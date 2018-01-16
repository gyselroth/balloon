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
                $group = $db->group->findOne(['ldapdn' => $rule['group']]);
                if ($group !== null) {
                    $acl[] = [
                        'type' => 'group',
                        'privilege' => $rule['privilege'] === 'w' ? 'w+' : $rule['privilege'],
                        'role' => (string) $group['_id'],
                    ];
                }
            }

            foreach ($object['acl']['user'] as $rule) {
                $user = null;
                if (isset($rule['ldapdn'])) {
                    $user = $db->user->findOne(['ldapdn' => $rule['ldapdn']]);
                } elseif (isset($rule['user'])) {
                    $user = $db->user->findOne(['username' => $rule['user']]);
                }

                if ($user !== null) {
                    $acl[] = [
                        'type' => 'user',
                        'privilege' => $rule['privilege'] === 'w' ? 'w+' : $rule['privilege'],
                        'role' => (string) $user['_id'],
                    ];
                }
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
