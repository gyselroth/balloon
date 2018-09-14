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
use MongoDB\BSON\ObjectId;
use TaskScheduler\JobInterface;
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

        $this->factory->build($attributes['mount'])->test();
    }

    /**
     * {@inheritdoc}
     */
    public function postCreateCollection(Collection $parent, Collection $node, bool $clone): void
    {
        if (!$node->isMounted()) {
            return;
        }

        $this->addTasks($node->getId());
    }

    /**
     * {@inheritdoc}
     */
    public function preDeleteCollection(Collection $node, bool $force, ?string $recursion, bool $recursion_first): void
    {
        if ($node->isMounted() && $force === true) {
            throw new \Exception('mounted collection can not get removed');
        }
    }

    /**
     * {@inheritdoc}
     */
    public function postDeleteCollection(Collection $node, bool $force, ?string $recursion, bool $recursion_first): void
    {
        if (!$node->isMounted()) {
            return;
        }

        foreach ($this->scheduler->getJobs([
            '$or' => [['class' => SmbScanner::class], ['class' => SmbListener::class]],
            'data.id' => $node->getId(),
            'status' => ['$lte' => JobInterface::STATUS_PROCESSING],
        ]) as $job) {
            $this->scheduler->cancelJob($job->getId());
        }
    }

    /**
     * {@inheritdoc}
     */
    public function preExecuteAsyncJobs(): void
    {
        $fs = $this->server->getFilesystem();

        foreach ($this->scheduler->getJobs([
            '$or' => [['class' => SmbScanner::class], ['class' => SmbListener::class]],
            'status' => ['$lte' => JobInterface::STATUS_PROCESSING],
        ]) as $job) {
            if ($fs->findNodeById($job->getData()['id'])->isDeleted()) {
                $this->scheduler->cancelJob($job->getId());
            }
        }

        $nodes = $fs->findNodesByFilter([
            'deleted' => false,
            'directory' => true,
            'mount' => ['$type' => 3],
        ]);

        foreach ($nodes as $node) {
            $this->addTasks($node->getId());
        }
    }

    /**
     * Add tasks.
     */
    protected function addTasks(ObjectId $node): bool
    {
        $this->scheduler->addJobOnce(SmbScanner::class, [
            'id' => $node,
        ], [
            Scheduler::OPTION_INTERVAL => 86400,
        ]);

        $job = $this->scheduler->addJobOnce(SmbListener::class, [
            'id' => $node,
        ], [
            Scheduler::OPTION_IGNORE_MAX_CHILDREN => true,
            Scheduler::OPTION_RETRY => -1,
        ]);

        return true;
    }
}
