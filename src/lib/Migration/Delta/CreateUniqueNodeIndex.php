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

class CreateUniqueNodeIndex implements DeltaInterface
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
        $pipeline = [
            ['$group' => [
                '_id' => [
                    'name' => '$name',
                    'parent' => '$parent',
                    'owner' => '$owner',
                    'deleted' => '$deleted',
                ],
                'count' => [
                    '$sum' => 1,
                ],
                'nodes' => [
                    '$addToSet' => '$_id',
                ],
            ]],
            ['$match' => [
                '_id' => ['$ne' => null],
                'count' => ['$gt' => 1],
            ]],
        ];

        $cursor = $this->db->storage->aggregate($pipeline, [
            'allowDiskUse' => true,
        ]);

        foreach ($cursor as $object) {
            $id = array_pop($object['nodes']);
            $this->db->storage->deleteOne(['_id' => $id]);
        }

        $this->db->storage->createIndex([
            'name' => 1,
            'owner' => 1,
            'parent' => 1,
            'deleted' => 1,
        ], [
            'unique' => true,
        ]);

        return true;
    }
}
