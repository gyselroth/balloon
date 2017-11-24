<?php

declare(strict_types=1);

/**
 * Balloon
 *
 * @author      Raffael Sahli <sahli@gyselroth.net>
 * @copyright   Copryright (c) 2012-2017 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Database\Delta;

use MongoDB\Database;

class CoreInstallation extends AbstractDelta
{
    /**
     * Initialize database.
     *
     * @return bool
     */
    public function init(): bool
    {
        /*
        db.delta.createIndex({"owner": 1})
        db.delta.createIndex({"timestamp": 1})
        db.delta.createIndex({"node": 1})
        */
        $collections = [];
        foreach ($this->db->listCollections() as $collection) {
            $collections[] = $collection->getName();
        }

        $this->db->user->createIndex(['username' => 1], ['unique' => true]);
        $this->db->selectCollection('fs.files')->createIndex(['md5' => 1], ['unique' => true]);
        $this->db->storage->createIndexes([
            ['key' => ['acl.group.group' => 1]],
            ['key' => ['acl.user.user' => 1]],
            ['key' => ['hash' => 1]],
            ['key' => ['parent' => 1, 'owner' => 1], ['sparse' => true]],
            ['key' => ['reference' => 1]],
            ['key' => ['shared' => 1]],
            ['key' => ['deleted' => 1]],
        ]);

        if (!in_array('queue', $collections, true)) {
            $this->db->createCollection(
                'queue',
                [
                'capped' => true,
                'size' => 100000, ]
            );
        }

        return true;
    }
}
