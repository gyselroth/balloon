<?php

declare(strict_types=1);

/**
 * Balloon
 *
 * @author      Raffael Sahli <sahli@gyselroth.net>
 * @copyright   Copryright (c) 2012-2017 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Testsuite\Api\v1\Node;

use Balloon\Testsuite\Test;

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
        $this->assertSame(201, $res->getStatusCode());
        $body = $this->jsonBody($res);
        $id = new \MongoDB\BSON\ObjectID($body);
        $this->assertInstanceOf('\MongoDB\BSON\ObjectID', $id);
        $delta = $this->getLastDelta(self::$current_cursor);
        $this->assertCount(1, $delta['nodes']);
        $this->assertSame((string) $id, $delta['nodes'][0]->id);
        self::$current_cursor = $delta['cursor'];

        return $id;
    }

    /**
     * @depends testCreate
     *
     * @param mixed $id
     */
    public function testExists($id)
    {
        $res = $this->request('HEAD', '/'.$this->type.'?id='.$id);
        $this->assertSame(200, $res->getStatusCode());
    }

    /**
     * @depends testCreate
     *
     * @param mixed $id
     */
    public function testDeleteIntoTrash($id)
    {
        $res = $this->request('DELETE', '/'.$this->type.'?id='.$id);
        $this->assertSame(204, $res->getStatusCode());

        $delta = $this->getLastDelta(self::$current_cursor);
        $this->assertCount(1, $delta['nodes']);
        $this->assertTrue($delta['nodes'][0]->deleted);
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
        $res = $this->request('HEAD', '/'.$this->type.'/'.$id);
        $this->assertSame(404, $res->getStatusCode());

        $res = $this->request('HEAD', '/'.$this->type.'/'.$id.'?deleted=1');
        $this->assertSame(200, $res->getStatusCode());

        $res = $this->request('HEAD', '/'.$this->type.'/'.$id.'?deleted=2');
        $this->assertSame(200, $res->getStatusCode());
    }

    /**
     * @depends testDeleteIntoTrash
     *
     * @param mixed $id
     */
    public function testCheckIfIsDeleted($id)
    {
        $res = $this->request('GET', '/'.$this->type.'/attributes?id='.$id);
        $this->assertSame(200, $res->getStatusCode());
        $body = $this->jsonBody($res);
        $this->assertArrayHasKey('deleted', $body);
        $this->assertInstanceOf('\stdClass', $body['deleted']);

        return $id;
    }

    /**
     * @depends testCheckIfIsDeleted
     *
     * @param mixed $id
     */
    public function testRestoreFromTrash($id)
    {
        $res = $this->request('POST', '/'.$this->type.'/undelete?id='.$id);
        $this->assertSame(204, $res->getStatusCode());

        $delta = $this->getLastDelta(self::$current_cursor);
        $this->assertCount(1, $delta['nodes']);
        $this->assertFalse($delta['nodes'][0]->deleted);
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
        $res = $this->request('GET', '/'.$this->type.'/attributes?id='.$id);
        $this->assertSame(200, $res->getStatusCode());
        $body = $this->jsonBody($res);
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
        $res = $this->request('DELETE', '/'.$this->type.'?id='.$id.'&force=1');
        $this->assertSame(204, $res->getStatusCode());

        return $id;
    }

    /**
     * @depends testRestoreFromTrash
     *
     * @param mixed $id
     */
    public function testCheckIfIsForceDeleted($id)
    {
        $res = $this->request('GET', '/'.$this->type.'/attributes?id='.$id);
        $this->assertSame(404, $res->getStatusCode());
        $body = $this->jsonBody($res);
        $this->assertSame('Balloon\\Exception\\NotFound', $body['error']);
    }

    /**
     * @depends testForceDelete
     *
     * @param mixed $id
     */
    public function testDelta($id)
    {
        $delta = $this->getLastDelta(self::$first_cursor);
        $this->assertCount(1, $delta['nodes']);
        $this->assertSame((string) $id, $delta['nodes'][0]->id);
        $this->assertTrue($delta['nodes'][0]->deleted);
    }
}
