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
use MongoDB\BSON\UTCDateTime;
use TaskScheduler\AbstractJob;

class SmbListener extends AbstractJob
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
        $user_fs = $this->server->getUserById($collection->getOwner())->getFilesystem();
        $share = $collection->getStorage()->getShare();

        $collection
            ->setFilesystem($user_fs)
            ->setStorage($dummy);

        $this->notify($collection, $collection, $share, $dummy);

        return true;
    }

    /**
     * Iterate recursively through smb share.
     */
    protected function notify(Collection $parent, Collection $external, IShare $share, Blackhole $dummy): void
    {
        $share->notify('')->listen(function (\Icewind\SMB\Change $change) use ($share) {
            echo $change->getCode().': '.$change->getPath()."\n";
        });
    }

    /*protected function getAttributes(Collection $collection, IShare $share, NativeFileInfo $node): array
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
                'ino' => $stats['ino'],
            ]
        ];
    }*/
}
