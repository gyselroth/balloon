<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copyright (c) 2012-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Migration\Delta;

use Balloon\Server;
use MongoDB\Client;
use MongoDB\Database;
use MongoDB\Driver\Exception\CommandException;

class CoreInstallation implements DeltaInterface
{
    /**
     * MongoDB Client.
     *
     * @var Client
     */
    protected $client;

    /**
     * Database.
     *
     * @var Database
     */
    protected $db;

    /**
     * Server.
     *
     * @var Server
     */
    protected $server;

    /**
     * Construct.
     */
    public function __construct(Database $db, Server $server, Client $client)
    {
        $this->db = $db;
        $this->server = $server;
        $this->client = $client;
    }

    /**
     * Initialize database.
     */
    public function start(): bool
    {
        try {
            $this->client->selectDatabase('admin')->command(['replSetInitiate' => []]);
        } catch (CommandException $e) {
            if ($e->getCode() !== 23) {
                throw $e;
            }
        }

        $collections = [];
        foreach ($this->db->listCollections() as $collection) {
            $collections[] = $collection->getName();
        }

        $this->db->user->createIndex(['username' => 1], ['unique' => true]);
        $this->db->user->createIndex(['mail' => 1], [
            'unique' => true,
            'partialFilterExpression' => [
                'mail' => [
                    '$type' => 'string',
                ],
            ],
        ]);

        $this->db->group->createIndex(['member' => 1]);
        $this->db->selectCollection('fs.files')->createIndex(['md5' => 1], [
            'unique' => true,
            'partialFilterExpression' => [
                'md5' => ['$exists' => true],
            ],
        ]);

        $this->db->selectCollection('fs.chunks')->createIndex(
            ['files_id' => 1, 'n' => 1],
            ['unique' => true]
        );

        $this->db->storage->createIndexes([
            ['key' => [
                'name' => 1,
                'owner' => 1,
                'parent' => 1,
                'deleted' => 1,
            ], 'unique' => true],
            ['key' => ['acl.id' => 1]],
            ['key' => ['hash' => 1]],
            ['key' => ['parent' => 1, 'owner' => 1]],
            ['key' => ['reference' => 1]],
            ['key' => ['shared' => 1]],
            ['key' => ['deleted' => 1]],
            ['key' => ['pointer' => 1]],
            ['key' => [
                'owner' => 1,
                'directory' => 1,
                'delete' => 1,
            ]],
        ]);

        $this->db->delta->createIndexes([
            ['key' => ['owner' => 1]],
            ['key' => ['timestamp' => 1]],
            ['key' => ['node' => 1]],
            ['key' => ['share' => 1]],
        ]);

        if (!in_array('queue', $collections, true)) {
            $this->db->createCollection(
                'queue',
                [
                'capped' => true,
                'size' => 100000, ]
            );
        }

        $this->server->addUser('admin', [
            'password' => 'admin',
            'mail' => 'root@localhost.local',
            'admin' => true,
        ]);

        $this->db->taskscheduler->createIndex(['created' => 1], ['expireAfterSeconds' => 864000]);

        return true;
    }
}
