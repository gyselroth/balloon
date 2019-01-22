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

class SetPointerId implements DeltaInterface
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
            'pointer' => ['$exists' => false],
        ]);

        foreach ($cursor as $object) {
            if (isset($object['reference'])) {
                $pointer = $object['reference'];
            } else {
                $pointer = $object['_id'];
            }

            $this->db->storage->updateOne(
                ['_id' => $object['_id']],
                [
                   '$set' => ['pointer' => $pointer],
                ]
            );
        }

        $this->db->selectCollection('storage')->createIndex(['pointer' => 1]);

        return true;
    }
}
