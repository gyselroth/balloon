<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copyright (c) 2012-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Migration\Delta;

use MongoDB\Database;

class RemoveStorageReferenceFromHistory implements DeltaInterface
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
        $cursor = $this->db->storage->aggregate([
            ['$unwind' => '$history'],
            ['$match' => [
                '$or' => [
                    ['history.storage_reference' => ['$exists' => 1]],
                    ['history.storage_adapter' => ['$exists' => 1]],
                ],
            ]],
        ]);

        foreach ($cursor as $object) {
            $this->db->storage->updateOne(
                [
                    '_id' => $object['_id'],
                    'history.version' => $object['history']['version'],
                ],
                [
                    '$unset' => [
                        'history.$.storage_adapter' => true,
                        'history.$.storage_reference' => true,
                    ],
                ]
            );
        }

        return true;
    }
}
