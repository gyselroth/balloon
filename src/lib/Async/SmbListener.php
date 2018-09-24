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
use Balloon\Filesystem\Node\NodeInterface;
use Balloon\Filesystem\Storage\Adapter\Blackhole;
use Balloon\Filesystem\Storage\Adapter\Smb;
use Balloon\Helper;
use Balloon\Server;
use Icewind\SMB\INotifyHandler;
use Icewind\SMB\IShare;
use Psr\Log\LoggerInterface;
use TaskScheduler\AbstractJob;
use TaskScheduler\Process;
use TaskScheduler\Scheduler;

class SmbListener extends AbstractJob
{
    /**
     * Server.
     *
     * @var Server
     */
    protected $server;

    /**
     * Scheduler.
     *
     * @var Scheduler
     */
    protected $scheduler;

    /**
     * Logger.
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
     * Bind to smb changes.
     */
    protected function notify(Collection $mount, IShare $share, Blackhole $dummy, Smb $smb): void
    {
        $last = null;
        $that = $this;
        $logger = $this->logger;
        $root = $smb->getRoot();
        $system = ($root === '') ? $smb->getSystemFolder() : $root.DIRECTORY_SEPARATOR.$smb->getSystemFolder();

        $notify = $share->notify('');

        while (true) {
            //pcntl_signal_dispatch();

            foreach ($notify->getChanges() as $change) {
                $logger->debug('smb mount ['.$mount->getId().'] notify event in ['.$change->getCode().'] for path ['.$change->getPath().']', [
                    'category' => get_class($this),
                ]);

                if (substr($change->getPath(), 0, strlen($root)) !== $root) {
                    $logger->debug('skip smb event ['.$change->getPath().'], path is not part of root ['.$root.']', [
                        'category' => get_class($this),
                    ]);

                    return;
                }
                if (substr($change->getPath(), 0, strlen($system)) === $system) {
                    $logger->debug('skip smb event ['.$change->getPath().'], path is part of balloon system folder ['.$system.']', [
                        'category' => get_class($this),
                    ]);

                    return;
                }

                if ($change->getCode() === INotifyHandler::NOTIFY_RENAMED_OLD) {
                    //do nothing
                } elseif ($change->getCode() === INotifyHandler::NOTIFY_RENAMED_NEW && $last->getCode() === INotifyHandler::NOTIFY_RENAMED_OLD) {
                    $this->renameNode($mount, $dummy, $last->getPath(), $change->getPath());
                } else {
                    $this->syncNode($mount, $change->getPath(), $change->getCode());
                }

                $last = $change;
            }
        }
    }

    /**
     * Get parent node from sub path.
     */
    protected function getNode(Collection $mount, string $path): NodeInterface
    {
        $nodes = explode(DIRECTORY_SEPARATOR, $path);
        foreach ($nodes as $child) {
            $mount = $mount->getChild($child);
        }

        return $mount;
    }

    /**
     * Rename node.
     */
    protected function renameNode(Collection $mount, Blackhole $dummy, string $from, string $to): bool
    {
        try {
            $this->logger->debug('rename smb node from ['.$from.'] to ['.$to.'] in mount ['.$mount->getId().']', [
                'category' => get_class($this),
            ]);

            $node = $this->getNode($mount, $from);
            $node->getParent()->setStorage($dummy);

            if (Helper::mb_basename($from) !== Helper::mb_basename($to)) {
                $node->setName(Helper::mb_basename($to));
            }

            return true;
        } catch (\Exception $e) {
            $this->logger->error('failed to handle smb rename event from ['.$from.'] to ['.$to.'] in mount ['.$mount->getId().']', [
                'category' => get_class($this),
                'exception' => $e,
            ]);
        }

        return false;
    }

    /**
     * Add sync task.
     */
    protected function syncNode(Collection $mount, string $path, int $action): Process
    {
        $this->logger->debug('add new smb sync job for path ['.$path.'] in mount ['.$mount->getId().']', [
            'category' => get_class($this),
        ]);

        return $this->scheduler->addJob(SmbScanner::class, [
            'id' => $mount->getId(),
            'path' => $path,
            'action' => $action,
            'recursive' => true,
        ]);
    }
}
