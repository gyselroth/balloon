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
use Exception;
use Icewind\SMB\IFileInfo;
use Icewind\SMB\INotifyHandler;
use Icewind\SMB\IShare;
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
        $action = INotifyHandler::NOTIFY_ADDED;

        if (isset($this->data['action'])) {
            $action = $this->data['action'];
        }

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
            $collection = $this->getParent($collection, $share, $path, $action);
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
    protected function getParent(Collection $mount, IShare $share, string $path, int $action): Collection
    {
        $parent = $mount;
        $nodes = explode(DIRECTORY_SEPARATOR, $path);
        $sub = '';
        foreach ($nodes as $child) {
            try {
                $dummy = $parent->getStorage();
                $sub .= DIRECTORY_SEPARATOR.$child;
                $parent = $parent->getChild($child);
                $parent->setStorage($dummy);
            } catch (Exception $e) {
                if ($action === INotifyHandler::NOTIFY_REMOVED) {
                    throw $e;
                }
                $this->logger->debug('child node ['.$child.'] does not exits, add folder', [
                        'category' => get_class($this),
                        'exception' => $e,
                    ]);

                $node = $share->stat($sub);
                $parent = $parent->addDirectory($child, $this->getAttributes($mount, $share, $node));
            }
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

        if ($path === $system_path) {
            return;
        }

        $node = $share->stat($path);
        if ($node->isDirectory()) {
            /* if (!$parent->childExists($node->getName())) {
                 $child = $parent->addDirectory($node->getName(), $attributes);
             } else {
                 $child = $parent->getChild($node->getName());
             }*/

            $parent->setStorage($dummy);
            if ($recursive === true) {
                foreach ($share->dir($path) as $node) {
                    $nodes[] = $node->getName();
                }

                foreach ($parent->getChildren() as $child) {
                    if (!in_array($child->getName(), $nodes)) {
                        $child->delete(true);
                    }
                }

                foreach ($share->dir($path) as $node) {
                    if ($node->getPath() === $system_path) {
                        continue;
                    }

                    $this->recursiveIterator($child, $mount, $share, $dummy, $user, $smb, $path.$node->getName().DIRECTORY_SEPARATOR, $recursive);
                }
            }
        } else {
            $this->syncFile($parent, $node, $action, $share, $user);
        }
    }

    /**
     * Sync file.
     */
    protected function syncFile(Collection $parent, IFileInfo $node, int $action, IShare $share, User $user): bool
    {
        if ($parent->childExists($node->getName())) {
            $file = $parent->getChild($node->getName());

            if ($action === INotifyHandler::NOTIFY_DELETE) {
                $file->delete(true);

                return true;
            }

            $file = $parent->getChild($node->getName());

            if ($this->fileUpdateRequired($file, $attributes)) {
                $this->updateFileContent($parent, $share, $node, $file, $user, $attributes);
            }
        } elseif ($action !== INotifyHandler::NOTIFY_DELETE) {
            $file = $parent->addFile($node->getName(), null, $attributes);
            $this->updateFileContent($parent, $share, $node, $file, $user, $attributes);
        }

        return true;
    }

    /**
     * Set file content.
     */
    protected function updateFileContent(Collection $parent, IShare $share, IFileInfo $node, File $file, User $user, array $attributes): bool
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
    protected function getAttributes(Collection $collection, IShare $share, IFileInfo $node): array
    {
        $stats = $share->getStat($node->getPath());
        $ctime = new UTCDateTime($stats['ctime'] * 1000);
        $mtime = new UTCDateTime($stats['mtime'] * 1000);

        return [
            'created' => $ctime,
            'changed' => $mtime,
            'storage_reference' => $collection->getId(),
            'storage' => [
                'path' => $node->getPath(),
            ],
        ];
    }
}
