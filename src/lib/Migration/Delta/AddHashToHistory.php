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

class AddHashToHistory implements DeltaInterface
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
                'history.hash' => ['$exists' => 0],
            ]],
            ['$lookup' => [
                'from' => 'fs.files',
                'foreignField' => '_id',
                'localField' => 'history.storage._id',
                'as' => 'blob',
           ]],
        ]);

        foreach ($cursor as $object) {
            if (count($object['blob']) === 0) {
                continue;
            }

            $this->db->storage->updateOne(
                [
                    '_id' => $object['_id'],
                    'history.version' => $object['history']['version'],
                ],
                [
                    '$set' => [
                        'history.$.hash' => $object['blob'][0]['md5'],
                    ],
                ]
            );
        }

        return true;
    }
}
