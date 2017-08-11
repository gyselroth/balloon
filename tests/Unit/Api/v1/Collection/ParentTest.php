<?php
namespace Balloon\Testsuite\Unit\Api\Collection;

use Balloon\Testsuite\Unit\Test;

class ParentTest extends Test
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
    public function testCreateChild($id)
    {
        $name = uniqid();
        $res = $this->request('POST', '/collection?id='.$id.'&name='.$name);
        $this->assertEquals(201, $res->getStatusCode());
        $body = $this->jsonBody($res);
        $id = new \MongoDB\BSON\ObjectID($body);
        $this->assertInstanceOf('\MongoDB\BSON\ObjectID', $id);
        return $id;
    }

    /**
     * @depends testCreate
     * @depends testCreateChild
     */
    public function testCheckParent($parent, $child)
    {
        $res = $this->request('GET', '/collection/'.$child.'/parent');
        $this->assertEquals(200, $res->getStatusCode());
        $body = $this->jsonBody($res);
        $this->assertEquals($parent, new \MongoDB\BSON\ObjectID($body['id']));
    }

    /**
     * @depends testCreate
     * @depends testCreateChild
     */
    public function testCheckParents($parent, $child)
    {
        $res = $this->request('GET', '/collection/'.$child.'/parents');
        $this->assertEquals(200, $res->getStatusCode());
        $body = $this->jsonBody($res);
        $this->assertCount(1, $body);
        $this->assertEquals($parent, new \MongoDB\BSON\ObjectID($body[0]->id));
    }

    /**
     * @depends testCreate
     * @depends testCreateChild
     */
    public function testCheckParentsIncludingSelf($parent, $child)
    {
        $res = $this->request('GET', '/collection/'.$child.'/parents?self=true');
        $this->assertEquals(200, $res->getStatusCode());
        $body = $this->jsonBody($res);
        $this->assertCount(2, $body);
        $this->assertEquals($child, new \MongoDB\BSON\ObjectID($body[0]->id));
        $this->assertEquals($parent, new \MongoDB\BSON\ObjectID($body[1]->id));
    }
}
