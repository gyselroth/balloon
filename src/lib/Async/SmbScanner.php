<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2018 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Async;

use Balloon\Filesystem\Node\Collection;
use Balloon\Filesystem\Node\File;
use Balloon\Filesystem\Storage\Adapter\Blackhole;
use Balloon\Server;
use Balloon\Server\User;
use Icewind\SMB\IShare;
use Icewind\SMB\Native\NativeFileInfo;
use MongoDB\BSON\UTCDateTime;
use TaskScheduler\AbstractJob;

class SmbScanner extends AbstractJob
{
    /**
     * Constructor.
     */
    public function __construct(Server $server)
    {
        $this->server = $server;
    }

    /**
     * {@inheritdoc}
     */
    public function start(): bool
    {
        $dummy = new Blackhole();
        $fs = $this->server->getFilesystem();
        $collection = $fs->findNodeById($this->data);
        $user = $this->server->getUserById($collection->getOwner());
        $user_fs = $user->getFilesystem();
        $share = $collection->getStorage()->getShare();

        $collection
            ->setFilesystem($user_fs)
            ->setStorage($dummy);

        $this->recursiveIterator($collection, $collection, $share, $dummy, $user);

        return true;
    }

    /**
     * Iterate recursively through smb share.
     */
    protected function recursiveIterator(Collection $parent, Collection $mount, IShare $share, Blackhole $dummy, User $user, string $path = '/'): void
    {
        $nodes = [];
        foreach ($share->dir($path) as $node) {
            $nodes[] = $node->getName();
        }

        foreach ($parent->getChildren() as $child) {
            if (!in_array($child->getName(), $nodes)) {
                $child
                    ->setStorage($dummy)
                    ->delete(true);
            }
        }

        foreach ($share->dir($path) as $node) {
            $attributes = $this->getAttributes($mount, $share, $node);

            if ($node->isDirectory()) {
                if (!$parent->childExists($node->getName())) {
                    $child = $parent->addDirectory($node->getName(), $attributes);
                } else {
                    $child = $parent->getChild($node->getName());
                }

                $child->setStorage($dummy);
                $this->recursiveIterator($child, $mount, $share, $dummy, $user, $path.$node->getName().DIRECTORY_SEPARATOR);
            } else {
                if (!$parent->childExists($node->getName())) {
                    $file = $parent->addFile($node->getName(), null, $attributes)
                        ->setStorage($dummy);

                    $this->updateFileContent($parent, $share, $node, $file, $user, $attributes);
                } else {
                    $file = $parent->getChild($node->getName());

                    if ($this->fileUpdateRequired($file, $attributes)) {
                        $this->updateFileContent($parent, $share, $node, $file, $user, $attributes);
                    }
                }
            }
        }
    }

    /**
     * Set file content.
     */
    protected function updateFileContent(Collection $parent, IShare $share, NativeFileInfo $node, File $file, User $user, array $attributes): bool
    {
        $storage = $parent->getStorage();
        $stream = $share->read($node->getPath());
        $session = $storage->storeTemporaryFile($stream, $user);
        $file->setContent($session, $attributes);

        return true;
    }

    /**
     * Check if file content needs to be updated.
     */
    protected function fileUpdateRequired(File $file, array $smb_attributes): bool
    {
        $meta_attributes = $file->getAttributes();

        return $smb_attributes['size'] != $meta_attributes['size'] && $smb_attributes['changed'] != $meta_attributes['changed'];
    }

    /**
     * Prepare node attributes.
     */
    protected function getAttributes(Collection $collection, IShare $share, NativeFileInfo $node): array
    {
        $stats = $share->getStat($node->getPath());
        $ctime = new UTCDateTime($stats['ctime'] * 1000);
        $mtime = new UTCDateTime($stats['mtime'] * 1000);

        return [
            'created' => $ctime,
            'changed' => $mtime,
            'size' => $node->getSize(),
            'storage_reference' => $collection->getId(),
            'storage' => [
                'path' => $node->getPath(),
                'ino' => $stats['ino'],
            ],
        ];
    }
}
