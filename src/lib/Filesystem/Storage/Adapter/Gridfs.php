<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2018 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Filesystem\Storage\Adapter;

use Balloon\Filesystem\Node\Collection;
use Balloon\Filesystem\Node\File;
use Balloon\Filesystem\Node\NodeInterface;
use Balloon\Filesystem\Storage\Exception;
use MongoDB\BSON\ObjectId;
use MongoDB\Database;
use MongoDB\GridFS\Bucket;
use Psr\Log\LoggerInterface;
use MongoDB\BSON\Binary;
use MongoDB\BSON\UTCDateTime;

class Gridfs implements AdapterInterface
{
    /**
     * Grid chunks
     */
    public const CHUNK_SIZE = 261120;

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
            throw new Exception\NotFound('attributes do not contain a gridfs id');
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
            throw new Exception\NotFound('attributes do not contain a gridfs id');
        }

        return $this->gridfs->openDownloadStream($attributes['_id']);
    }

    /**
     * {@inheritdoc}
     */
    public function storeFile(File $file, ObjectId $session): array
    {
        $this->logger->debug('finalize temporary file ['.$session.'] and add file ['.$file->getId().'] as reference', [
            'category' => get_class($this)
        ]);

        $md5 = $this->db->command([
            'filemd5' => $session,
            'root' => 'fs'
        ])->toArray()[0]["md5"];

        $blob = $this->getFileByHash($md5);

        if($blob !== null) {
            $this->logger->debug('found existing file with hash ['.$md5.'], add file ['.$file->getId().'] as reference to ['.$blob['_id'].']', [
                'category' => get_class($this)
            ]);

            $this->db->selectCollection('fs.files')->updateOne([
                'md5' => $blob['md5']
            ],[
                '$addToSet' => [
                    'metadata.references' => $file->getId(),
                ]
            ]);

            $this->gridfs->delete($session);

            return [
                'reference' => ['_id' => $blob['_id']],
                'size' => $blob['length'],
                'hash' => $md5,
            ];
        }

        $this->logger->debug('calculated hash ['.$md5.'] for temporary file ['.$session.'], remove temporary flag', [
            'category' => get_class($this)
        ]);

        $this->db->selectCollection('fs.files')->updateOne([
            '_id' => $session
        ],[
            '$set' => [
                'md5' => $md5,
                'metadata.references' => [$file->getId()],
            ],
            '$unset' => [
                'metadata.temporary' => true
            ]
        ]);

        $blob = $this->getFileById($session);

        return [
            'reference' => ['_id' => $session],
            'size' => $blob['length'],
            'hash' => $md5,
        ];
    }

    /**
     * Create collection.
     */
    public function createCollection(Collection $collection): array
    {
        return [];
    }

    /**
     * Get stored file.
     */
    protected function getFileById(ObjectId $id): ?array
    {
        return $this->gridfs->findOne(['_id' => $id]);
    }

    /**
     * Get stored file.
     */
    protected function getFileByHash(string $hash): ?array
    {
        return $this->gridfs->findOne(['md5' => $hash]);
    }

    /**
     * {@inheritdoc}
     */
    public function storeTemporaryFile($stream, User $user, ?ObjectId $session=null): ObjectId
    {
        $exists = $session;

        if($session === null) {
            $session = new ObjectId();

            $this->logger->info('create new tempory storage file ['.$session.']', [
                'category' => get_class($this)
            ]);

            $this->db->selectCollection('fs.files')->insertOne([
                '_id' => $session,
                'chunkSize' => self::CHUNK_SIZE,
                'length' => 0,
                'uploadDate' => new UTCDateTime(),
                'metadata' => ['temporary' => true],
            ]);
        }

        $temp = $this->db->selectCollection('fs.files')->findOne([
            '_id' => $session
        ]);

        if($temp === null) {
            throw new Exception\SessionNotFound('Temporary storage for this file is gone');
        }

        $this->storeStream($stream, $user, $temp);
        return $session;
    }

    /**
     * Store stream content
     */
    protected function storeStream($stream, User $user, array $temp): int
    {
        $data = null;
        $length = $temp['length'];
        $chunks = 0;

        if($exists !== null) {
            $chunks = (int)ceil($temp['length'] / $temp['chunkSize']);
            $left = (int)($chunks * $temp['chunkSize'] - $temp['length']);

            $this->logger->debug('found existing chunks ['.$chunks.'] for temporary file ['.$temp['_id'].'] while the last chunk has ['.$left.'] bytes free', [
                'category' => get_class($this),
            ]);

            $chunks--;
            $last = $this->db->selectCollection('fs.chunks')->findOne([
                'files_id' => $temp['_id'],
                'n' => $chunks
            ]);

            if($last === null) {
                throw new Exception\ChunkNotFound('Chunk not found, file is corrupt');
            }

            $data = $last['data']->getData();
        }

        while (!feof($stream)) {
            if($data !== null) {
                $data .= $this->readStream($stream, $left);
                $chunk = new Binary($data, Binary::TYPE_GENERIC);
                $length += mb_strlen($data, '8bit');

                $this->logger->debug('append data to last chunk ['.$chunks.'] in temporary file ['.$temp['_id'].']', [
                    'category' => get_class($this),
                ]);

                $last = $this->db->selectCollection('fs.chunks')->updateOne([
                    'files_id' => $temp['_id'],
                    'n' => $chunks
                ], [
                    '$set' => [
                        'data' => $chunk,
                    ]
                ]);

                $chunks++;
                $data = null;
                continue;
            }

            $content = $this->readStream($stream, $temp['chunkSize']);
            $length += mb_strlen($content, '8bit');
            $chunk = new Binary($content, Binary::TYPE_GENERIC);
            $last = $this->db->selectCollection('fs.chunks')->insertOne([
                'files_id' => $tmp['_id'],
                'n' => $chunks,
                'data' => $chunk
            ]);

            $this->logger->debug('inserted new chunk ['.$last->getInsertedId().'] in temporary file ['.$temp['_id'].']', [
                'category' => get_class($this),
            ]);

            $chunks++;
            continue;
        }

        $this->db->selectCollection('fs.files')->updateOne([
            '_id' => $tmp['_id']
        ],[
            '$set' => [
                'uploadDate' => new UTCDateTime(),
                'length' => $length
            ]
        ]);

        return $length;
    }

    if (!$this->_user->checkQuota($size)) {
        $this->_logger->warning('could not execute PUT, user quota is full', [
                'category' => get_class($this),
            ]);

            if (true === $new) {
                $this->_forceDelete();
             }

            throw new Exception\InsufficientStorage(
                'user quota is full',
                 Exception\InsufficientStorage::USER_QUOTA_FULL
             );
        }


    /**
     * Read x bytes from stream
     */
    protected function readStream($stream, int $bytes): string
    {
        $length = 0;
        $data = '';
        while (!feof($stream)) {
            if($length + 8192 > $bytes) {
                $max = $bytes - $length;
                return $data .= fread($stream, $max);
            }

            $length += 8192;
            $data .= fread($stream, 8192);
        }

        return $data;
    }
}
