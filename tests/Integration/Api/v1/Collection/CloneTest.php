<?php
namespace Balloon\Testsuite\Api\Collection;

use Balloon\Testsuite\Test;

class CloneTest extends Test
{   
    protected static $delta = [];
    protected static $first_cursor;
    protected static $current_cursor;

    public function testReceiveLastDelta()
    {
        self::$first_cursor = $this->getLastCursor();
        self::$current_cursor = self::$first_cursor;
    }

    public function testCreate()
    {
        $name = uniqid();
        $res = $this->request('POST', '/collection?name='.$name);
        $this->assertEquals(201, $res->getStatusCode());
        $body = $this->jsonBody($res);
        $id = new \MongoDB\BSON\ObjectID($body);
        $this->assertInstanceOf('\MongoDB\BSON\ObjectID', $id);
        self::$delta[] = $id;
        return $id;
    }

    public function testCreate2()
    {
        return $this->testCreate();
    }
    
    /**
     * @depends testCreate
     */
    public function testCloneCollectionIntoItself($id)
    {
        $res = $this->request('POST', '/collection/clone?id='.$id.'&destid='.$id);
        $this->assertEquals(400, $res->getStatusCode());
        $body = $this->jsonBody($res);
        $this->assertEquals('Balloon\\Exception\\Conflict', $body['error']);
    }
    
    /**
     * @depends testCreate
     */
    public function testCloneCollectionIntoSameParent($id)
    {
        $res = $this->request('POST', '/collection/clone?id='.$id.'&destid=');
        $this->assertEquals(400, $res->getStatusCode());
        $body = $this->jsonBody($res);
        $this->assertEquals('Balloon\\Exception\\Conflict', $body['error']);
    }

    /**
     * @depends testCreate
     * @depends testCreate2
     */
    public function testCloneCollectionIntoOtherCollection($source, $dest)
    {
        $res = $this->request('POST', '/collection/clone?id='.$source.'&destid='.$dest);
        $this->assertEquals(201, $res->getStatusCode());
        $body = $this->jsonBody($res);
        $id = new \MongoDB\BSON\ObjectID($body);
        $this->assertInstanceOf('\MongoDB\BSON\ObjectID', $id);
        self::$delta[] = $id;
    }

    public function testDelta()
    {
        $delta = $this->getLastDelta(self::$first_cursor);
        $this->assertCount(count(self::$delta), $delta['nodes']);

        foreach($delta['nodes'] as $key => $node) {
            $node = (array)$node;
            $this->assertEquals($node['id'], self::$delta[$key]);
            $this->assertArrayHasKey('path', $node);
            $this->assertFalse($node['deleted']);
        }
    }
}
