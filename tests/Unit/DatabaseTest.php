<?php

declare(strict_types=1);

/**
 * Balloon
 *
 * @author      Raffael Sahli <sahli@gyselroth.net>
 * @copyright   Copryright (c) 2012-2017 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Testsuite\Unit;

use Balloon\Database;
use Balloon\Database\DatabaseInterface;

/**
 * @coversNothing
 */
class DatabaseTest extends Test
{
    protected $server;
    protected $expected_indices = [
        'balloon.user.username_1',
        'balloon.fs.files.md5_1',
        'balloon.storage.acl.group.group_1',
        'balloon.storage.acl.user.user_1',
        'balloon.storage.hash_1',
        'balloon.storage.parent_1_owner_1',
        'balloon.storage.reference_1',
        'balloon.storage.shared_1',
        'balloon.storage.deleted_1',
    ];

    public function setUp()
    {
        $server = self::setupMockServer();
        $this->server = $server;
    }

    public function testInitDatabase()
    {
        //Server $server, Database $db, LoggerInterface $logger, ProgressBar $bar
        $db = new Database($this->server, $this->server->getDatabase(), self::$logger);
        $this->assertInstanceOf(DatabaseInterface::class, $db->getSetups()[0]);

        return $db;
    }

    /**
     * @depends testInitDatabase
     *
     * @param mixed $db
     */
    public function testInitCoreIndices($db)
    {
        $available = [];

        $db->init();
        $mongodb = $db->getServer()->getDatabase();
        foreach ($mongodb->listCollections() as $collection) {
            foreach ($mongodb->{$collection->getName()}->listIndexes() as $index) {
                $available[] = $index['ns'].'.'.$index['name'];
            }
        }

        $this->assertSame($this->expected_indices, $available);
    }
}
