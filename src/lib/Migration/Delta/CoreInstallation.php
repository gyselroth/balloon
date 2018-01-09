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

class CoreInstallation implements DeltaInterface
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
     * Initialize database.
     *
     * @return bool
     */
    public function start(): bool
    {
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
            ['key' => ['parent' => 1, 'owner' => 1]],
            ['key' => ['reference' => 1]],
            ['key' => ['shared' => 1]],
            ['key' => ['deleted' => 1]],
        ]);

        $this->db->delta->createIndexes([
            ['key' => ['owner' => 1]],
            ['key' => ['timestamp' => 1]],
            ['key' => ['node' => 1]],
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
