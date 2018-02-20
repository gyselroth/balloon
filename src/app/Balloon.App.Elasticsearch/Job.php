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
use Balloon\Filesystem\Node\File;
use Balloon\Filesystem\Node\NodeInterface;
use Balloon\Filesystem\Storage;
use Balloon\Server;
use MongoDB\BSON\ObjectId;
use Psr\Log\LoggerInterface;
use TaskScheduler\AbstractJob;

class Job extends AbstractJob
{
    /**
     * Document actions.
     */
    const ACTION_CREATE = 0;
    const ACTION_UPDATE = 1;
    const ACTION_DELETE_COLLECTION = 2;
    const ACTION_DELETE_FILE = 3;

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
        $this->setMemoryLimit();
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
        $this->logger->debug('elasticsearch document action ['.$this->data['action'].'] for node ['.$this->data['id'].']', [
            'category' => get_class($this),
        ]);

        switch ($this->data['action']) {
            case self::ACTION_CREATE:
                $this->createDocument($this->fs->findNodeById($this->data['id']));

            break;
            case self::ACTION_DELETE_FILE:
                $this->deleteFileDocument($this->data['id'], $this->data['storage']);

            break;
            case self::ACTION_DELETE_COLLECTION:
                $this->deleteCollectionDocument($this->data['id']);

            break;
            case self::ACTION_UPDATE:
                $this->updateDocument($this->fs->findNodeById($this->data['id']));

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

        if ($node instanceof File) {
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

        if ($node instanceof File) {
            $this->storeBlob($node);
        }

        return true;
    }

    /**
     * Create document.
     *
     * @param ObjectId $node
     * @param array storage_reference
     *
     * @return bool
     */
    public function deleteCollectionDocument(ObjectId $node): bool
    {
        $this->logger->info('delete elasticsearch  document for collection ['.$node.']', [
            'category' => get_class($this),
        ]);

        $params = [
            'id' => (string) $node,
            'type' => 'storage',
            'index' => $this->es->getIndex(),
        ];

        $this->es->getEsClient()->delete($params);

        return true;
    }

    /**
     * Create document.
     *
     * @param ObjectId $node
     * @param array storage_reference
     *
     * @return bool
     */
    public function deleteFileDocument(ObjectId $node, ?array $storage_reference): bool
    {
        $this->logger->info('delete elasticsearch document for file ['.$node.']', [
            'category' => get_class($this),
        ]);

        $params = [
            'id' => (string) $node,
            'type' => 'storage',
            'index' => $this->es->getIndex(),
        ];

        $this->es->getEsClient()->delete($params);

        if ($storage_reference !== null) {
            $this->deleteBlob($node, $storage_reference);
        }

        return true;
    }

    /**
     * Set memory limit.
     *
     * @return Job
     */
    protected function setMemoryLimit(): self
    {
        $limit = (int) ini_get('memory_limit') * 1024 * 1024;
        $required = $this->size_limit * 2;
        if ($limit !== -1 && $limit < $limit + $required) {
            ini_set('memory_limit', (string) (($limit + $required) * 1024 * 1024));
        }

        return $this;
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
     * Delete blob.
     *
     * @param ObjectId $node
     * @param array    $storage_reference
     *
     * @return bool
     */
    protected function deleteBlob(ObjectId $node, array $storage_reference): bool
    {
        $params = [
            'index' => $this->es->getIndex(),
            'id' => (string) $storage_reference['_id'],
            'type' => 'fs',
        ];

        $this->es->getEsClient()->delete($params);

        return true;
    }

    /**
     * Add or update blob.
     *
     * @param File $node
     *
     * @return bool
     */
    protected function storeBlob(File $node): bool
    {
        $this->logger->debug('store file blob for node ['.$node->getId().'] to elasticsearch', [
            'category' => get_class($this),
            'size' => $node->getSize(),
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
