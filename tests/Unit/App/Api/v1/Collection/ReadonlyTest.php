<?php
namespace Balloon\Testsuite\Unit\App\Api\v1\Collection;

use \Balloon\Testsuite\Unit\App\Api\v1\Test;
use \Balloon\Api\v1\Collection;
use \Micro\Http\Response;
use \MongoDB\BSON\ObjectID;

class ReadonlyTest extends Test
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
        $id = new ObjectID($res->getBody());
        $this->assertInstanceOf(ObjectID::class, $id);
        return (string)$id;
    }
    
    /**
     * @depends testCreate
     */
    public function testSetReadonly($id)
    {
        $res = self::$controller->postReadonly($id);
        $this->assertInstanceOf(Response::class, $res);
        $this->assertEquals(204, $res->getCode());
        return $id;
    }

    /**
     * @depends testSetReadonly
     * @expectedException \Balloon\Exception\Conflict
     * @expectedExceptionCode 25
     */
    public function testCreateChildUnderReadonlyCollection($id)
    {
        self::$controller->post($id, null, uniqid());
    }
    
    /**
     * @depends testSetReadonly
     */
    public function testSetWriteable($id)
    {
        $res = self::$controller->postReadonly($id, null, false);
        $this->assertInstanceOf(Response::class, $res);
        $this->assertEquals(204, $res->getCode());
    }

    /**
     * @depends testSetWriteable
     */
    public function testCreateChildUnderWriteableCollection($id)
    {
        $name = uniqid();
        $res = self::$controller->post($id, null, $name);
        $this->assertInstanceOf(Response::class, $res);
        $this->assertEquals(201, $res->getCode());
        $id = new ObjectID($res->getBody());
        $this->assertInstanceOf(ObjectID::class, $id);
    }
}
