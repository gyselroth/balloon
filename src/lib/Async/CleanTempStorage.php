<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copyright (c) 2012-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Async;

use Balloon\Session\Factory as SessionFactory;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Database;
use MongoDB\GridFS\Bucket;
use Psr\Log\LoggerInterface;
use TaskScheduler\AbstractJob;

class CleanTempStorage extends AbstractJob
{
    /**
     * Databse.
     *
     * @var Database
     */
    protected $db;

    /**
     * Session factory.
     *
     * @var SessionFactory
     */
    protected $session_factory;

    /**
     * Bucket.
     *
     * @var Bucket
     */
    protected $bucket;

    /**
     * Logger.
     *
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * Default data.
     *
     * @var array
     */
    protected $data = [
        'max_age' => 172800,
    ];

    /**
     * Constructor.
     */
    public function __construct(Database $db, SessionFactory $session_factory, LoggerInterface $logger, Bucket $bucket)
    {
        $this->db = $db;
        $this->logger = $logger;
        $this->session_factory = $session_factory;
        $this->bucket = $bucket;
    }

    /**
     * {@inheritdoc}
     */
    public function start(): bool
    {
        $this->logger->debug('clean sessions older than ['.$this->data['max_age'].'s]', [
            'category' => get_class($this),
        ]);

        $lt = (time() - $this->data['max_age']) * 1000;
        $result = $this->db->selectCollection('sessions')->find([
            'changed' => ['$lt' => new UTCDateTime($lt)],
        ]);

        $count = 0;
        foreach ($result as $session) {
            $this->session_factory->deleteOne($session['_id']);
            $this->bucket->delete($session['_id']);
            ++$count;
        }

        $this->logger->info('found ['.$count.'] temporary storage blobs for cleanup', [
            'category' => get_class($this),
        ]);

        return true;
    }
}
