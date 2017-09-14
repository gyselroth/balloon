<?php
namespace Balloon\Testsuite\Unit\App\Api\v1\Collection;

use \Balloon\Testsuite\Unit\App\Api\v1\Node;
use \Balloon\Api\v1\Collection;
use \Micro\Http\Response;
use \MongoDB\BSON\ObjectID;

class RenameTest extends Node\RenameTest
{
    public static function setUpBeforeClass()
    {
        $server = self::setupMockServer();
        self::$controller = new Collection($server, $server->getLogger());
    }

    public function testCreate()
    {
        $name = uniqid();
        $res = self::$controller->post(null, null, $name);
        $this->assertInstanceOf(Response::class, $res);
        $this->assertEquals(201, $res->getCode());
        $id = new \MongoDB\BSON\ObjectID($res->getBody());
        $this->assertInstanceOf('\MongoDB\BSON\ObjectID', $id);
        return $id;
    }
}
