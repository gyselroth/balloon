<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2018 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\Elasticsearch;

use Balloon\Filesystem;
use Balloon\Filesystem\Exception\NotFound as NotFoundException;
use Balloon\Filesystem\Node\CollectionInterface;
use Balloon\Filesystem\Node\FileInterface;
use Balloon\Filesystem\Node\NodeInterface;
use Balloon\Filesystem\Storage;
use Balloon\Server;
use Psr\Log\LoggerInterface;
use TaskScheduler\AbstractJob;

class Job extends AbstractJob
{
    /**
     * Document actions.
     */
    const ACTION_CREATE = 0;
    const ACTION_DELETE = 1;
    const ACTION_UPDATE = 2;

    /**
     * Filesystem.
     *
     * @var Filesystem
     */
    protected $fs;

    /**
     * Elasticsearch.
     *
     * @var Elasticsearch
     */
    protected $es;

    /**
     * Storage.
     *
     * @var Storage
     */
    protected $storage;

    /**
     * Logger.
     *
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * Node attribute decorator.
     *
     * @var NodeAttributeDecorator
     */
    protected $decorator;

    /**
     * File size limit.
     *
     * @var int
     */
    protected $size_limit = 52428800;

    /**
     * Constructor.
     *
     * @param Elasticsarch           $es
     * @param Storage                $storage
     * @param Server                 $server
     * @param NodeAttributeDecorator $decorator
     * @param LoggerInterface        $logger
     * @param iterable               $config
     */
    public function __construct(Elasticsearch $es, Storage $storage, Server $server, NodeAttributeDecorator $decorator, LoggerInterface $logger, Iterable $config = null)
    {
        $this->es = $es;
        $this->storage = $storage;
        $this->fs = $server->getFilesystem();
        $this->decorator = $decorator;
        $this->logger = $logger;
        $this->setOptions($config);
    }

    /**
     * Set options.
     *
     * @param iterable $config
     *
     * @return Job
     */
    public function setOptions(?Iterable $config = null): self
    {
        if (null === $config) {
            return $this;
        }

        foreach ($config as $option => $value) {
            switch ($option) {
                case 'size_limit':
                    $this->size_limit = (int) $value;

                break;
                default:
                    throw new Exception('invalid option '.$option.' given');
            }
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function start(): bool
    {
        try {
            $node = $this->fs->findNodeById($this->data['id']);
        } catch (NotFoundException $e) {
            $this->logger->debug('node ['.$this->data['id'].'] is deleted, remove from elasticsearch', [
                'category' => get_class($this),
            ]);

            $this->deleteDocument($this->data['id']);

            return true;
        }

        $this->logger->debug('elasticsearch document action ['.$this->data['action'].'] for node ['.$this->data['id'].']', [
            'category' => get_class($this),
        ]);

        switch ($this->data['action']) {
            case self::ACTION_CREATE:
                $this->createDocument($node);

            break;
            case self::ACTION_DELETE:
                $this->deleteDocument($node->getId());

            break;
            case self::ACTION_UPDATE:
                $this->updateDocument($node);

            break;
            default:
                throw new Exception('invalid document action given');
        }

        return true;
    }

    /**
     * Create document.
     *
     * @param NodeInterface $node
     *
     * @return bool
     */
    public function createDocument(NodeInterface $node): bool
    {
        $this->logger->info('create elasticsearch document for node ['.$node->getId().']', [
            'category' => get_class($this),
        ]);

        $params = $this->getParams($node);
        $params['body'] = $this->decorator->decorate($node);

        $this->es->getEsClient()->index($params);
        $that = $this;

        if ($node instanceof CollectionInterface) {
            $node->doRecursiveAction(function ($node) use ($that) {
                $that->createDocument($node);
            }, NodeInterface::DELETED_INCLUDE);
        } elseif ($node instanceof FileInterface) {
            $this->storeBlob($node);
        }

        return true;
    }

    /**
     * Update document.
     *
     * @param NodeInterface $node
     *
     * @return bool
     */
    public function updateDocument(NodeInterface $node): bool
    {
        $this->logger->info('update elasticsearch document for node ['.$node->getId().']', [
            'category' => get_class($this),
        ]);

        $params = $this->getParams($node);
        $params['body'] = $this->decorator->decorate($node);
        $this->es->getEsClient()->index($params);
        $that = $this;

        if ($node instanceof CollectionInterface) {
            $node->doRecursiveAction(function ($node) use ($that) {
                $that->updateDocument($node);
            }, NodeInterface::DELETED_INCLUDE);
        } elseif ($node instanceof FileInterface) {
            $this->storeBlob($node);
        }

        return true;
    }

    /**
     * Create document.
     *
     * @param ObjectId $node
     *
     * @return bool
     */
    public function deleteDocument(ObjectId $node): bool
    {
        $this->logger->info('delete elasticsearch document for node ['.$node.']', [
            'category' => get_class($this),
        ]);

        $params = [
            'id' => (string) $node,
            'type' => 'storage',
            'index' => $this->es->getIndex(),
        ];

        $this->es->getEsClient()->delete($params);
        $that = $this;

        if ($node instanceof CollectionInterface) {
            $node->doRecursiveAction(function ($node) use ($that) {
                $that->deleteDocument($node);
            }, NodeInterface::DELETED_INCLUDE);
        } elseif ($node instanceof FileInterface) {
            $this->deleteBlob($node);
        }

        return true;
    }

    /**
     * Get params.
     *
     * @param NodeInterface $node
     *
     * @return array
     */
    protected function getParams(NodeInterface $node): array
    {
        return [
            'index' => $this->es->getIndex(),
            'id' => (string) $node->getId(),
            'type' => 'storage',
        ];
    }

    /**
     * Add or update blob.
     *
     * @param FileInterface $node
     *
     * @return bool
     */
    protected function storeBlob(FileInterface $node): bool
    {
        $this->logger->debug('store file blob for node ['.$node->getId().'] to elasticsearch', [
            'category' => get_class($this),
        ]);

        if ($node->getSize() > $this->size_limit) {
            $this->logger->debug('skip file blob ['.$node->getId().'] because size ['.$node->getSize().'] is bigger than the maximum configured ['.$this->size_limit.']', [
                'category' => get_class($this),
            ]);

            return false;
        }
        if ($node->getSize() === 0) {
            $this->logger->debug('skip empty file blob ['.$node->getId().']', [
                'category' => get_class($this),
            ]);

            return false;
        }

        $meta = $this->storage->getFileMeta($node);
        $content = base64_encode(stream_get_contents($node->get()));

        $metadata = $meta['metadata'];
        array_walk_recursive($metadata, function (&$value) { $value = (string) $value; });

        $params = [
            'index' => $this->es->getIndex(),
            'id' => (string) $meta['_id'],
            'type' => 'fs',
            'body' => [
                'metadata' => $metadata,
                'content' => $content,
            ],
        ];

        $this->es->getEsClient()->index($params);

        return true;
    }
}