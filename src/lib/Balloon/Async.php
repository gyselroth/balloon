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
use \Micro\Config;

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
     * Config
     *
     * @var Config
     */
    protected $config;

    
    /**
     * Init queue
     *
     * @param   Filesystem $fs
     * @param   Logger $logger
     * @param   Config $config
     * @return  void
     */
    public function __construct(Database $db, Logger $logger, ?Config $config=null)
    {
        $this->db     = $db;
        $this->logger = $logger;
        $this->config = $config;
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
            'class' => get_class($job),
            'data'  => $job->getData()
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
     * Execute job queue
     *
     * @param  Filesystem $fs
     * @return void
     */
    public function execute(Filesystem $fs): void
    {
        $queue = $this->db->queue->find();
        
        foreach ($queue as $job) {
            $this->logger->debug("execute job [".$job['_id']."] [".$job['class']."]", [
                'category' => get_class($this),
                'params'   => $job['data']
            ]);
            
            try {
                if (!class_exists($job['class'])) {
                    $this->removeJob($job['_id']);
                    continue;
                }

                $instance = new $job['class']((array)$job['data']);
                $instance->run($fs, $this->logger, $this->config);
                $this->removeJob($job['_id']);
            } catch (\Exception $e) {
                $this->logger->error("failed execute job [".$job['_id']."], failed with error", [
                    'category' => get_class($this),
                    'exception' => $e,
                ]);
            
                $this->removeJob($job['_id']);
            }
        }
    }
}
