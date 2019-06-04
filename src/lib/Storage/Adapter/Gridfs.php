<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Storage\Adapter;

use Balloon\Node\Collection;
use Balloon\Node\File;
use Balloon\Node\NodeInterface;
use Balloon\Server\User;
use Balloon\Storage\Exception;
use MongoDB\BSON\Binary;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Database;
use MongoDB\GridFS\Bucket;
use Psr\Log\LoggerInterface;

class Gridfs implements AdapterInterface
{
    /**
     * Grid chunks.
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
    public function deleteCollection(Collection $collection): ?array
    {
        return $collection->getAttributes()['storage'];
    }

    /**
     * {@inheritdoc}
     */
    public function forceDeleteCollection(Collection $collection): bool
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function undelete(NodeInterface $node): ?array
    {
        return $node->getAttributes()['storage'];
    }

    /**
     * {@inheritdoc}
     */
    public function rename(NodeInterface $node, string $new_name): ?array
    {
        return $node->getAttributes()['storage'];
    }

    /**
     * {@inheritdoc}
     */
    public function readonly(NodeInterface $node, bool $readonly = true): ?array
    {
        return $node->getAttributes()['storage'];
    }

    /**
     * {@inheritdoc}
     */
    public function hasNode(NodeInterface $node): bool
    {
        return null !== $this->getFileById($node->getId());
    }

