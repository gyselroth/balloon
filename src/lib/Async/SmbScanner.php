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
use Balloon\Filesystem\Storage\Adapter\Smb;
use Balloon\Server;
use Balloon\Server\User;
use Icewind\SMB\IShare;
use Icewind\SMB\Native\NativeFileInfo;
use MongoDB\BSON\UTCDateTime;
use Psr\Log\LoggerInterface;
use TaskScheduler\AbstractJob;

class SmbScanner extends AbstractJob
{
    /**
     * Server.
     *
     * @var Server
     */
    protected $server;

    /**
     * Logger.
     *
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * Constructor.
     */
    public function __construct(Server $server, LoggerInterface $logger)
    {
        $this->server = $server;
        $this->logger = $logger;
    }

    /**
     * {@inheritdoc}
     */
    public function start(): bool
    {
        $dummy = new Blackhole();
        $fs = $this->server->getFilesystem();
        $mount = $collection = $fs->findNodeById($this->data['id']);
        $user = $this->server->getUserById($collection->getOwner());
        $user_fs = $user->getFilesystem();
        $smb = $collection->getStorage();
        $share = $smb->getShare();

        $collection
            ->setFilesystem($user_fs)
            ->setStorage($dummy);

        $path = $smb->getRoot();
        if (isset($this->data['path'])) {
            $path = $this->data['path'];
        }

        if ($path === '') {
            $path = DIRECTORY_SEPARATOR;
        }

        if ($path !== DIRECTORY_SEPARATOR) {
            $collection = $this->getParent($collection, $path);
            $collection->setStorage($dummy);
        }

        $recursive = true;
        if (isset($this->data['recursive'])) {
            $recursive = $this->data['recursive'];
        }

        $this->recursiveIterator($collection, $mount, $share, $dummy, $user, $smb, $path, $recursive);

        return true;
    }

    /**
     * Get parent node from sub path.
     */
    protected function getParent(Collection $parent, string $path): Collection
    {
        $nodes = explode(DIRECTORY_SEPARATOR, $path);
        foreach ($nodes as $child) {
            $parent = $parent->getChild($child);
        }

        return $parent;
    }

    /**
     * Iterate recursively through smb share.
     */
    protected function recursiveIterator(Collection $parent, Collection $mount, IShare $share, Blackhole $dummy, User $user, Smb $smb, string $path = '/', bool $recursive = true): void
    {
        $this->logger->debug('sync smb collection path ['.$path.']', [
            'category' => get_class($this),
        ]);

        $nodes = [];
        $system_path = $path.DIRECTORY_SEPARATOR.$smb->getSystemFolder();

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
            if ($node->getPath() === $system_path) {
                continue;
            }

            $attributes = $this->getAttributes($mount, $share, $node);

            if ($node->isDirectory()) {
                if (!$parent->childExists($node->getName())) {
                    $child = $parent->addDirectory($node->getName(), $attributes);
                } else {
                    $child = $parent->getChild($node->getName());
                }

                $child->setStorage($dummy);

                if ($recursive === true) {
                    $this->recursiveIterator($child, $mount, $share, $dummy, $user, $smb, $path.$node->getName().DIRECTORY_SEPARATOR, $recursive);
                }
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

        fclose($stream);

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
