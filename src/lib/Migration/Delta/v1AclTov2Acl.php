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
            'directory' => true,
            'acl' => ['$exists' => 1],
            '$or' => [
                ['acl.user' => ['$exists' => 1]],
                ['acl.group' => ['$exists' => 1]],
            ],
        ]);

        foreach ($cursor as $object) {
            $acl = [];

            if (isset($object['acl']['group'])) {
                foreach ($object['acl']['group'] as $rule) {
                    $group = $this->db->group->findOne(['ldapdn' => $rule['group']]);
                    if ($group !== null) {
                        $acl[] = [
                            'type' => 'group',
                            'privilege' => $rule['priv'] === 'w' ? 'w+' : $rule['priv'],
                            'id' => (string) $group['_id'],
                        ];
                    }
                }
            }

            if (isset($object['acl']['user'])) {
                foreach ($object['acl']['user'] as $rule) {
                    $user = null;
                    if (isset($rule['ldapdn'])) {
                        $user = $this->db->user->findOne(['ldapdn' => $rule['ldapdn']]);
                    } elseif (isset($rule['user'])) {
                        $user = $this->db->user->findOne(['username' => $rule['user']]);
                    }

                    if ($user !== null) {
                        $acl[] = [
                            'type' => 'user',
                            'privilege' => $rule['priv'] === 'w' ? 'w+' : $rule['priv'],
                            'id' => (string) $user['_id'],
                        ];
                    }
                }
            }

            $this->db->storage->updateOne(
                ['_id' => $object['_id']],
                [
                    '$set' => [
                        'migration.v1AclTov2Acl.acl' => $object['acl'],
            'acl' => $acl,
            ],
                ]
            );
        }

        return true;
    }
}
