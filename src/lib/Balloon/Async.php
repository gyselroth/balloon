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
use \Psr\Log\LoggerInterface as Logger;
use \MongoDB\Operation\Find;
use \MongoDB\Driver\Cursor;
use \MongoDB\BSON\UTCDateTime;
use \IteratorIterator;

class Async
{
    /**
     * Database
     *
     * @var Database
     */
    protected $db;
    

    /**
     * Logger
     *
     * @var Logger
     */
    protected $logger;


    /**
     * Init queue
     *
     * @param   Filesystem $fs
     * @param   Logger $logger
     * @return  void
     */
    public function __construct(Database $db, Logger $logger)
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
            'waiting'   => true,
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
        if($tailable === true) {
            $options['cursorType'] = Find::TAILABLE;
        }

        $cursor = $this->db->queue->find(['waiting' => true], $options);
        $iterator = new IteratorIterator($cursor);
        $iterator->rewind();

        return $iterator;
    }


    /**
     * Mark job as executed
     *
     * @return bool
     */
    public function updateJob(ObjectId $id, bool $status): bool
    {
        $result = $this->db->queue->updateOne(['_id' => $id], [ '$set' => [
            'waiting'   => false,
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
        while(true) {
            if($cursor->current() === null) {
                if($cursor->getInnerIterator()->isDead()) {
                    return false;
                } else {
                    $cursor->next();
                    return true;
                }
            }

            $job = $cursor->current();
            $cursor->next();

            $this->logger->debug("execute job [".$job['_id']."] [".$job['class']."]", [
                'category' => get_class($this),
                'params'   => $job['data']
            ]);
            
            try {
                if (!class_exists($job['class'])) {
                    $this->updateJob($job['_id'], false);
                    continue;
                }

                $instance = new $job['class']((array)$job['data']);
                $instance->start($server, $this->logger);
                $this->updateJob($job['_id'], true);
            } catch (\Exception $e) {
                $this->logger->error("failed execute job [".$job['_id']."], failed with error", [
                    'category' => get_class($this),
                    'exception' => $e,
                ]);
                
                $this->updateJob($job['_id'], false);
            }
        }
    }
}
