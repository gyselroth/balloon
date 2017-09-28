<?php
declare(strict_types=1);

/**
 * Balloon
 *
 * @author      Raffael Sahli <sahli@gyselroth.net>
 * @copyright   Copryright (c) 2012-2017 gyselroth GmbH (https://gyselroth.com)
 * @license     GPLv3 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon;

use \MongoDB\Database;
use \MongoDB\BSON\ObjectId;
use \Balloon\Async\JobInterface;
use \Psr\Log\LoggerInterface;
use \MongoDB\Operation\Find;
use \MongoDB\Driver\Cursor;
use \MongoDB\BSON\UTCDateTime;
use \IteratorIterator;

class Async
{
    /**
     * Job status
     */
    const STATUS_WAITING = 0;
    const STATUS_PROCESSING = 1;
    const STATUS_DONE = 2;
    const STATUS_FAILED = 3;


    /**
     * Database
     *
     * @var Database
     */
    protected $db;


    /**
     * LoggerInterface
     *
     * @var LoggerInterface
     */
    protected $logger;


    /**
     * Init queue
     *
     * @param   Filesystem $fs
     * @param   LoggerInterface $logger
     * @return  void
     */
    public function __construct(Database $db, LoggerInterface $logger)
    {
        $this->db     = $db;
        $this->logger = $logger;
    }


    /**
     * Register job
     *
     * @param   JobInterface $job
     * @return  bool
     */
    public function addJob(JobInterface $job): bool
    {
        $bson = \MongoDB\BSON\fromPHP($job);
        $result = $this->db->queue->insertOne([
            'class'     => get_class($job),
            'status'    => self::STATUS_WAITING,
            'timestamp' => new UTCDateTime(),
            'data'      => $job->getData()
        ]);

        $this->logger->debug("queue job [".$result->getInsertedId()."] added to [".get_class($job)."]", [
            'category' => get_class($this),
            'params'   => $job->getData()
        ]);

        return $result->isAcknowledged();
    }


    /**
     * Remove job
     *
     * @param   ObjectId $id
     * @return  bool
     */
    public function removeJob(ObjectId $id): bool
    {
        $result = $this->db->queue->deleteOne(['_id' => $id]);
        return $result->isAcknowledged();
    }


    /**
     * Get cursor
     *
     * @param  bool $tailable
     * @return IteratorIterator
     */
    public function getCursor(bool $tailable=false): IteratorIterator
    {
        $options = [];
        if ($tailable === true) {
            $options['cursorType'] = Find::TAILABLE;
        }

        $cursor = $this->db->queue->find(['waiting' => 0], $options);
        $iterator = new IteratorIterator($cursor);
        $iterator->rewind();

        return $iterator;
    }


    /**
     * Update job status
     *
     * @param  ObjectId $id
     * @param  int $status
     * @return bool
     */
    public function updateJob(ObjectId $id, int $status): bool
    {
        $result = $this->db->queue->updateMany(['_id' => $id, '$isolated' => true], [ '$set' => [
            'waiting'   => $status,
            'timestamp' => new UTCDateTime()
        ]]);

        return $result->isAcknowledged();
    }


    /**
     * Execute job queue
     *
     * @param  IteratorIterator $cursor
     * @param  Server $server
     * @return bool
     */
    public function start(IteratorIterator $cursor, Server $server): bool
    {
        while (true) {
            if ($cursor->current() === null) {
                if ($cursor->getInnerIterator()->isDead()) {
                    return false;
                } else {
                    $cursor->next();
                    return true;
                }
            }

            $job = $cursor->current();
            $cursor->next();

            $this->updateJob($job['_id'], self::STATUS_PROCESSING);

            $this->logger->debug("execute job [".$job['_id']."] [".$job['class']."]", [
                'category' => get_class($this),
                'params'   => $job['data']
            ]);

            try {
                if (!class_exists($job['class'])) {
                    $this->updateJob($job['_id'], self::STATUS_FAILED);
                    continue;
                }

                $instance = new $job['class']((array)$job['data']);
                $instance->start($server, $this->logger);
                $this->updateJob($job['_id'], self::STATUS_DONE);
            } catch (\Exception $e) {
                $this->logger->error("failed execute job [".$job['_id']."], failed with error", [
                    'category' => get_class($this),
                    'exception' => $e,
                ]);

                $this->updateJob($job['_id'], self::STATUS_FAILED);
            }
        }
    }
}
