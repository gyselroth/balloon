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
     * Local queue
     *
     * @var array
     */
    protected $queue = [];

    /**
     * Collection name
     *
     * @var string
     */
    protected $collection_name = 'queue';

    /**
     * Init queue.
     *
     * @param Filesystem      $fs
     * @param LoggerInterface $logger
     * @param string $collection_name
     */
    public function __construct(Database $db, LoggerInterface $logger, string $collection_name='queue', array $jobs=[])
    {
        $this->db = $db;
        $this->logger = $logger;
        $this->collection_name = $collection_name;

        foreach($jobs as $job) {
            $this->addJob($job['class'], $job['data']);
        }
    }

    /**
     * Add job to queue
     *
     * @param string $class
     * @param array $data
     * @param UTCDateTime $at
     *
     * @return bool
     */
    public function addJob(string $class, array $data, ?UTCDateTime $at=null): bool
    {
        $result = $this->db->{$this->collection_name}->insertOne([
            'class' => $class,
            'status' => self::STATUS_WAITING,
            'timestamp' => new UTCDateTime(),
            'at' => $at,
            'data' => $data,
        ]);

        $this->logger->debug('queue job ['.$result->getInsertedId().'] added to ['.$class.']', [
            'category' => get_class($this),
            'params' => $data,
        ]);

        return $result->isAcknowledged();
    }

    /**
     * Only add job if not in queue yet
     *
     * @param string $class
     * @param array $data
     *
     * @return bool
     */
    public function addJobOnce(string $class, array $data, bool $ignore_status=false, ?UTCDateTime $since=null): bool
    {
        $options =[
            'class' => $class,
            'data'=> $data,
        ];

        if($ignore_status === false) {
            $options['status'] = self::STATUS_WAITING;
        }

        if($since !== null) {
            $options['timestamp'] = ['$gt' => $since];
        }

        $result = $this->db->queue->findOne($options);

        if($result === null) {
            return $this->addJob($class, $data);
        } else {
            $this->logger->debug('queue job ['.$result['_id'].'] of type ['.$class.'] already exists', [
                'category' => get_class($this),
                'params' => $data,
            ]);

            return true;
        }
    }


    /**
     * Get cursor.
     *
     * @param bool $tailable
     *
     * @return IteratorIterator
     */
    public function getCursor(bool $tailable = true): IteratorIterator
    {
        $options = [];
        if (true === $tailable) {
            $options['cursorType'] = Find::TAILABLE;
            $options['noCursorTimeout'] = true;
        }

        $cursor = $this->db->{$this->collection_name}->find(['status' => self::STATUS_WAITING], $options);
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
    public function updateJob(ObjectId $id, int $status): bool
    {
        $result = $this->db->{$this->collection_name}->updateMany(['_id' => $id, '$isolated' => true], ['$set' => [
            'status' => $status,
            'timestamp' => new UTCDateTime(),
        ]]);

        $this->logger->debug('job ['.$id.'] updated to status ['.$status.']', [
            'category' => get_class($this)
        ]);

        return $result->isAcknowledged();
    }


    /**
     * Check local queue for postponed jobs
     *
     * @return bool
     */
    protected function processLocalQueue()
    {
        $now = new DateTime();
        foreach($this->queue as $key => $job) {
            if($job['at'] >= $now) {
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
     * Execute job queue.
     *
     * @param IteratorIterator   $cursor
     * @param ContainerInterface $container
     *
     * @return bool
     */
    public function start(IteratorIterator $cursor, ContainerInterface $container): bool
    {
        while (true) {
            if (null === $cursor->current()) {
                if ($cursor->getInnerIterator()->isDead()) {
                    $this->logger->error('job queue cursor is dead, is it a capped collection?', [
                        'category' => get_class($this),
                    ]);

                    return false;
                }

                continue;
                //$cursor->next();
                //return true;
            }

            $job = $cursor->current();
            $cursor->next();

            if($job['at'] instanceof UTCDateTime) {
                $this->updateJob($job['_id'], self::STATUS_POSTPONED);
                $this->queue[] = $job;

                $this->logger->debug('execution of job ['.$job['_id'].'] ['.$job['class'].'] is postponed at ['.$job['at'].']', [
                    'category' => get_class($this),
                ]);

                continue;
            }

            $this->processJob($job);
        }
    }


    /**
     * Process job
     *
     * @param array $job
     * @return bool
     */
    protected function processJob(array $job): bool
    {
        $this->updateJob($job['_id'], self::STATUS_PROCESSING);

        $this->logger->debug('execute job ['.$job['_id'].'] ['.$job['class'].']', [
            'category' => get_class($this),
            'params' => $job['data'],
        ]);

        try {
            if (!class_exists($job['class'])) {
                throw new Exception('job class does not exists');
            }

            $instance = $container->getNew($job['class']);

            if(!($instance instanceof JobInterface)) {
                throw new Exception('job must implement JobInterface');
            }

            $instance->setData($job['data'])
                ->start();

            $this->updateJob($job['_id'], self::STATUS_DONE);
        } catch (\Exception $e) {
            $this->logger->error('failed execute job ['.$job['_id'].'], failed with error', [
                'category' => get_class($this),
                'exception' => $e,
            ]);

            $this->updateJob($job['_id'], self::STATUS_FAILED);
            return false;
        }

        return true;
    }
}
