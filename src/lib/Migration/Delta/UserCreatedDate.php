<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copyright (c) 2012-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Migration\Delta;

use MongoDB\BSON\UTCDateTime;
use MongoDB\Database;

class UserCreatedDate implements DeltaInterface
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
        $cursor = $this->db->user->updateMany([
            '$or' => [
                ['created' => ['$exists' => false]],
                ['changed' => null],
            ],
        ], [
            '$set' => [
                'created' => new UTCDateTime(),
                'changed' => new UTCDateTime(),
            ],
        ]);

        return true;
    }
}
