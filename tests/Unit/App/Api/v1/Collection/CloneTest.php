<?php
namespace Balloon\Testsuite\Unit\App\Api\v1\Collection;

use \Balloon\Testsuite\Unit\App\Api\v1\Test;
use \Balloon\Api\v1\Collection;
use \Micro\Http\Response;
use \MongoDB\BSON\ObjectID;

class CloneTest extends Test
{   
    protected static $delta = [];
    
    public static function setUpBeforeClass()
    {
        $server = self::setupMockServer();
        self::$controller = new Collection($server, $server->getLogger());
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
        $this->assertInstanceOf(Response::class, $res);
        $this->assertEquals(201, $res->getCode());
        $id = new ObjectID($res->getBody());
        $this->assertInstanceOf(ObjectID::class, $id);
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
     * @expectedExceptionCode 19
     */
    public function testCloneCollectionIntoSameParent($id)
    {
        self::$controller->postClone($id);
    }

    /**
     * @depends testCreate
     * @depends testCreate2
     */
    public function testCloneCollectionIntoOtherCollection($source, $dest)
    {
        $res = self::$controller->postClone($source, null, $dest);
        $this->assertEquals(201, $res->getCode());
        $id = new ObjectID($res->getBody());
        $this->assertInstanceOf(ObjectID::class, $id);
        self::$delta[] = $id;
    }

    public function testDelta()
    {
        $delta = $this->getDelta(self::$current_cursor);
        $this->assertCount(count(self::$delta), $delta['nodes']);

        foreach($delta['nodes'] as $key => $node) {
            $node = (array)$node;
            $this->assertEquals($node['id'], self::$delta[$key]);
            $this->assertArrayHasKey('path', $node);
            $this->assertFalse($node['deleted']);
        }
    }
}
