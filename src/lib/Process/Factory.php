<?php

declare(strict_types=1);

/**
 * tubee
 *
 * @copyright   Copryright (c) 2017-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Process;

use Generator;
use MongoDB\BSON\ObjectIdInterface;
use MongoDB\Database;
use TaskScheduler\Process;
use TaskScheduler\Scheduler;
use Balloon\Async\Sync;
use Balloon\User\UserInterface;
use Balloon\Process as ProcessWrapper;
use Balloon\Resource\Factory as ResourceFactory;

class Factory
{
    /**
     * Database.
     *
     * @var Database
     */
    protected $db;

    /**
     * Resource factory.
     *
     * @var ResourceFactory
     */
    protected $resource_factory;

    /**
     * Job scheduler.
     *
     * @var Scheduler
     */
    protected $scheduler;

    /**
     * Initialize.
     */
    public function __construct(Database $db, ResourceFactory $resource_factory, Scheduler $scheduler)
    {
        $this->db = $db;
        $this->resource_factory = $resource_factory;
        $this->scheduler = $scheduler;
    }

    /**
     * Get jobs.
     */
    public function getAll(UserInterface $user, ?array $query = null, ?int $offset = null, ?int $limit = null, ?array $sort = null): Generator
    {
        $filter = $this->prepareQuery($user, $query);
        $that = $this;

        return $this->resource_factory->getAllFrom($this->db->{$this->scheduler->getJobQueue()}, $filter, $offset, $limit, $sort, function (array $resource) use ($user, $that) {
            return $that->build($resource, $user);
        });
    }


    /**
     * Delete by name.
     */
    public function deleteOne(UserInterface $user, ObjectIdInterface $id): bool
    {
        $cursor = $this->db->{$this->scheduler->getJobQueue()}->find([
            '$or' => [
                ['_id' => $id],
                ['data.parent' => $id],
            ],
        ]);

        foreach ($cursor as $process) {
            $this->scheduler->cancelJob($process['_id']);
            if (isset($process['data']['parent']) && $process['options']['interval'] !== 0) {
                $this->scheduler->addJob(Sync::class, $process['data'], $process['options']);
            }
        }

        return true;
    }

    /**
     * Get job.
     */
    public function getOne(UserInterface $user, ObjectIdInterface $id): ProcessInterface
    {
        $result = $this->scheduler->getJob($id);

        return $this->build($result->toArray(), $user);
    }

    /**
     * Change stream.
     */
    public function watch(UserInterface $user, ?ObjectIdInterface $after = null, bool $existing = true, ?array $query = null, ?int $offset = null, ?int $limit = null, ?array $sort = null): Generator
    {
        $filter = $this->prepareQuery($user, $query);
        $that = $this;

        return $this->resource_factory->watchFrom($this->db->{$this->scheduler->getJobQueue()}, $after, $existing, $filter, function (array $resource) use ($user, $that) {
            return $that->build($resource, $user);
        }, $offset, $limit, $sort);
    }

    /**
     * Wrap process.
     */
    public function build(array $process, UserInterface $user): ProcessInterface
    {
        return $this->resource_factory->initResource(new ProcessWrapper($process, $user));
    }

    /**
     * Prepare query.
     */
    protected function prepareQuery(UserInterface $user, ?array $query = null): array
    {
        $filter = [
            'status' => ['$exists' => true],
            'data.owner' => $user->getId(),
            'class' => ['$ne' => 'dummy'],
        ];

        if (!empty($query)) {
            $filter = [
                '$and' => [$filter, $query],
            ];
        }

        return $filter;
    }
}
