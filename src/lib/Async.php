<?php

declare(strict_types=1);

/**
 * Balloon
 *
 * @author      Raffael Sahli <sahli@gyselroth.net>
 * @copyright   Copryright (c) 2012-2017 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon;

use Balloon\Async\JobInterface;
use IteratorIterator;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Database;
use MongoDB\Driver\Cursor;
use MongoDB\Operation\Find;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

class Async
{
    /**
     * Job status.
     */
    const STATUS_WAITING = 0;
    const STATUS_POSTPONED = 1;
    const STATUS_PROCESSING = 2;
    const STATUS_DONE = 3;
    const STATUS_FAILED = 4;

    /**
     * Database.
     *
     * @var Database
     */
    protected $db;

    /**
     * LoggerInterface.
     *
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * Local queue.
     *
     * @var array
     */
    protected $queue = [];

    /**
     * Node name.
     *
     * @var string
     */
    protected $node_name;

    /**
     * Collection name.
     *
     * @var string
     */
    protected $collection_name = 'queue';

    /**
     * Container.
     *
     * @var ContainerInterface
     */
    protected $container;

    /**
     * Init queue.
     *
     * @param Database           $db
     * @param LoggerInterface    $logger
     * @param ContainerInterface $container
     * @param iterable           $config
     */
    public function __construct(Database $db, LoggerInterface $logger, ?ContainerInterface $container, ?Iterable $config = null)
    {
        $this->db = $db;
        $this->logger = $logger;
        $this->container = $container;
        $this->node_name = gethostname();
        $this->setOptions($config);
    }

    /**
     * Set options.
     *
     * @param iterable $config
     *
     * @return Async
     */
    public function setOptions(? Iterable $config = null): self
    {
        if (null === $config) {
            return $this;
        }

        foreach ($config as $option => $value) {
            switch ($option) {
                case 'node_name':
                case 'collection_name':
                    $this->{$option} = (string) $value;

                break;
                default:
                    throw new Exception('invalid option '.$option.' given');
            }
        }

        return $this;
    }

    /**
     * Add job to queue.
     *
     * @param string      $class
     * @param array       $data
     * @param UTCDateTime $at
     *
     * @return bool
     */
    public function addJob(string $class, array $data, array $options = []): bool
    {
        $defaults = [
            'at' => null,
            'interval' => -1,
            'retry' => 0,
        ];

        $options = array_merge($defaults, $options);

        $result = $this->db->{$this->collection_name}->insertOne([
            'class' => $class,
            'status' => self::STATUS_WAITING,
            'timestamp' => new UTCDateTime(),
            'at' => $options['at'],
            'retry' => $options['retry'],
            'interval' => $options['interval'],
            'node' => $this->node_name,
            'data' => $data,
        ]);

        $this->logger->debug('queue job ['.$result->getInsertedId().'] added to ['.$class.']', [
            'category' => get_class($this),
            'params' => $data,
        ]);

        return $result->isAcknowledged();
    }

    /**
     * Only add job if not in queue yet.
     *
     * @param string $class
     * @param array  $data
     * @param array  $options
     *
     * @return bool
     */
    public function addJobOnce(string $class, array $data, array $options = []): bool
    {
        $filter = [
            'class' => $class,
            'data' => $data,
            '$or' => [
                ['status' => self::STATUS_WAITING],
                ['status' => self::STATUS_POSTPONED],
            ],
        ];

        $result = $this->db->queue->findOne($filter);

        if (null === $result) {
            return $this->addJob($class, $data, $options);
        }
        $this->logger->debug('queue job ['.$result['_id'].'] of type ['.$class.'] already exists', [
                'category' => get_class($this),
                'params' => $data,
            ]);

        return true;
    }

    /**
     * Execute job queue as endless loop.
     *
     * @return bool
     */
    public function startDaemon(): bool
    {
        $cursor = $this->getCursor();

        while (true) {
            $this->processLocalQueue();

            if (null === $cursor->current()) {
                if ($cursor->getInnerIterator()->isDead()) {
                    $this->logger->error('job queue cursor is dead, is it a capped collection?', [
                        'category' => get_class($this),
                    ]);

                    return $this->startDaemon();
                }

                $cursor->next();

                continue;
            }

            $job = $cursor->current();
            $cursor->next();
            $this->processJob($job);
        }
    }

    /**
     * Execute job queue.
     *
     * @return bool
     */
    public function startOnce(): bool
    {
        $cursor = $this->getCursor(false);

        while (true) {
            $this->processLocalQueue();

            if (null === $cursor->current()) {
                if ($cursor->getInnerIterator()->isDead()) {
                    $this->logger->debug('all jobs were processed', [
                        'category' => get_class($this),
                    ]);

                    return false;
                }

                return true;
            }

            $job = $cursor->current();
            $cursor->next();
            $this->processJob($job);
        }
    }

    /**
     * Get cursor.
     *
     * @param bool $tailable
     *
     * @return IteratorIterator
     */
    protected function getCursor(bool $tailable = true): IteratorIterator
    {
        $options = [];
        if (true === $tailable) {
            $options['cursorType'] = Find::TAILABLE;
            $options['noCursorTimeout'] = true;
        }

        $cursor = $this->db->{$this->collection_name}->find([
            '$or' => [
                ['status' => self::STATUS_WAITING],
                ['status' => self::STATUS_POSTPONED,
                 'node' => $this->node_name, ],
                ['status' => self::STATUS_POSTPONED,
                 'at' => ['$gte' => new UTCDateTime()], ],
            ],
        ], $options);

        $iterator = new IteratorIterator($cursor);
        $iterator->rewind();

        return $iterator;
    }

    /**
     * Update job status.
     *
     * @param ObjectId $id
     * @param int      $status
     *
     * @return bool
     */
    protected function updateJob(ObjectId $id, int $status): bool
    {
        $result = $this->db->{$this->collection_name}->updateMany(['_id' => $id, '$isolated' => true], ['$set' => [
            'status' => $status,
            'node' => $this->node_name,
            'timestamp' => new UTCDateTime(),
        ]]);

        $this->logger->debug('job ['.$id.'] updated to status ['.$status.']', [
            'category' => get_class($this),
        ]);

        return $result->isAcknowledged();
    }

    /**
     * Check local queue for postponed jobs.
     *
     * @return bool
     */
    protected function processLocalQueue()
    {
        $now = new UTCDateTime();
        foreach ($this->queue as $key => $job) {
            if ($job['at'] >= $now) {
                $this->logger->info('postponed job ['.$job['_id'].'] ['.$job['class'].'] can now be executed', [
                    'category' => get_class($this),
                ]);

                unset($this->queue[$key]);
                $this->processJob($job);
            }
        }

        return true;
    }

    /**
     * Process job.
     *
     * @param array $job
     *
     * @return bool
     */
    protected function processJob(array $job): bool
    {
        if ($job['at'] instanceof UTCDateTime) {
            $this->updateJob($job['_id'], self::STATUS_POSTPONED);
            $this->queue[] = $job;

            $this->logger->debug('execution of job ['.$job['_id'].'] ['.$job['class'].'] is postponed at ['.$job['at'].']', [
                'category' => get_class($this),
            ]);

            return true;
        }

        $this->updateJob($job['_id'], self::STATUS_PROCESSING);

        $this->logger->debug('execute job ['.$job['_id'].'] ['.$job['class'].']', [
            'category' => get_class($this),
            'params' => $job['data'],
        ]);

        try {
            if (!class_exists($job['class'])) {
                throw new Exception('job class does not exists');
            }

            if (null === $this->container) {
                $instance = new $job['class']();
            } else {
                $instance = $this->container->getNew($job['class']);
            }

            if (!($instance instanceof JobInterface)) {
                throw new Exception('job must implement JobInterface');
            }

            $instance->setData($job['data'])
                ->start();

            $this->updateJob($job['_id'], self::STATUS_DONE);
        } catch (\Exception $e) {
            $this->logger->error('failed execute job ['.$job['_id'].']', [
                'category' => get_class($this),
                'exception' => $e,
            ]);

            $this->updateJob($job['_id'], self::STATUS_FAILED);
        }

        if ($job['interval'] >= 0) {
            $at = new UTCDateTime((time() + $job['interval']) * 1000);
            $this->addJob($job['class'], $job['data'], [
                'at' => $at,
                'interval' => $job['interval'],
                'retry' => $job['retry'],
            ]);
        }

        return true;
    }
}
