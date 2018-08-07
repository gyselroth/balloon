<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2018 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Hook;

use Balloon\Async\SmbListener;
use Balloon\Async\SmbScanner;
use Balloon\Filesystem\Node\Collection;
use Balloon\Filesystem\Storage\Factory as StorageFactory;
use Balloon\Server;
use TaskScheduler\Async;

class ExternalStorage extends AbstractHook
{
    /**
     * Server.
     *
     * @var Server
     */
    protected $server;

    /**
     * Async.
     *
     * @var Async
     */
    protected $async;

    /**
     * Storage factory.
     *
     * @var StorageFactory
     */
    protected $factory;

    /**
     * Constructor.
     */
    public function __construct(Server $server, Async $async, StorageFactory $factory)
    {
        $this->server = $server;
        $this->async = $async;
        $this->factory = $factory;
    }

    /**
     * {@inheritdoc}
     */
    public function preCreateCollection(Collection $parent, string &$name, array &$attributes, bool $clone): void
    {
        if (!isset($attributes['mount'])) {
            return;
        }

        $adapter = $this->factory->build($attributes['mount']);
    }

    /**
     * {@inheritdoc}
     */
    public function postCreateCollection(Collection $parent, Collection $node, bool $clone): void
    {
        if (count($node->getAttributes()['mount']) === 0) {
            return;
        }

        $this->async->addJob(SmbListener::class, $node->getId());
        $this->async->addJob(SmbScanner::class, $node->getId());

        throw new Exception('s');
    }

    /**
     * {@inheritdoc}
     */
    public function preExecuteAsyncJobs(): void
    {
        $fs = $this->server->getFilesystem();
        $nodes = $fs->findNodesByFilter([
            'mount' => ['$type' => 3],
        ]);

        foreach ($nodes as $node) {
            //$this->async->addJobOnce(SmbListener::class, $node->getId());
            //$this->async->addJobOnce(SmbScanner::class, $node->getId());
        }
    }
}
