<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copyright (c) 2012-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\Elasticsearch;

use Balloon\Filesystem;
use Balloon\Filesystem\Node\Collection;
use Balloon\Filesystem\Node\File;
use Balloon\Filesystem\Node\NodeInterface;
use Balloon\Server;
use Elasticsearch\Client;
use InvalidArgumentException;
use MongoDB\BSON\ObjectId;
use Psr\Log\LoggerInterface;
use TaskScheduler\AbstractJob;

class Job extends AbstractJob
{
    /**
     * Document actions.
     */
    public const ACTION_CREATE = 0;
    public const ACTION_UPDATE = 1;
    public const ACTION_DELETE_COLLECTION = 2;
    public const ACTION_DELETE_FILE = 3;
    public const ACTION_ADD_SHARE = 4;
    public const ACTION_DELETE_SHARE = 5;

    /**
     * Filesystem.
     *
     * @var Filesystem
     */
    protected $fs;

    /**
     * Elasticsearch.
     *
     * @var Client
     */
    protected $client;

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
     */
    public function __construct(Client $client, Server $server, NodeAttributeDecorator $decorator, LoggerInterface $logger, iterable $config = null)
    {
        $this->client = $client;
        $this->fs = $server->getFilesystem();
        $this->decorator = $decorator;
        $this->logger = $logger;
        $this->setOptions($config);
        $this->setMemoryLimit();
    }

