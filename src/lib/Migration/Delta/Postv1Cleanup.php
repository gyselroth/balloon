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

class Postv1Cleanup implements DeltaInterface
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
        $cursor = $this->db->user->find([
            '$or' => [
                ['groups' => ['$exists' => true]],
                ['ldapdn' => ['$exists' => true]],
                ['last_share_sync' => ['$exists' => true]],
            ],
        ]);

        foreach ($cursor as $user) {
            $this->db->user->updateOne(
                ['_id' => $user['_id']],
                [
                    '$unset' => [
                        'groups' => true,
                        'ldapdn' => true,
                        'last_share_sync' => true,
                    ],
                ]
            );
        }

        $cursor = $this->db->storage->find([
            ['migration' => ['$exists' => true]],
        ]);

        foreach ($cursor as $node) {
            $this->db->storage->updateOne(
                ['_id' => $node['_id']],
                [
                    '$unset' => [
                        'migration' => true,
                    ],
                ]
            );
        }

        return true;
    }
}
