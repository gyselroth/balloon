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

class JsonEncodeFilteredCollection implements DeltaInterface
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
        $this->db->storage->updateMany(['directory' => true, 'filter' => []], [
            '$unset' => [
                'filter' => 1,
            ],
        ]);

        $cursor = $this->db->storage->find(
            [
            'directory' => true,
            'filter' => ['$type' => 3], ]
        );

        foreach ($cursor as $object) {
            $filter = json_encode($object['filter']);
            $this->db->storage->updateOne(
                ['_id' => $object['_id']],
                [
                   '$set' => ['filter' => $filter],
                ]
            );
        }

        return true;
    }
}
