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
use Icewind\SMB\IFileInfo;
use Icewind\SMB\IShare;
use MongoDB\BSON\ObjectId;
use MongoDB\Database;
use Psr\Log\LoggerInterface;

class Smb extends Gridfs
{
    /**
     * SMB share.
     *
     * @var IShare
     */
    protected $share;

    /**
     * SMB Root directory within share.
     *
     * @var string
     */
    protected $root = '';

    /**
     * SMB storage.
     */
    public function __construct(IShare $share, Database $db, LoggerInterface $logger, $root = '')
    {
        $this->gridfs = $db->selectGridFSBucket();
        $this->db = $db;
        $this->share = $share;
        $this->logger = $logger;
        $this->root = $root;
    }

    /**
     * Get SMB share.
     */
    public function getShare(): IShare
    {
        return $this->share;
    }

    /**
     * {@inheritdoc}
     */
    public function hasNode(NodeInterface $node): bool
    {
        $attributes = $node->getAttributes();

        if (isset($attributes['storage']['path'])) {
            return (bool) $this->share->stat($attributes['storage']['path']);
        }

        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function deleteFile(File $file, ?int $version = null): array
    {
        return $this->deleteNode($file, $version);
    }

    /**
     * {@inheritdoc}
     */
    public function forceDeleteFile(File $file, ?int $version = null): bool
    {
        if (false === $this->hasNode($file)) {
            $this->logger->debug('smb blob for file ['.$file->getId().'] was not found', [
                'category' => get_class($this),
            ]);

            return false;
        }

        return $this->share->del($this->getPath($file));
    }

    /**
     * {@inheritdoc}
     */
    public function openReadStream(File $file)
    {
        return $this->share->read($this->getPath($file));
    }

    /**
     * {@inheritdoc}
     */
    public function storeFile(File $file, ObjectId $session): array
    {
        $path = $this->getPath($file->getParent()).DIRECTORY_SEPARATOR.$file->getName();

        $this->logger->debug('copy file from ['.$session.'] to smb share ['.$path.']', [
            'category' => get_class($this),
        ]);

        $md5 = $this->db->command([
            'filemd5' => $session,
            'root' => 'fs',
        ])->toArray()[0]['md5'];

        $this->logger->debug('calculated hash ['.$md5.'] for temporary file ['.$session.']', [
            'category' => get_class($this),
        ]);

        $to = $this->share->write($path);
        $from = $this->gridfs->openDownloadStream($session);

        $size = stream_copy_to_stream($from, $to);
        fclose($to);
        fclose($from);
        $this->gridfs->delete($session);

        return [
            'reference' => [
                'path' => $path,
                'ino' => null,
            ],
            'size' => $size,
            'hash' => $md5,
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function createCollection(Collection $parent, string $name): array
    {
        $path = $this->getPath($parent).DIRECTORY_SEPARATOR.$name;
        $this->share->mkdir($path);

        return [
            'path' => $path,
            'ino' => null,
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function deleteCollection(Collection $collection): array
    {
        return $this->deleteNode($collection, $version);
    }

    /**
     * {@inheritdoc}
     */
    public function forceDeleteCollection(Collection $collection): bool
    {
        if (false === $this->hasNode($collection)) {
            $this->logger->debug('smb collection ['.$collection->getId().'] was not found', [
                'category' => get_class($this),
            ]);

            return false;
        }

        return $this->share->rmdir($this->getPath($collection));
    }

    /**
     * {@inheritdoc}
     */
    public function rename(NodeInterface $node, string $new_name): array
    {
        $path = dirname($this->getPath($node)).DIRECTORY_SEPARATOR.$new_name;
        $this->share->rename($this->getPath($node), $path);
        $reference = $node->getAttributes()['storage'];
        $reference['path'] = $path;

        return $reference;
    }

    /**
     * {@inheritdoc}
     */
    public function move(NodeInterface $node, Collection $parent): array
    {
        $path = $this->getPath($parent).DIRECTORY_SEPARATOR.$node->getName();
        $this->share->rename($this->getPath($node), $path);
        $reference = $node->getAttributes()['storage'];
        $reference['path'] = $path;

        return $reference;
    }

    /**
     * {@inheritdoc}
     */
    public function undelete(NodeInterface $node): array
    {
        if (null === $this->hasNode($node)) {
            $this->logger->debug('smb node ['.$this->getPath($node).'] was not found for reference=['.$node->getId().']', [
                'category' => get_class($this),
            ]);

            return $node->getAttributes()['storage'];
        }

        $this->share->setMode($this->getPath($node), IFileInfo::MODE_NORMAL);

        return $this->rename($node, $node->getName());
    }

    /**
     * {@inheritdoc}
     */
    protected function deleteNode(NodeInterface $node, ?int $version = null): array
    {
        if (null === $this->hasNode($node)) {
            $this->logger->debug('smb node ['.$this->getPath($node).'] was not found for reference=['.$node->getId().']', [
                'category' => get_class($this),
            ]);

            return false;
        }

        $this->share->setMode($this->getPath($node), IFileInfo::MODE_HIDDEN);

        return $this->rename($node, '.'.$node->getId());
    }

    /**
     * Get SMB path from node.
     */
    protected function getPath(NodeInterface $node): string
    {
        $attributes = $node->getAttributes();

        if (isset($attributes['mount']) && count($attributes['mount']) !== 0) {
            return $this->root;
        }

        if (!isset($attributes['storage']['path'])) {
            throw new Exception\BlobNotFound('no storage.path given for smb storage definiton');
        }

        return $attributes['storage']['path'];
    }
}
