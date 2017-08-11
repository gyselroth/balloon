<?php
namespace Balloon\Testsuite\Unit\Api\v1\Node;

use Balloon\Testsuite\Unit\Test;

abstract class DeleteTest extends Test
{
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
        $res = $this->request('PUT', '/'.$this->type.'?name='.$name);
        $this->assertEquals(201, $res->getStatusCode());
        $body = $this->jsonBody($res);
        $id = new \MongoDB\BSON\ObjectID($body);
        $this->assertInstanceOf('\MongoDB\BSON\ObjectID', $id);
        $delta = $this->getLastDelta(self::$current_cursor);
        $this->assertCount(1, $delta['nodes']);
        $this->assertEquals((string)$id, $delta['nodes'][0]->id);
        self::$current_cursor = $delta['cursor'];
        
        return $id;
    }
    
    /**
     * @depends testCreate
     */
    public function testExists($id)
    {
        $res = $this->request('HEAD', '/'.$this->type.'?id='.$id);
        $this->assertEquals(200, $res->getStatusCode());
    }
    
    /**
     * @depends testCreate
     */
    public function testDeleteIntoTrash($id)
    {
        $res = $this->request('DELETE', '/'.$this->type.'?id='.$id);
        $this->assertEquals(204, $res->getStatusCode());
        
        $delta = $this->getLastDelta(self::$current_cursor);
        $this->assertCount(1, $delta['nodes']);
        $this->assertTrue($delta['nodes'][0]->deleted);
        self::$current_cursor = $delta['cursor'];
        
        return $id;
    }
    
    /**
     * @depends testDeleteIntoTrash
     */
    public function testExistsWhenDeleted($id)
    {
        $res = $this->request('HEAD', '/'.$this->type.'/'.$id);
        $this->assertEquals(404, $res->getStatusCode());
        
        $res = $this->request('HEAD', '/'.$this->type.'/'.$id.'?deleted=1');
        $this->assertEquals(200, $res->getStatusCode());
        
        $res = $this->request('HEAD', '/'.$this->type.'/'.$id.'?deleted=2');
        $this->assertEquals(200, $res->getStatusCode());
    } 
    
    /**
     * @depends testDeleteIntoTrash
     */
    public function testCheckIfIsDeleted($id)
    {
        $res = $this->request('GET', '/'.$this->type.'/attributes?id='.$id);
        $this->assertEquals(200, $res->getStatusCode());
        $body = $this->jsonBody($res);
        $this->assertArrayHasKey('deleted', $body);
        $this->assertInstanceOf('\stdClass', $body['deleted']);
        return $id;
    }

    /**
     * @depends testCheckIfIsDeleted
     */
    public function testRestoreFromTrash($id)
    {
        $res = $this->request('POST', '/'.$this->type.'/undelete?id='.$id);
        $this->assertEquals(204, $res->getStatusCode());
        
        $delta = $this->getLastDelta(self::$current_cursor);
        $this->assertCount(1, $delta['nodes']);
        $this->assertFalse($delta['nodes'][0]->deleted);
        self::$current_cursor = $delta['cursor'];
        
        return $id;
    }
    
    /**
     * @depends testRestoreFromTrash
     */
    public function testCheckIfIsNotDeleted($id)
    {
        $res = $this->request('GET', '/'.$this->type.'/attributes?id='.$id);
        $this->assertEquals(200, $res->getStatusCode());
        $body = $this->jsonBody($res);
        $this->assertArrayHasKey('deleted', $body);
        $this->assertFalse($body['deleted']);
        return $id;
    }

    /**
     * @depends testRestoreFromTrash
     */
    public function testForceDelete($id)
    {
        $res = $this->request('DELETE', '/'.$this->type.'?id='.$id.'&force=1');
        $this->assertEquals(204, $res->getStatusCode());
        return $id;
    }

    /**
     * @depends testRestoreFromTrash
     */
    public function testCheckIfIsForceDeleted($id)
    {
        $res = $this->request('GET', '/'.$this->type.'/attributes?id='.$id);
        $this->assertEquals(404, $res->getStatusCode());
        $body = $this->jsonBody($res);
        $this->assertEquals('Balloon\\Exception\\NotFound', $body['error']);
    }


    /**
     * @depends testForceDelete
     */
    public function testDelta($id)
    {
        $delta = $this->getLastDelta(self::$first_cursor);
        $this->assertCount(1, $delta['nodes']);
        $this->assertEquals((string)$id, $delta['nodes'][0]->id);
        $this->assertTrue($delta['nodes'][0]->deleted);
    }
}
