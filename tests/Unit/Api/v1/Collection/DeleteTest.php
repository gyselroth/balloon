<?php
namespace Balloon\Testsuite\Unit\Api\v1\Collection;

use Balloon\Testsuite\Unit\Api\v1\Node;

class DeleteTest extends Node\DeleteTest
{
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
        
        $delta = $this->getLastDelta(self::$current_cursor);
        $this->assertCount(1, $delta['nodes']);
        $this->assertEquals((string)$id, $delta['nodes'][0]->id);
        self::$current_cursor = $delta['cursor'];
        
        return $id;
    }
}
