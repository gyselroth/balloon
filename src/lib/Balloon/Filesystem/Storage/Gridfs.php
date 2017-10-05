<?php
declare(strict_types=1);

/**
 * Balloon
 *
 * @author      Raffael Sahli <sahli@gyselroth.net>
 * @copyright   Copryright (c) 2012-2017 gyselroth GmbH (https://gyselroth.com)
 * @license     GPLv3 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Filesystem\Node;

use \Balloon\Filesystem\Exception;

class Gridfs
{
    /**
     * Init virtual file and set attributes
     *
     * @param   array $attributes
     * @param   Filesystem $fs
     * @return  void
     */
    public function __construct(Database $db, string $collection='fs')
    {
        $this->gridfs = $db->selectGridFSBucket($collection);
    }


    /**
     * Remove blob from gridfs
     *
     * @param   ObjectId $file_id
     * @return  bool
     */
    protected function _removeBlob(ObjectId $file_id): bool
    {
        try {
            $bucket = $this->_db->selectGridFSBucket();
            $file   = $bucket->findOne(['_id' => $file_id]);

            if ($file) {
                if (!isset($file['metadata'])) {
                    $bucket->delete($file_id);
                    return true;
                }

                $ref  = $file['metadata']['ref'];

                $found = false;
                foreach ($ref as $key => $node) {
                    if ($node['id'] == $this->_id) {
                        unset($ref[$key]);
                    }
                }

                if (count($ref) >= 1) {
                    $this->_logger->debug('gridfs content node ['.$file['_id'].'] still has references left, just remove the reference ['.$this->_id.']', [
                        'category' => get_class($this),
                    ]);

                    $this->_db->{'fs.files'}->updateOne(['_id' => $file_id], [
                        '$set' => ['metadata.ref' => $ref]
                    ]);
                } else {
                    $this->_logger->debug('gridfs content node ['.$file['_id'].'] has no references left, delete node completley', [
                        'category' => get_class($this),
                    ]);

                    $bucket->delete($file_id);
                }
            } else {
                $this->_logger->debug('gridfs content node ['.$file_id.'] was not found, reference=['.$this->_id.']', [
                    'category' => get_class($this),
                ]);
            }

            return true;
        } catch (\Exception $e) {
            $this->_logger->error('failed remove gridfs content node ['.$file_id.']', [
                'category' => get_class($this),
                'exception' => $e,
            ]);

            throw $e;
        }
    }


    /**
     * Write content to storage
     *
     * @param   resource $contents
     * @return  ObjectId
     */
    public function _storeFile(File $file): ObjectId
    {
        $file = [
            'ref' => [
                [
                    'id'    => $file->getId(),
                    'owner' => $file->getOwner()
                ]
            ],
        ];

        if ($file->isShareMember()) {
            $file['share_ref'] = [[
                'id'    => $file->getId(),
                'share' => $file->getShared()
            ]];
        } else {
            $file['share_ref'] = [];
        }

        $exists = $thid->gridfs->findOne(['md5' => $file->getHash()]);

        if ($exists) {
            $ref = $exists['metadata']['ref'];
            $set = [];

            $found = false;
            foreach ($ref as $node) {
                if ($node['id'] == (string)$this->_id) {
                    $found = true;
                }
            }

            if ($found === false) {
                $ref[] = [
                    'id'    => $this->_id,
                    'owner' => $this->owner
                ];
            }
            $set['metadata']['ref'] = $ref;

            $share_ref = $exists['metadata']['share_ref'];
            $found = false;
            foreach ((array)$share_ref as $node) {
                if ($node['id'] == (string)$this->_id) {
                    $found = true;
                }
            }

            if ($found === false && $this->isShareMember()) {
                $share_ref[] = [
                    'id'    => $this->_id,
                    'share' => $this->shared
                ];
            }

            $set['metadata']['share_ref'] = $share_ref;
            $this->_db->{'fs.files'}->updateOne(['md5' => $this->hash], [
                '$set' => $set
            ]);

            $this->_logger->info('gridfs content node with hash ['.$this->hash.'] does already exists, add new file reference for ['.$this->_id.']', [
                'category' => get_class($this),
            ]);

            return $exists['_id'];
        } else {
            set_time_limit((int)($this->size / 15339168));
            $id     = new \MongoDB\BSON\ObjectId();

            //somehow mongo-connector does not catch metadata when set during uploadFromStream()
            $stream = $bucket->uploadFromStream($id, $contents, ['_id' => $id/*, 'metadata' => $file*/]);
            $this->_db->{'fs.files'}->updateOne(['_id' => $id], [
              '$set' => ['metadata'=> $file]
            ]);

            $this->_logger->info('added new gridfs content node ['.$id.'] for file ['.$this->_id.']', [
                'category' => get_class($this),
            ]);

            return $id;
        }
    }
}
