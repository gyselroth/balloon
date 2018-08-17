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
use Balloon\Filesystem\Storage\Adapter\Blackhole;
use Balloon\Server;
use Icewind\SMB\IShare;
use Icewind\SMB\NativeFileInfo;
use Icewind\SMB\Change;
use Icewind\SMB\INotifyHandler;
use MongoDB\BSON\UTCDateTime;
use MongoDB\BSON\ObjectId;
use TaskScheduler\AbstractJob;
use TaskScheduler\Scheduler;
use Psr\Log\LoggerInterface;
use Balloon\Filesystem\Storage\Adapter\Smb;

class SmbListener extends AbstractJob
{
    /**
     * Server
     *
     * @var Server
     */
    protected $server;

    /**
     * Scheduler
     *
     * @var Scheduler
     */
    protected $scheduler;

    /**
     * Logger
     *
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * Constructor.
     */
    public function __construct(Server $server, Scheduler $scheduler, LoggerInterface $logger)
    {
        $this->server = $server;
        $this->scheduler = $scheduler;
        $this->logger = $logger;
    }

    /**
     * {@inheritdoc}
     */
    public function start(): bool
    {
        $dummy = new Blackhole();
        $fs = $this->server->getFilesystem();
        $collection = $fs->findNodeById($this->data['id']);
        $user_fs = $this->server->getUserById($collection->getOwner())->getFilesystem();
        $smb = $collection->getStorage();
        $share = $smb->getShare();

        $collection
            ->setFilesystem($user_fs)
            ->setStorage($dummy);

        $this->notify($collection, $share, $dummy, $smb);

        return true;
    }

    /**
     * Bind to smb changes
     */
    protected function notify(Collection $mount, IShare $share, Blackhole $dummy, Smb $smb): void
    {
        $rename_from = null;
        $last = null;
        $logger = $this->logger;
        $root = $smb->getRoot();

        $share->notify('')->listen(function (Change $change) use ($logger, $root, $share, $mount, $last, $rename_from) {
            $logger->debug('smb mount ['.$mount->getId().'] notify event in ['.$change->getCode().'] for path ['.$change->getPath().']', [
                'category' => get_class($this)
            ]);

            if(substr($change->getPath(), 0, strlen($root)) !== $root) {
                $logger->debug('skip smb event ['.$change->getPath().'], path is not part of root ['.$root.']', [
                    'category' => get_class($this)
                ]);

                return;
            }

            if($change->getCode() === INotifyHandler::NOTIFY_RENAMED_OLD) {
                $rename_from = $change->getPath();
            } elseif($change->getCode() === INotifyHandler::NOTIFY_RENAMED_NEW && $last === INotifyHandler::NOTIFY_RENAMED_OLD) {
                $this->moveNode($rename_from, $change->getPath());
                $rename_from = null;
            } else {
                $this->syncNode($mount, $change->getPath());
            }

            $last = $change->getCode();
        });
    }

    /**
     * Move node
     */
    protected function moveNode(Collection $mount, string $from, string $to): bool
    {
        return true;
    }

    /**
     * Add sync task
     */
    protected function syncNode(Collection $mount, string $path): ObjectId
    {
        $path = dirname($path);
        if($path === DIRECTORY_SEPARATOR || $path === '.') {
            $path = '';
        }

        return $this->scheduler->addJob(SmbScanner::class, [
            'id' => $mount->getId(),
            'path' => $path,
            'recursive' => false,
        ]);
    }
}
