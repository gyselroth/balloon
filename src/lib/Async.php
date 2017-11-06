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
    const STATUS_PROCESSING = 1;
    const STATUS_DONE = 2;
    const STATUS_FAILED = 3;

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
     * Init queue.
     *
     * @param Filesystem      $fs
     * @param LoggerInterface $logger
     */
    public function __construct(Database $db, LoggerInterface $logger)
    {
        $this->db = $db;
        $this->logger = $logger;
    }

    /**
     * Add job to queue
     *
     * @param string $class
     * @param array $data
     *
     * @return bool
     */
    public function addJob(string $class, array $data): bool
    {
        $result = $this->db->queue->insertOne([
            'class' => $class,
            'status' => self::STATUS_WAITING,
            'timestamp' => new UTCDateTime(),
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
    public function getCursor(bool $tailable = false): IteratorIterator
    {
        $options = [];
        if (true === $tailable) {
            $options['cursorType'] = Find::TAILABLE;
            $options['noCursorTimeout'] = true;
        }

        $cursor = $this->db->queue->find(['status' => self::STATUS_WAITING], $options);
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
        $result = $this->db->queue->updateMany(['_id' => $id, '$isolated' => true], ['$set' => [
            'status' => $status,
            'timestamp' => new UTCDateTime(),
        ]]);

        $this->logger->debug('job ['.$id.'] updated to status ['.$status.']', [
            'category' => get_class($this)
        ]);

        return $result->isAcknowledged();
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
                $cursor->next();

                return true;
            }

            $job = $cursor->current();
            $cursor->next();

            $this->updateJob($job['_id'], self::STATUS_PROCESSING);

            $this->logger->debug('execute job ['.$job['_id'].'] ['.$job['class'].']', [
                'category' => get_class($this),
                'params' => $job['data'],
            ]);

            try {
                if (!class_exists($job['class'])) {
                    $this->updateJob($job['_id'], self::STATUS_FAILED);

                    continue;
                }

                $instance = $container->getNew($job['class']);
                $instance->setData($job['data'])
                    ->start();
                $this->updateJob($job['_id'], self::STATUS_DONE);
            } catch (\Exception $e) {
                $this->logger->error('failed execute job ['.$job['_id'].'], failed with error', [
                    'category' => get_class($this),
                    'exception' => $e,
                ]);

                $this->updateJob($job['_id'], self::STATUS_FAILED);
            }
        }
    }
}
