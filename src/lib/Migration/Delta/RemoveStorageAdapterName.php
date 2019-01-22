<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Migration\Delta;

use MongoDB\Database;

class RemoveStorageAdapterName implements DeltaInterface
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
        $cursor = $this->db->storage->find([
            'storage_adapter' => ['$exists' => true],
        ]);

        foreach ($cursor as $object) {
            $this->db->storage->updateOne(
                ['_id' => $object['_id']],
                [
                    '$unset' => ['storage_adapter' => 1],
                ]
            );
        }

        return true;
    }
}