    /**
     * {@inheritdoc}
     */
    public function forceDeleteFile(File $file, ?int $version = null): bool
    {
        try {
            $exists = $this->getFileById($this->getId($file, $version));
        } catch (Exception\BlobNotFound $e) {
            return true;
        }

        if (null === $exists) {
            $this->logger->debug('gridfs blob ['.$exists['_id'].'] was not found for file reference ['.$file->getId().']', [
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
    public function deleteFile(File $file, ?int $version = null): ?array
    {
        return $file->getAttributes()['storage'];
    }

    /**
     * {@inheritdoc}
     */
    public function openReadStream(File $file)
    {
        return $this->gridfs->openDownloadStream($this->getId($file));
    }

    /**
     * {@inheritdoc}
     */
    public function storeFile(File $file, ObjectId $session): array
    {
        $this->logger->debug('finalize temporary file ['.$session.'] and add file ['.$file->getId().'] as reference', [
            'category' => get_class($this),
        ]);

        $md5 = $this->db->command([
            'filemd5' => $session,
            'root' => 'fs',
        ])->toArray()[0]['md5'];

        $blob = $this->getFileByHash($md5);

        if ($blob !== null) {
            $this->logger->debug('found existing file with hash ['.$md5.'], add file ['.$file->getId().'] as reference to ['.$blob['_id'].']', [
                'category' => get_class($this),
            ]);

            $this->db->selectCollection('fs.files')->updateOne([
                'md5' => $blob['md5'],
            ], [
                '$addToSet' => [
                    'metadata.references' => $file->getId(),
                ],
            ]);

            $this->gridfs->delete($session);

            return [
                'reference' => ['_id' => $blob['_id']],
                'size' => $blob['length'],
                'hash' => $md5,
            ];
        }

        $this->logger->debug('calculated hash ['.$md5.'] for temporary file ['.$session.'], remove temporary flag', [
            'category' => get_class($this),
        ]);

        $this->db->selectCollection('fs.files')->updateOne([
            '_id' => $session,
        ], [
            '$set' => [
                'md5' => $md5,
                'metadata.references' => [$file->getId()],
            ],
            '$unset' => [
                'metadata.temporary' => true,
            ],
        ]);

        $blob = $this->getFileById($session);

        return [
            'reference' => ['_id' => $session],
            'size' => $blob['length'],
            'hash' => $md5,
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function createCollection(Collection $parent, string $name): array
    {
        return [];
    }

    /**
     * {@inheritdoc}
     */
    public function move(NodeInterface $node, Collection $parent): ?array
    {
        return $node->getAttributes()['storage'];
    }

    /**
     * {@inheritdoc}
     */
    public function storeTemporaryFile($stream, User $user, ?ObjectId $session = null): ObjectId
    {
        $exists = $session;

        if ($session === null) {
            $session = new ObjectId();

            $this->logger->info('create new tempory storage file ['.$session.']', [
                'category' => get_class($this),
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
            '_id' => $session,
        ]);

        if ($temp === null) {
            throw new Exception\SessionNotFound('temporary storage for this file is gone');
        }

        $this->storeStream($stream, $user, $exists, $temp);

        return $session;
    }

    /**
     * Get file blob id.
     */
    protected function getId(NodeInterface $node, ?int $version = null): ObjectId
    {
        $attributes = $node->getAttributes();

        if ($version !== null) {
            $history = $node->getHistory();

            $key = array_search($version, array_column($history, 'version'), true);
            $blobs = array_column($history, 'storage');

            if ($key === false || !isset($blobs[$key]['_id'])) {
                throw new Exception\BlobNotFound('attributes do not contain a gridfs id storage._id');
            }

            return $blobs[$key]['_id'];
        }

        if (!isset($attributes['storage']['_id'])) {
            throw new Exception\BlobNotFound('attributes do not contain a gridfs id storage._id');
        }

        return $attributes['storage']['_id'];
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
     * Store stream content.
     */
    protected function storeStream($stream, User $user, ?ObjectId $exists, array $temp): int
    {
        $data = null;
        $length = $temp['length'];
        $chunks = 0;
        $left = 0;

        if ($exists !== null) {
            $chunks = (int) ceil($temp['length'] / $temp['chunkSize']);
            $left = (int) ($chunks * $temp['chunkSize'] - $temp['length']);

            if ($left < 0) {
                $left = 0;
            } else {
                $this->logger->debug('found existing chunks ['.$chunks.'] for temporary file ['.$temp['_id'].'] while the last chunk has ['.$left.'] bytes free', [
                    'category' => get_class($this),
                ]);

                --$chunks;

                $last = $this->db->selectCollection('fs.chunks')->findOne([
                    'files_id' => $temp['_id'],
                    'n' => $chunks,
                ]);

                if ($last === null) {
                    throw new Exception\ChunkNotFound('Chunk not found, file is corrupt');
                }

                $data = $last['data']->getData();
            }
        }

        while (!feof($stream)) {
            if ($data !== null) {
                $append = $this->readStream($stream, $left);
                $data .= $append;
                $chunk = new Binary($data, Binary::TYPE_GENERIC);
                $size = mb_strlen($append, '8bit');
                $length += $size;

                $this->logger->debug('append data ['.$size.'] to last chunk ['.$chunks.'] in temporary file ['.$temp['_id'].']', [
                    'category' => get_class($this),
                ]);

                $last = $this->db->selectCollection('fs.chunks')->updateOne([
                    'files_id' => $temp['_id'],
                    'n' => $chunks,
                ], [
                    '$set' => [
                        'data' => $chunk,
                    ],
                ]);

                ++$chunks;
                $data = null;

                continue;
            }

            $content = $this->readStream($stream, $temp['chunkSize']);
            $size = mb_strlen($content, '8bit');

            if ($size === 0) {
                continue;
            }

            $length += $size;

            $chunk = new Binary($content, Binary::TYPE_GENERIC);
            $last = $this->db->selectCollection('fs.chunks')->insertOne([
                'files_id' => $temp['_id'],
                'n' => $chunks,
                'data' => $chunk,
            ]);

            $this->logger->debug('inserted new chunk ['.$last->getInsertedId().'] ['.$size.'] in temporary file ['.$temp['_id'].']', [
                'category' => get_class($this),
            ]);

            ++$chunks;

            continue;
        }

        $this->verifyQuota($user, $length, $temp['_id']);

        $this->db->selectCollection('fs.files')->updateOne([
            '_id' => $temp['_id'],
        ], [
            '$set' => [
                'uploadDate' => new UTCDateTime(),
                'length' => $length,
            ],
        ]);

        return $length;
    }

    /**
     * Verify quota.
     */
    protected function verifyQuota(User $user, int $size, ObjectId $session): bool
    {
        if (!$user->checkQuota($size)) {
            $this->logger->warning('stop adding chunk, user ['.$user->getId().'] quota is full, remove upload session', [
                'category' => get_class($this),
            ]);

            $this->gridfs->delete($session);

            throw new Exception\InsufficientStorage(
                'user quota is full',
                Exception\InsufficientStorage::USER_QUOTA_FULL
            );
        }

        return true;
    }

    /**
     * Read x bytes from stream.
     */
    protected function readStream($stream, int $bytes): string
    {
        $length = 0;
        $data = '';
        while (!feof($stream)) {
            if ($length + 8192 > $bytes) {
                $max = $bytes - $length;

                if ($max === 0) {
                    return $data;
                }

                $length += $max;

                return $data .= fread($stream, $max);
            }

            $length += 8192;
            $data .= fread($stream, 8192);
        }

        return $data;
    }
}
