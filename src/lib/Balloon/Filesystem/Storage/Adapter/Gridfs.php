<?php
declare(strict_types=1);

/**
 * Balloon
 *
 * @author      Raffael Sahli <sahli@gyselroth.net>
 * @copyright   Copryright (c) 2012-2017 gyselroth GmbH (https://gyselroth.com)
 * @license     GPLv3 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Filesystem\Storage\Adapter;

use \Balloon\Filesystem\Exception;
use \MongoDB\Database;
use \MongoDB\BSON\ObjectId;
use \Psr\Log\LoggerInterface;
use \Balloon\Filesystem\Node\File;

class Gridfs implements AdapterInterface
{
    /**
     * Database
     *
     * @var Database
     */
    protected $db;


    /**
     * GridFS
     *
     * @var GridFSBucket
     */
    protected $gridfs;


    /**
     * Logger
     *
     * @var Logger
     */
    protected $logger;


    /**
     * GridFS storage
     *
     * @param   Database
     * @param   LoggerInterface $logger
     * @return  void
     */
    public function __construct(Database $db, LoggerInterface $logger)
    {
        $this->db = $db;
        $this->gridfs = $db->selectGridFSBucket();
        $this->logger = $logger;
    }


    /**
     * Check if file exists
     *
     * @param File $file
     * @param array $attributes
     */
    public function hasFile(File $file, array $attributes): bool
    {
        return $this->getFileById($attributes) !== null;
    }


    /**
     * Remove blob from gridfs
     *
     * @param   File $file
     * @param   array $attributes
     * @return  bool
     */
    public function deleteFile(File $file, array $attributes): bool
    {
        $exists = $this->getFileById($attributes['_id']);

        if ($exists === null) {
            $this->logger->debug('gridfs content node ['.$exists['_id'].'] was not found, file reference=['.$file->getId().']', [
                'category' => get_class($this),
            ]);

            return false;
        }

        if (!isset($exists['metadata'])) {
            $this->gridfs->delete($exists['_id']);
            return true;
        }

        $ref  = $exists['metadata']['ref'];

        if ($key = array_search((string)$file->getId(), array_column($ref, 'id'))) {
            unset($ref[$key]);
        }

        if (count($ref) >= 1) {
            $this->logger->debug('gridfs content node ['.$exists['_id'].'] still has references left, just remove the reference ['.$file->getId().']', [
                'category' => get_class($this),
            ]);

            $this->db->{'fs.files'}->updateOne(['_id' => $exists['_id']], [
                '$set' => ['metadata.ref' => $ref]
            ]);
        } else {
            $this->logger->debug('gridfs content node ['.$exists['_id'].'] has no references left, delete node completely', [
                'category' => get_class($this),
            ]);

            $bucket->delete($exists['_id']);
        }

        return true;
    }


    /**
     * Get stored file
     *
     * @param  File $file
     * @param  array $attributes
     * @return stream
     */
    public function getFile(File $file, array $attributes)
    {
        return $this->gridfs->openDownloadStream($attributes['_id']);
    }


    /**
     * Get stored file
     *
     * @param  ObjectId $id
     * @return array
     */
    protected function getFileById(ObjectId $id): ?array
    {
        return $this->gridfs->findOne(['_id' => $id]);
    }


    /**
     * Get stored file
     *
     * @param  string $hash
     * @return array
     */
    protected function getFileByHash(string $hash): ?array
    {
        return $this->gridfs->findOne(['md5' => $hash]);
    }


    /**
     * Store file
     *
     * @param   File $file
     * @param   resource $contents
     * @return  array
     */
    public function storeFile(File $file, $contents): array
    {
        $exists = $this->getFileByHash($file->getHash());

        if ($exists === null) {
            return $this->storeNew($file, $contents);
        }

        $this->logger->info('gridfs content node with hash ['.$file->getHash().'] does already exists,
          add new file reference for ['.$file->getId().']', [
            'category' => get_class($this),
        ]);

        $action['$addToSet'] = [
            'metadata.ref' => [
                'id'    => $file->getId(),
                'owner' => $file->getOwner()
            ]
        ];

        if ($file->isShareMember()) {
            $action['$addToSet']['metadata.share_ref'] = [
                'id'    => $file->getId(),
                'share' => $file->getShared()
            ];
        }

        $this->db->{'fs.files'}->updateOne(['md5' => $file->getHash()], $action);

        return ['_id' => $exists['_id']];
    }


    /**
     * Store new file
     *
     * @param  File $file
     * @param  resource $contents
     * @return array
     */
    protected function storeNew(File $file, $contents): array
    {
        $meta = [
            'ref' => [[
                'id'    => $file->getId(),
                'owner' => $file->getOwner()
            ]]
        ];

        if ($file->isShareMember()) {
            $meta['share_ref'] = [[
                'id'    => $file->getId(),
                'share' => $file->getShared()
            ]];
        } else {
            $meta['share_ref'] = [];
        }

        set_time_limit((int)($file->getSize() / 15339168));
        $id = new ObjectId();

        //somehow mongo-connector does not catch metadata when set during uploadFromStream()
        $stream = $this->gridfs->uploadFromStream($id, $contents, ['_id' => $id/*, 'metadata' => $file*/]);
        $this->db->{'fs.files'}->updateOne(['_id' => $id], [
            '$set' => ['metadata'=> $meta]
        ]);

        $this->logger->info('added new gridfs content node ['.$id.'] for file ['.$file->getId().']', [
            'category' => get_class($this),
        ]);

        return ['_id' => $id];
    }
}
