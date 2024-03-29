<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copyright (c) 2012-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\Notification\Migration\Delta;

use Balloon\Migration\Delta\DeltaInterface;
use MongoDB\Database;

class Installation implements DeltaInterface
{
    /**
     * Database.
     *
     * @var Database
     */
    protected $db;

    /**
     * Construct.
     */
    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    /**
     * Start.
     */
    public function start(): bool
    {
        $this->db->selectCollection('subscription')->createIndex(['node' => 1]);
        $this->db->selectCollection('subscription')->createIndex(['user' => 1]);
        $this->db->selectCollection('notification')->createIndex(['receiver' => 1]);
        $this->db->selectCollection('notification')->createIndex(['sender' => 1]);

        return true;
    }
}
