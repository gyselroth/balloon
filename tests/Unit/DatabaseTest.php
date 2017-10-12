<?php
namespace Balloon\Testsuite\Unit;

use \Balloon\Database;
use \Balloon\Database\DatabaseInterface;

class DatabaseTest extends Test
{
    protected $server;
    protected $expected_indices = [
        "balloon.user.username_1",
        "balloon.fs.files.md5_1",
        "balloon.storage.acl.group.group_1",
        "balloon.storage.acl.user.user_1",
        "balloon.storage.hash_1",
        "balloon.storage.parent_1_owner_1",
        "balloon.storage.reference_1",
        "balloon.storage.shared_1",
        "balloon.storage.deleted_1"
    ];

    public function setUp()
    {
        $server = self::setupMockServer();
        $this->server = $server;
    }

    public function testInitDatabase()
    {
        $db = new Database($this->server, self::$logger);
        $this->assertInstanceOf(DatabaseInterface::class, $db->getSetups()[0]);
        return $db;
    }

    /**
     * @depends testInitDatabase
     */
    public function testInitCoreIndices($db)
    {
        $available = [];

        $db->init();
        $mongodb = $db->getServer()->getDatabase();
        foreach($mongodb->listCollections() as $collection) {
            foreach($mongodb->{$collection->getName()}->listIndexes() as $index)  {
                $available[] = $index['ns'].'.'.$index['name'];
            }
        }

        $this->assertEquals($this->expected_indices, $available);
    }
}
