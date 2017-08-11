<?php
namespace Balloon\Testsuite\Unit\Api\v1\Collection;

use Balloon\Testsuite\Unit\Api\v1\Node;

class RenameTest extends Node\RenameTest
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
}
