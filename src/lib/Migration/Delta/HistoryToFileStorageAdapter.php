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

class HistoryToFileStorageAdapter implements DeltaInterface
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
                'history.storage' => ['$exists' => 0],
            ]],
        ]);

        foreach ($cursor as $object) {
            $this->db->storage->updateOne(
                [
                    '_id' => $object['_id'],
                    'history.version' => $object['history']['version'],
                ],
                [
                    '$set' => [
                        'history.$.storage' => $object['history']['file'] === null ? null : ['_id' => $object['history']['file']],
                        'history.$.storage_adapter' => 'gridfs',
                    ],
                    '$unset' => ['history.$.file' => true],
                ]
            );
        }

        return true;
    }
}
