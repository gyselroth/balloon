<?php
namespace Balloon\Testsuite\Unit\App\Api\v1\Collection;

use \Balloon\Testsuite\Unit\App\Api\v1\Test;
use \Balloon\Api\v1\Collection;
use \Micro\Http\Response;
use \MongoDB\BSON\ObjectID;

class ParentTest extends Test
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
    public function testCreateChild($id)
    {
        $name = uniqid();
        $res = self::$controller->post($id, null, $name);
        $this->assertInstanceOf(Response::class, $res);
        $this->assertEquals(201, $res->getCode());
        $id = new ObjectID($res->getBody());
        $this->assertInstanceOf(ObjectID::class, $id);
        return (string)$id;
    }

    /**
     * @depends testCreate
     * @depends testCreateChild
     */
    public function testCheckParent($parent, $child)
    {
        $res = self::$controller->getParent($child);
        $this->assertInstanceOf(Response::class, $res);
        $this->assertEquals(200, $res->getCode());
        $this->assertEquals(new ObjectID($parent), new ObjectID($res->getBody()['id']));
    }

    /**
     * @depends testCreate
     * @depends testCreateChild
     */
    public function testCheckParents($parent, $child)
    {
        $res = self::$controller->getParents($child);
        $this->assertInstanceOf(Response::class, $res);
        $this->assertEquals(200, $res->getCode());
        $this->assertCount(1, $res->getBody());
        $this->assertEquals(new ObjectID($parent), new ObjectID($res->getBody()[0]['id']));
    }

    /**
     * @depends testCreate
     * @depends testCreateChild
     */
    public function testCheckParentsIncludingSelf($parent, $child)
    {
        $res = self::$controller->getParents($child, null, [], true);
        $this->assertInstanceOf(Response::class, $res);
        $this->assertEquals(200, $res->getCode());
        $body = $res->getBody();
        $this->assertCount(2, $body);
        $this->assertEquals(new ObjectID($child), new ObjectID($body[0]['id']));
        $this->assertEquals(new ObjectID($parent), new ObjectID($body[1]['id']));
    }
}
