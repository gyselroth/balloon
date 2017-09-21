<?php
namespace Balloon\Testsuite\Unit\App\Api\v1\Node;

use \Balloon\Testsuite\Unit\App\Api\v1\Test;
use \Balloon\Api\v1\Node;
use \Micro\Http\Response;

abstract class DeleteTest extends Test
{
    protected static $first_cursor;
    protected static $current_cursor;

    public static function setUpBeforeClass()
    {
        $server = self::setupMockServer();
        self::$controller = new Node($server, $server->getLogger());
    }


    public function testReceiveLastDelta()
    {
        self::$first_cursor = $this->getLastCursor();
        self::$current_cursor = self::$first_cursor;
    }

    abstract public function testCreate();
    
    /**
     * @depends testCreate
     */
    public function testExists($id)
    {
        $res = self::$controller->head($id);
        $this->assertInstanceOf(Response::class, $res);
        $this->assertEquals(200, $res->getCode());
    }
    
    /**
     * @depends testCreate
     */
    public function testDeleteIntoTrash($id)
    {
        $res = self::$controller->delete($id);
        $this->assertInstanceOf(Response::class, $res);
        $this->assertEquals(204, $res->getCode());
        
        $delta = $this->getDelta(self::$current_cursor);
        $this->assertCount(1, $delta['nodes']);
        $this->assertTrue($delta['nodes'][0]['deleted']);
        self::$current_cursor = $delta['cursor'];

        return $id;
    }
    
    /**
     * @depends testDeleteIntoTrash
     */
    public function testExistsWhenDeleted($id)
    {
        $res = self::$controller->head($id);
        $this->assertInstanceOf(Response::class, $res);
        $this->assertEquals(404, $res->getCode());
        
    }

    /**
     * @depends testDeleteIntoTrash
     */
    public function testExistsWhenDeletedIncludeDeleted($id)
    { 
        $res = self::$controller->head($id, null, 1);
        $this->assertInstanceOf(Response::class, $res);
        $this->assertEquals(200, $res->getCode());
        
        $res = self::$controller->head($id, null, 2);
        $this->assertInstanceOf(Response::class, $res);
        $this->assertEquals(200, $res->getCode());
    } 
    
    /**
     * @depends testDeleteIntoTrash
     */
    public function testCheckIfIsDeleted($id)
    {
        $res = self::$controller->getAttributes($id);
        $this->assertInstanceOf(Response::class, $res);
        $this->assertEquals(200, $res->getCode());
        $this->assertArrayHasKey('deleted', $res->getBody());
        $this->assertInstanceOf('\stdClass', $res->getBody()['deleted']);
        return $id;
    }

    /**
     * @depends testCheckIfIsDeleted
     */
    public function testRestoreFromTrash($id)
    {
        $res = self::$controller->postUndelete($id);
        $this->assertInstanceOf(Response::class, $res);
        $this->assertEquals(204, $res->getCode());
        
        $delta = $this->getDelta(self::$current_cursor);
        $this->assertCount(1, $delta['nodes']);
        $this->assertFalse($delta['nodes'][0]['deleted']);
        self::$current_cursor = $delta['cursor'];
        
        return $id;
    }
    
    /**
     * @depends testRestoreFromTrash
     */
    public function testCheckIfIsNotDeleted($id)
    {
        $res = self::$controller->getAttributes($id);
        $this->assertInstanceOf(Response::class, $res);
        $this->assertEquals(200, $res->getCode());
        $body = $res->getBody();
        $this->assertArrayHasKey('deleted', $body);
        $this->assertFalse($body['deleted']);
        return $id;
    }

    /**
     * @depends testRestoreFromTrash
     */
    public function testForceDelete($id)
    {
        $res = self::$controller->delete($id, null, 1);
        $this->assertInstanceOf(Response::class, $res);
        $this->assertEquals(204, $res->getCode());
        return $id;
    }

    /**
     * @depends testRestoreFromTrash
     * @expectedException \Balloon\Exception\NotFound
     * @expectedExceptionCode 49
     */
    public function testCheckIfIsForceDeleted($id)
    {
        $res = self::$controller->getAttributes($id);
    }

    /**
     * @depends testForceDelete
     */
    public function testDelta($id)
    {
        $delta = $this->getDelta(self::$first_cursor);
        $this->assertCount(1, $delta['nodes']);
    }
}
