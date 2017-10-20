<?php

declare(strict_types=1);

/**
 * Balloon
 *
 * @author      Raffael Sahli <sahli@gyselroth.net>
 * @copyright   Copryright (c) 2012-2017 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Testsuite\Unit\App\Api\v1\Node;

use Balloon\Api\v1\Node;
use Balloon\Testsuite\Unit\App\Api\v1\Test;
use Micro\Http\Response;

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
     *
     * @param mixed $id
     */
    public function testExists($id)
    {
        $res = self::$controller->head($id);
        $this->assertInstanceOf(Response::class, $res);
        $this->assertSame(200, $res->getCode());
    }

    /**
     * @depends testCreate
     *
     * @param mixed $id
     */
    public function testDeleteIntoTrash($id)
    {
        $res = self::$controller->delete($id);
        $this->assertInstanceOf(Response::class, $res);
        $this->assertSame(204, $res->getCode());

        $delta = $this->getDelta(self::$current_cursor);
        $this->assertCount(1, $delta['nodes']);
        $this->assertTrue($delta['nodes'][0]['deleted']);
        self::$current_cursor = $delta['cursor'];

        return $id;
    }

    /**
     * @depends testDeleteIntoTrash
     *
     * @param mixed $id
     */
    public function testExistsWhenDeleted($id)
    {
        $res = self::$controller->head($id);
        $this->assertInstanceOf(Response::class, $res);
        $this->assertSame(404, $res->getCode());
    }

    /**
     * @depends testDeleteIntoTrash
     *
     * @param mixed $id
     */
    public function testExistsWhenDeletedIncludeDeleted($id)
    {
        $res = self::$controller->head($id, null, 1);
        $this->assertInstanceOf(Response::class, $res);
        $this->assertSame(200, $res->getCode());

        $res = self::$controller->head($id, null, 2);
        $this->assertInstanceOf(Response::class, $res);
        $this->assertSame(200, $res->getCode());
    }

    /**
     * @depends testDeleteIntoTrash
     *
     * @param mixed $id
     */
    public function testCheckIfIsDeleted($id)
    {
        $res = self::$controller->getAttributes($id);
        $this->assertInstanceOf(Response::class, $res);
        $this->assertSame(200, $res->getCode());
        $this->assertArrayHasKey('deleted', $res->getBody());
        $this->assertInstanceOf('\stdClass', $res->getBody()['deleted']);

        return $id;
    }

    /**
     * @depends testCheckIfIsDeleted
     *
     * @param mixed $id
     */
    public function testRestoreFromTrash($id)
    {
        $res = self::$controller->postUndelete($id);
        $this->assertInstanceOf(Response::class, $res);
        $this->assertSame(204, $res->getCode());

        $delta = $this->getDelta(self::$current_cursor);
        $this->assertCount(1, $delta['nodes']);
        $this->assertFalse($delta['nodes'][0]['deleted']);
        self::$current_cursor = $delta['cursor'];

        return $id;
    }

    /**
     * @depends testRestoreFromTrash
     *
     * @param mixed $id
     */
    public function testCheckIfIsNotDeleted($id)
    {
        $res = self::$controller->getAttributes($id);
        $this->assertInstanceOf(Response::class, $res);
        $this->assertSame(200, $res->getCode());
        $body = $res->getBody();
        $this->assertArrayHasKey('deleted', $body);
        $this->assertFalse($body['deleted']);

        return $id;
    }

    /**
     * @depends testRestoreFromTrash
     *
     * @param mixed $id
     */
    public function testForceDelete($id)
    {
        $res = self::$controller->delete($id, null, 1);
        $this->assertInstanceOf(Response::class, $res);
        $this->assertSame(204, $res->getCode());

        return $id;
    }

    /**
     * @depends testRestoreFromTrash
     * @expectedExceptionCode 49
     *
     * @param mixed $id
     */
    public function testCheckIfIsForceDeleted($id)
    {
        $res = self::$controller->getAttributes($id);
    }

    /**
     * @depends testForceDelete
     *
     * @param mixed $id
     */
    public function testDelta($id)
    {
        $delta = $this->getDelta(self::$first_cursor);
        $this->assertCount(1, $delta['nodes']);
    }
}