    /**
     * Set options.
     */
    public function setOptions(?iterable $config = null): self
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
                    throw new InvalidArgumentException('invalid option '.$option.' given');
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
            'category' => static::class,
        ]);

        switch ($this->data['action']) {
            case self::ACTION_CREATE:
                $this->createDocument($this->fs->findNodeById($this->data['id']));

            break;
            case self::ACTION_DELETE_FILE:
                $this->deleteFileDocument($this->data['id'], $this->data['hash']);

            break;
            case self::ACTION_DELETE_COLLECTION:
                $this->deleteCollectionDocument($this->data['id']);

            break;
            case self::ACTION_UPDATE:
                $this->updateDocument($this->fs->findNodeById($this->data['id']), $this->data['hash']);

            break;
            case self::ACTION_ADD_SHARE:
                $this->addShare($this->fs->findNodeById($this->data['id']));

            break;
            case self::ACTION_DELETE_SHARE:
                $this->deleteShare($this->fs->findNodeById($this->data['id']));

            break;
            default:
                throw new InvalidArgumentException('invalid document action given');
        }

        return true;
    }

    /**
     * Create document.
     */
    public function createDocument(NodeInterface $node): bool
    {
        $this->logger->info('create elasticsearch document for node ['.$node->getId().']', [
            'category' => static::class,
        ]);

        $params = $this->getParams($node);
        $params['body'] = $this->decorator->decorate($node);

        $this->client->index($params);

        if ($node instanceof File) {
            $this->storeBlob($node);
        }

        return true;
    }

    /**
     * Update document.
     */
    public function updateDocument(NodeInterface $node, ?string $hash): bool
    {
        $this->logger->info('update elasticsearch document for node ['.$node->getId().']', [
            'category' => static::class,
        ]);

        $params = $this->getParams($node);
        $params['body'] = $this->decorator->decorate($node);
        $this->client->index($params);

        if ($node instanceof File && $hash !== $node->getHash()) {
            if ($hash !== null) {
                $this->deleteBlobReference((string) $node->getId(), $hash);
            }

            $this->storeBlob($node);
        }

        return true;
    }

    /**
     * Delete collection document.
     */
    public function deleteCollectionDocument(ObjectId $node): bool
    {
        $this->logger->info('delete elasticsearch document for collection ['.$node.']', [
            'category' => static::class,
        ]);

        $params = [
            'id' => (string) $node,
            'index' => 'nodes',
        ];

        $this->client->delete($params);

        return true;
    }

    /**
     * Create document.
     */
    public function deleteFileDocument(ObjectId $node, ?string $hash): bool
    {
        $this->logger->info('delete elasticsearch document for file ['.$node.']', [
            'category' => static::class,
        ]);

        $params = [
            'id' => (string) $node,
            'index' => 'nodes',
        ];

        $this->client->delete($params);

        if ($hash !== null) {
            return $this->deleteBlobReference((string) $node, $hash);
        }

        return true;
    }

    /**
     * Add share.
     */
    protected function addShare(Collection $collection): bool
    {
        $that = $this;
        $collection->doRecursiveAction(function ($node) use ($that) {
            if ($node instanceof Collection) {
                $that->addShare($node);
            } else {
                $that->storeBlob($node);
            }
        }, NodeInterface::DELETED_INCLUDE);

        return true;
    }

    /**
     * Delete share.
     */
    protected function deleteShare(Collection $collection): bool
    {
        $that = $this;
        $collection->doRecursiveAction(function ($node) use ($that) {
            if ($node instanceof Collection) {
                $that->deleteShare($node);
            } else {
                $that->storeBlob($node);
            }
        }, NodeInterface::DELETED_INCLUDE);

        return true;
    }

    /**
     * Delete blob reference.
     */
    protected function deleteBlobReference(string $id, string $hash): bool
    {
        $result = $this->getFileByHash($hash);
        if ($result === null) {
            return true;
        }

        $ref = $result['_source']['metadata']['ref'];
        $key = array_search($id, array_column($ref, 'id'));

        if ($key !== false) {
            unset($ref[$key]);
        }

        if (count($ref) >= 1) {
            $this->logger->debug('elasticsarch blob document ['.$result['_id'].'] still has references left, just remove the reference ['.$id.']', [
                'category' => static::class,
            ]);

            $meta = $result['_source']['metadata'];
            $meta['ref'] = array_values($ref);

            return $this->updateBlob($result['_id'], $meta);
        }

        $this->logger->debug('elasticsarch blob document ['.$result['_id'].'] has no references left, remove completely', [
         'category' => static::class,
        ]);

        return $this->deleteBlob($result['_id']);
    }

    /**
     * Set memory limit.
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
     */
    protected function getParams(NodeInterface $node): array
    {
        return [
            'index' => 'nodes',
            'id' => (string) $node->getId(),
        ];
    }

    /**
     * Delete blob.
     */
    protected function deleteBlob(string $id): bool
    {
        $params = [
            'index' => 'blobs',
            'id' => $id,
        ];

        $this->client->delete($params);

        return true;
    }

    /**
     * Get stored file.
     */
    protected function getFileByHash(?string $hash): ?array
    {
        if ($hash === null) {
            return null;
        }

        $params = [
            'index' => 'blobs',
            'body' => [
                'query' => [
                    'match' => [
                        'md5' => $hash,
                    ],
                ],
            ],
        ];

        $result = $this->client->search($params);

        if (count($result['hits']['hits']) === 0) {
            return null;
        }
        if (count($result['hits']['hits']) > 1) {
            throw new Exception\MultipleDocumentsFound('multiple elasticsearch documents found by the same hash');
        }

        return $result['hits']['hits'][0];
    }

    /**
     * Add or update blob.
     */
    protected function storeBlob(File $file): bool
    {
        $this->logger->debug('store file blob for node ['.$file->getId().'] to elasticsearch', [
            'category' => static::class,
            'size' => $file->getSize(),
        ]);

        if ($file->getSize() > $this->size_limit) {
            $this->logger->debug('skip file blob ['.$file->getId().'] because size ['.$file->getSize().'] is bigger than the maximum configured ['.$this->size_limit.']', [
                'category' => static::class,
            ]);

            return false;
        }
        if ($file->getSize() === 0) {
            $this->logger->debug('skip empty file blob ['.$file->getId().']', [
                'category' => static::class,
            ]);

            return false;
        }

        $result = $this->getFileByHash($file->getHash());

        if ($result === null) {
            return $this->createNewBlob($file);
        }

        $update = $this->prepareUpdate($file, $result['_source']['metadata']);

        return $this->updateBlob($result['_id'], $update);
    }

    /**
     * Prepare references update.
     */
    protected function prepareUpdate(File $file, array $references): array
    {
        $refs = array_column($references['ref'], 'id');
        if (!in_array((string) $file->getId(), $refs)) {
            $references['ref'][] = [
                'id' => (string) $file->getId(),
                'owner' => (string) $file->getOwner(),
            ];
        }

        $share_refs = array_column($references['share_ref'], 'id');
        $key = array_search((string) $file->getId(), $share_refs);
        if (!$file->isShareMember() && $key !== false) {
            unset($references['share_ref'][$key]);
            $references['share_ref'] = array_values($references['share_ref']);
        } elseif ($file->isShareMember() && $key === false) {
            $references['share_ref'][] = [
                'id' => (string) $file->getId(),
                'share' => (string) $file->getShareId(),
            ];
        }

        return $references;
    }

    /**
     * Add new blob.
     */
    protected function createNewBlob(File $file): bool
    {
        $meta = [
            'ref' => [[
                'id' => (string) $file->getId(),
                'owner' => (string) $file->getOwner(),
            ]],
            'share_ref' => [],
        ];

        if ($file->isShareMember()) {
            $meta['share_ref'] = [[
                'id' => (string) $file->getId(),
                'share' => (string) $file->getShareId(),
            ]];
        }

        $content = base64_encode(stream_get_contents($file->get()));

        $params = [
            'pipeline' => 'attachments',
            'index' => 'blobs',
            'id' => $file->getHash(),
            'body' => [
                'md5' => $file->getHash(),
                'metadata' => $meta,
                'content' => $content,
            ],
        ];

        $this->client->index($params);

        return true;
    }

    /**
     * Update blob.
     */
    protected function updateBlob(string $id, array $meta): bool
    {
        $params = [
            'index' => 'blobs',
            'id' => $id,
            'body' => [
                'doc' => [
                    'metadata' => $meta,
                ],
            ],
        ];

        $this->client->update($params);

        return true;
    }
}
