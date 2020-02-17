<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Migration\Delta;

use Balloon\User\Factory as UserFactory;
use MongoDB\Database;
use MongoDB\Client;
use MongoDB\Driver\Exception\CommandException;
use Balloon\AccessRole\Factory as AccessRoleFactory;
use Balloon\AccessRule\Factory as AccessRuleFactory;

class CoreInstallation implements DeltaInterface
{
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
    public function __construct(Client $client, Database $db, UserFactory $user_factory, AccessRoleFactory $role_factory, AccessRuleFactory $rule_factory)
    {
        $this->client = $client;
        $this->db = $db;
        $this->user_factory = $user_factory;
        $this->role_factory = $role_factory;
        $this->rule_factory = $rule_factory;
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

        $this->db->nodes->createIndexes([
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

        $this->db->events->createIndexes([
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

        if(!$this->user_factory->has('admin')) {
            $this->user_factory->add([
                'username' => 'admin',
                'password' => 'admin',
                'mail' => 'root@localhost.local',
                'admin' => true,
            ]);
        }


        if (!$this->role_factory->has('admin')) {
            $this->role_factory->add([
                'name' => 'admin',
                'selectors' => ['admin'],
            ]);
        }

        if (!$this->role_factory->has('admin')) {
            $this->role_factory->add([
                'name' => 'user',
                'selectors' => ['*'],
            ]);
        }

        if (!$this->rule_factory->has('full-access')) {
            $this->rule_factory->add([
                'name' => 'full-access',
                'rules' => [[
                    'roles' => ['admin'],
                    'verbs' => ['*'],
                    'as' => ['*'],
                    'selectors' => ['*'],
                    'match' => ['*'],
                    'fields' => ['*'],
                ]],
            ]);
        }

        if (!$this->rule_factory->has('user-access')) {
            $this->rule_factory->add([
                'name' => 'user-access',
                'rules' => [[
                    'roles' => ['user'],
                    'verbs' => ['GET'],
                    'as' => [],
                    'selector' => ['users','groups'],
                    'match' => ['*'],
                    'fields' => ['id','username','name','member'],
                ],[
                    'roles' => ['user'],
                    'verbs' => ['*'],
                    'as' => [],
                    'selectors' => ['nodes','sessions','files','collections','events'],
                    'match' => ['*'],
                    'fields' => ['*'],
                ],
            ]]);
        }

        return true;
    }
}
