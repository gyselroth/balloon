<?php
namespace Balloon\Testsuite\Unit\Api\Collection;

use \Balloon\Testsuite\Unit\Test;
use \Balloon\Api\v1\Collection;

class CloneTest extends Test
{   
    protected static $delta = [];
    protected static $first_cursor;
    protected static $current_cursor;
    protected static $server;
    protected static $controller;

    public static function setUpBeforeClass()
    {
        self::$server = self::setupMockServer();
        self::$controller = new Collection(self::$server, self::$server->getLogger());
    }

    public function testReceiveLastDelta()
    {
        self::$first_cursor = $this->getLastCursor();
        self::$current_cursor = self::$first_cursor;
    }

    public function testCreate()
    {       
        $name = uniqid();
        $res = self::$controller->post(null, null, $name);
        $this->assertInstanceOf('\Micro\Http\Response', $res);
        $this->assertEquals(201, $res->getCode());
        $id = new \MongoDB\BSON\ObjectID($res->getBody());
        $this->assertInstanceOf('\MongoDB\BSON\ObjectID', $id);
        self::$delta[] = $id;
        return (string)$id;
    }

    public function testCreate2()
    {
        return $this->testCreate();
    }
    
    /**
     * @depends testCreate
     * @expectedException \Balloon\Exception\Conflict
     * @expectedExceptionCode 272
     */
    public function testCloneCollectionIntoItself($id)
    {
        self::$controller->postClone($id, null, $id);
    }
    
    /**
     * @depends testCreate
     * @expectedException \Balloon\Exception\Conflict
     * @expectedExceptionCode 17
     */
    public function testCloneCollectionIntoSameParent($id)
    {
        self::$controller->postClone($id, null, null);
    }

    /**
     * @depends testCreate
     * @depends testCreate2
     */
    public function testCloneCollectionIntoOtherCollection($source, $dest)
    {
        $res = self::$controller->postClone($source, null, $dest);
        $this->assertEquals(201, $res->getCode());
        $id = new \MongoDB\BSON\ObjectID($res->getBody());
        $this->assertInstanceOf('\MongoDB\BSON\ObjectID', $id);
        self::$delta[] = $id;
    }

    public function testDelta()
    {
        $delta = self::$server->getFilesystem()->getDelta()->getDeltaFeed(self::$first_cursor);
        $this->assertCount(count(self::$delta), $delta['nodes']);

        foreach($delta['nodes'] as $key => $node) {
            $node = (array)$node;
            $this->assertEquals($node['id'], self::$delta[$key]);
            $this->assertArrayHasKey('path', $node);
            $this->assertFalse($node['deleted']);
        }
    }
}
