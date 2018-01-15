<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2018 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Migration\Delta;

use MongoDB\Database;
use MongoDB\Exception\RuntimeException;

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
            if (26 !== $e->getCode()) {
                throw $e;
            }
        }

        $this->db->queue->removeMany([]);

        return true;
    }
}
