<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2018 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Filesystem\Storage\Adapter;

use Balloon\Filesystem\Exception;
use Balloon\Filesystem\Node\File;
use MongoDB\BSON\ObjectId;
use MongoDB\Database;
use MongoDB\GridFS\Bucket;
use Psr\Log\LoggerInterface;

class Gridfs implements AdapterInterface
{
    /**
     * Database.
     *
     * @var Database
     */
    protected $db;

    /**
     * GridFS.
     *
     * @var Bucket
     */
    protected $gridfs;

    /**
     * Logger.
     *
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * GridFS storage.
     *
     * @param Database
     * @param LoggerInterface $logger
     */
    public function __construct(Database $db, LoggerInterface $logger)
    {
        $this->db = $db;
        $this->gridfs = $db->selectGridFSBucket();
        $this->logger = $logger;
    }

    /**
     * {@inheritdoc}
     */
    public function hasNode(NodeInterface $node, array $attributes): bool
    {
        return null !== $this->getFileById($attributes);
    }

    /**
     * {@inheritdoc}
     */
    public function deleteFile(File $file, array $attributes): bool
    {
        if (!isset($attributes['_id'])) {
            throw new Exception('attributes do not contain a gridfs id');
        }

        $exists = $this->getFileById($attributes['_id']);

        if (null === $exists) {
            $this->logger->debug('gridfs content node ['.$exists['_id'].'] was not found, file reference=['.$file->getId().']', [
                'category' => get_class($this),
            ]);

            return false;
        }

        if (!isset($exists['metadata']['references'])) {
            $this->gridfs->delete($exists['_id']);

            return true;
        }

        $refs = $exists['metadata']['references'];
        if (($key = array_search($file->getId(), $refs)) !== false) {
            unset($refs[$key]);
            $refs = array_values($refs);
        }

        if (count($refs) >= 1) {
            $this->logger->debug('gridfs content node ['.$exists['_id'].'] still has references left, just remove the reference ['.$file->getId().']', [
                'category' => get_class($this),
            ]);

            $this->db->{'fs.files'}->updateOne(['_id' => $exists['_id']], [
                '$set' => ['metadata.references' => $refs],
            ]);
        } else {
            $this->logger->debug('gridfs content node ['.$exists['_id'].'] has no references left, delete node completely', [
                'category' => get_class($this),
            ]);

            $this->gridfs->delete($exists['_id']);
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function getFile(File $file, array $attributes)
    {
        if (!isset($attributes['_id'])) {
            throw new Exception('attributes do not contain a gridfs id');
        }

        return $this->gridfs->openDownloadStream($attributes['_id']);
    }

    /**
     * {@inheritdoc}
     */
    public function storeFile(File $file, $contents): array
    {
        $exists = $this->getFileByHash($file->getHash());

        if (null === $exists) {
            return $this->storeNew($file, $contents);
        }

        $this->logger->info('gridfs content node with hash ['.$file->getHash().'] does already exists,
          add new file reference for ['.$file->getId().']', [
            'category' => get_class($this),
        ]);

        $action['$addToSet'] = [
            'metadata.references' => $file->getId(),
        ];

        $this->db->{'fs.files'}->updateOne(['md5' => $file->getHash()], $action);

        return ['_id' => $exists['_id']];
    }

    /**
     * Create collection.
     *
     * @return array
     */
    public function createCollection(Collection $collection): array
    {
        return [];
    }

    /**
     * Get stored file.
     *
     * @param ObjectId $id
     *
     * @return array
     */
    protected function getFileById(ObjectId $id): ?array
    {
        return $this->gridfs->findOne(['_id' => $id]);
    }

    /**
     * Get stored file.
     *
     * @param string $hash
     *
     * @return array
     */
    protected function getFileByHash(string $hash): ?array
    {
        return $this->gridfs->findOne(['md5' => $hash]);
    }

    /**
     * Store new file.
     *
     * @param File     $file
     * @param resource $contents
     *
     * @return array
     */
    protected function storeNew(File $file, $contents): array
    {
        set_time_limit((int) ($file->getSize() / 15339168));
        $id = new ObjectId();

        //somehow mongo-connector does not catch metadata when set during uploadFromStream()
        $stream = $this->gridfs->uploadFromStream($id, $contents, ['_id' => $id, 'metadata' => [
            'references' => [
                $file->getId(),
            ],
        ]]);

        $this->logger->info('added new gridfs content node ['.$id.'] for file ['.$file->getId().']', [
            'category' => get_class($this),
        ]);

        return ['_id' => $id];
    }
}
