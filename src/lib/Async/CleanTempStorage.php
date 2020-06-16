<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Async;

use MongoDB\BSON\UTCDateTime;
use MongoDB\Database;
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
    public function __construct(Database $db, LoggerInterface $logger)
    {
        $this->db = $db;
        $this->logger = $logger;
    }

    /**
     * {@inheritdoc}
     */
    public function start(): bool
    {
        $this->logger->debug('clean temporary storage from blobs older than ['.$this->data['max_age'].'s]', [
            'category' => get_class($this),
        ]);

        $lt = (time() - $this->data['max_age']) * 1000;
        $result = $this->db->selectCollection('fs.files')->find([
            'uploadDate' => ['$lt' => new UTCDateTime($lt)],
            'metadata.temporary' => true,
        ]);

        $count = 0;
        $gridfs = $this->db->selectGridFSBucket();

        foreach ($result as $blob) {
            $gridfs->delete($blob['_id']);
            ++$count;
        }

        $this->logger->info('found ['.$count.'] temporary storage blobs for cleanup', [
            'category' => get_class($this),
        ]);

        return true;
    }
}
