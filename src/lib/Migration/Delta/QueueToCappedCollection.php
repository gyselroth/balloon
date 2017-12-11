<?php

declare(strict_types=1);

/**
 * Balloon
 *
 * @author      Raffael Sahli <sahli@gyselroth.net>
 * @copyright   Copryright (c) 2012-2017 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Migration\Delta;

use MongoDB\BSON\UTCDateTime;
use MongoDB\Database;
use TaskScheduler\Async;

class QueueToCappedCollection implements DeltaInterface
{
    /**
     * Database.
     *
     * @var Database
     */
    protected $db;

    /**
     * Construct.
     *
     * @param Database $db
     */
    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    /**
     * Start.
     *
     * @return bool
     */
    public function start(): bool
    {
        try {
            $this->db->command([
                'convertToCapped' => 'queue',
                'size' => 100000,
            ]);
        } catch (RuntimeException $e) {
            if (26 === $e->getCode()) {
                $this->logger->debug('queue collection does not exists, skip upgrade', [
                    'category' => get_class($this),
                ]);
            } else {
                throw $e;
            }
        }

        $this->db->queue->updateMany([], [
            '$set' => [
                'timestamp' => new UTCDateTime(),
                'status' => Async::STATUS_WAITING,
            ],
        ]);

        return true;
    }
}
