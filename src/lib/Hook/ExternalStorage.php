<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2018 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Hook;

use Balloon\Filesystem\Node\Collection;
use Balloon\Filesystem\Storage\Factory as StorageFactory;
use Balloon\Scheduler\SmbListener;
use Balloon\Scheduler\SmbScanner;
use Balloon\Server;
use TaskScheduler\Scheduler;

class ExternalStorage extends AbstractHook
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
     * Storage factory.
     *
     * @var StorageFactory
     */
    protected $factory;

    /**
     * Constructor.
     */
    public function __construct(Server $server, Scheduler $scheduler, StorageFactory $factory)
    {
        $this->server = $server;
        $this->scheduler = $scheduler;
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

        $this->scheduler->addJob(SmbListener::class, $node->getId());
        $this->scheduler->addJob(SmbScanner::class, $node->getId());

        throw new Exception('s');
    }

    /**
     * {@inheritdoc}
     */
    public function preExecuteSchedulerJobs(): void
    {
        $fs = $this->server->getFilesystem();
        $nodes = $fs->findNodesByFilter([
            'mount' => ['$type' => 3],
        ]);

        foreach ($nodes as $node) {
            //$this->scheduler->addJobOnce(SmbListener::class, $node->getId());
            //$this->scheduler->addJobOnce(SmbScanner::class, $node->getId());
        }
    }
}
