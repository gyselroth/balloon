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
     * SMB storage.
     */
    public function __construct(IShare $share, Database $db, LoggerInterface $logger)
    {
        $this->gridfs = $db->selectGridFSBucket();
        $this->db = $db;
        $this->share = $share;
        $this->logger = $logger;
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
        return (bool) $this->share->stat($attributes['path']);
    }

    /**
     * {@inheritdoc}
     */
    public function deleteFile(File $file, ?int $version = null): bool
    {
        return $this->deleteNode($file, $version);
    }

    /**
     * {@inheritdoc}
     */
    public function forceDeleteFile(File $file, ?int $version = null): bool
    {
        if (null === $this->hasNode($file)) {
            $this->logger->debug('smb blob ['.$this->getPath($file).'] was not found for file reference=['.$file->getId().']', [
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
        return [
            'path' => $path,
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
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function deleteCollection(Collection $collection): bool
    {
        return $this->deleteNode($collection, $version);
    }

    /**
     * {@inheritdoc}
     */
    public function forceDeleteCollection(Collection $collection): bool
    {
        if (null === $this->hasNode($file)) {
            $this->logger->debug('smb collection ['.$this->getPath($file).'] was not found for collection reference ['.$file->getId().']', [
                'category' => get_class($this),
            ]);

            return false;
        }

        return $this->share->rmdir($this->getPath($collection));
    }

    /**
     * {@inheritdoc}
     */
    public function rename(NodeInterface $node, string $new_name): bool
    {
        return $this->share->rename($this->getPath($node), $new_name);
    }

    /**
     * {@inheritdoc}
     */
    protected function deleteNode(NodeInterface $node, ?int $version = null): bool
    {
        if (null === $this->hasNode($file)) {
            $this->logger->debug('smb node ['.$this->getPath($file).'] was not found for reference=['.$node->getId().']', [
                'category' => get_class($this),
            ]);

            return false;
        }

        return $this->share->setMode($this->getPath($node), IFileInfo::MODE_HIDDEN);
    }

    /**
     * Get SMB path from node.
     */
    protected function getPath(NodeInterface $node): string
    {
        $attributes = $node->getAttributes();

        if (isset($attributes['mount']) && count($attributes['mount']) !== 0) {
            return DIRECTORY_SEPARATOR;
        }

        if (!isset($attributes['storage']['path'])) {
            throw new Exception\BlobNotFound('no storage.path given for smb storage definiton');
        }

        return $attributes['storage']['path'];
    }
}
