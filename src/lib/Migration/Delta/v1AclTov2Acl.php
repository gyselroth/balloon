<?php

declare(strict_types=1);

/**
 * Balloon
 *
 * @author      Raffael Sahli <sahli@gyselroth.net>
 * @copyright   Copryright (c) 2012-2017 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Migration\Delta;

use MongoDB\Database;

class v1AclTov2Acl implements DeltaInterface
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
        $cursor = $this->db->storage->find(
            [
            'directory' => true,
            'acl' => ['$exists' => 1], ]
        );

        foreach ($cursor as $object) {
            $acl = [];

            foreach ($object['acl']['group'] as $rule) {
                $acl[] = [
                    'type' => 'group',
                    'privilege' => $rule['privilege'],
                    'role' => $rule['role'],
                ];
            }

            foreach ($object['acl']['user'] as $rule) {
                $acl[] = [
                    'type' => 'user',
                    'privilege' => $rule['privilege'],
                    'role' => $rule['role'],
                ];
            }

            $this->db->storage->updateOne(
                ['_id' => $object['_id']],
                [
                    '$set' => ['acl' => $acl],
                ]
            );
        }

        return true;
    }
}
