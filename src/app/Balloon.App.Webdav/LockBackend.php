<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copyright (c) 2012-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\Webdav;

use Balloon\Filesystem;
use Balloon\Filesystem\Exception;
use Balloon\Server;
use Sabre\DAV\Locks\Backend\BackendInterface;
use Sabre\DAV\Locks\LockInfo;

class LockBackend implements BackendInterface
{
    /**
     * Filesystem.
     *
     * @var Filesystem
     */
    protected $fs;

    /**
     * Constructor.
     */
    public function __construct(Server $server)
    {
        $this->fs = $server->getFilesystem();
    }

    /**
     * {@inheritdoc}
     */
    public function getLocks($uri, $returnChildLocks)
    {
        try {
            $node = $this->fs->findNodeByPath($uri);
        } catch (Exception\NotFound $e) {
            return [];
        }

        $locks = [];
        $nodes = $node->getParents();
        array_unshift($nodes, $node);

        foreach ($nodes as $node) {
            if (!$node->isLocked()) {
                continue;
            }

            $lock = $node->getLock();
            $info = new LockInfo();
            $info->owner = $lock['client'] ?? null;
            $info->token = $lock['id'];
            $info->timeout = $lock['expire']->toDateTime()->format('U') - time();
            $info->created = $lock['created']->toDateTime()->format('U');
            $info->uri = $node->getPath();

            $locks[] = $info;
        }

        return $locks;
    }

    /**
     * {@inheritdoc}
     */
    public function lock($uri, LockInfo $lock)
    {
        $node = $this->fs->findNodeByPath($uri);
        $node->lock($lock->token, $lock->timeout, $lock->owner);
    }

    /**
     * {@inheritdoc}
     */
    public function unlock($uri, LockInfo $lock)
    {
        $node = $this->fs->findNodeByPath($uri);
        $node->unlock($lock->token, $lock->owner);
    }
}
