<?php
namespace Balloon\Testsuite\Unit\Api\Collection;

use Balloon\Testsuite\Unit\Test;

class ReadonlyTest extends Test
{
    public function testCreate()
    {
        $name = uniqid();
        $res = $this->request('POST', '/collection?name='.$name);
        $this->assertEquals(201, $res->getStatusCode());
        $body = $this->jsonBody($res);
        $id = new \MongoDB\BSON\ObjectID($body);
        $this->assertInstanceOf('\MongoDB\BSON\ObjectID', $id);
        return $id;
    }
    
    /**
     * @depends testCreate
     */
    public function testSetReadonly($id)
    {
        $res = $this->request('POST', '/collection/readonly?id='.$id);
        $this->assertEquals(204, $res->getStatusCode());
        return $id;
    }

    /**
     * @depends testSetReadonly
     */
    public function testCreateChildUnderReadonlyCollection($id)
    {
        $name = uniqid();
        $res = $this->request('POST', '/collection?id='.$id.'&name='.$name);
        $this->assertEquals(400, $res->getStatusCode());
        $body = $this->jsonBody($res);
        $this->assertEquals('Balloon\\Exception\\Conflict', $body['error']);
        return $id;
    }
    
    /**
     * @depends testCreateChildUnderReadonlyCollection
     */
    public function testSetWriteable($id)
    {
        $res = $this->request('POST', '/collection/'.$id.'/readonly?readonly=false');
        $this->assertEquals(204, $res->getStatusCode());
    }

    /**
     * @depends testSetWriteable
     */
    public function testCreateChildUnderWriteableCollection($id)
    {
        $name = uniqid();
        $res = $this->request('POST', '/collection/'.$id.'?name='.$name);
        $this->assertEquals(201, $res->getStatusCode());
        $body = $this->jsonBody($res);
        $id = new \MongoDB\BSON\ObjectID($body);
        $this->assertInstanceOf('\MongoDB\BSON\ObjectID', $id);
    }
}
