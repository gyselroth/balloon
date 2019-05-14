<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\Webdav;

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
        $node = $this->fs->findNodeByPath($uri);

        if (!$node->isLocked()) {
            return [];
        }

        $lock = $node->getLock();
        $info = new LockInfo();
        $info->owner = (string) $lock['owner'];
        $info->token = $lock['id'];
        $info->timeout = $lock['expire']->toDateTime()->format('U') - time();
        $info->created = $lock['created']->toDateTime()->format('U');
        $info->uri = $uri;

        return [$info];
    }

    /**
     * {@inheritdoc}
     */
    public function lock($uri, LockInfo $lock)
    {
        $node = $this->fs->findNodeByPath($uri);
        $node->lock($lock->token, $lock->timeout);
    }

    /**
     * {@inheritdoc}
     */
    public function unlock($uri, LockInfo $lock)
    {
        $node = $this->fs->findNodeByPath($uri);
        $node->unlock($lock->token);
    }
}
