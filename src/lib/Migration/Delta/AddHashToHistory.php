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
                        'history.$.hash' => $object['blob']['md5'],
                    ],
                ]
            );
        }

        return true;
    }
}
