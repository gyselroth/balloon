<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copyright (c) 2012-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Migration\Delta;

use MongoDB\BSON\UTCDateTime;
use MongoDB\Database;

class LdapGroupsToLocalGroups implements DeltaInterface
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
        $cursor = $this->db->user->aggregate([
        ['$unwind' => '$groups'],
        ['$group' => [
                '_id' => '$groups',
                'member' => [
                    '$push' => '$_id',
                ],
            ]],
        ]);

        foreach ($cursor as $group) {
            $dn = explode(',', $group['_id']);
            $attrs = [];
            foreach ($dn as $part) {
                $parts = explode('=', $part);
                if ($parts[0] === 'cn') {
                    $attrs['name'] = $parts[1];
                } elseif ($parts[0] === 'o') {
                    $attrs['namespace'] = $parts[1];
                }
            }

            if (isset($attrs['namespace'])) {
                $attrs['name'] .= '.'.$attrs['namespace'];
            }

            $attrs['ldapdn'] = $group['_id'];
            $attrs['member'] = $group['member'];
            $attrs['created'] = new UTCDateTime();
            $attrs['changed'] = new UTCDateTime();

            $this->db->group->insertOne($attrs);
        }

        return true;
    }
}
