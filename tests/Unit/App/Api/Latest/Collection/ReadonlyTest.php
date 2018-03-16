<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2018 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Testsuite\Unit\App\Api\Latest\Collection;

use Balloon\Testsuite\Unit\App\Api\Latest\Test;
use Micro\Http\Response;
use MongoDB\BSON\ObjectId;

/**
 * @coversNothing
 */
class ReadonlyTest extends Test
{
    public function setUp()
    {
        $this->controller = $this->getCollectionController();
    }

    public function testCreate()
    {
        $name = uniqid();
        $res = $this->controller->post(null, null, $name);
        $this->assertInstanceOf(Response::class, $res);
        $this->assertSame(201, $res->getCode());
        $id = new ObjectId($res->getBody()['id']);
        $this->assertInstanceOf(ObjectId::class, $id);

        return (string) $id;
    }

    /**
     * @depends testCreate
     *
     * @param mixed $id
     */
    public function testSetReadonly($id)
    {
        $res = $this->controller->patch($id, null, null, true);
        $this->assertInstanceOf(Response::class, $res);
        $this->assertSame(200, $res->getCode());
        $this->assertTrue($res->getBody()['readonly']);

        return $id;
    }

    /**
     * @depends testSetReadonly
     * @expectedException \Balloon\Exception\Conflict
     * @expectedExceptionCode 25
     *
     * @param mixed $id
     */
    public function testCreateChildUnderReadonlyCollection($id)
    {
        $this->controller->post($id, null, uniqid());
    }

    /**
     * @depends testSetReadonly
     *
     * @param mixed $id
     */
    public function testSetWriteable($id)
    {
        $res = $this->controller->patch($id, null, null, false);
        $this->assertInstanceOf(Response::class, $res);
        $this->assertSame(200, $res->getCode());
        $this->assertFalse($res->getBody()['readonly']);
    }

    /**
     * @depends testSetWriteable
     *
     * @param mixed $id
     */
    public function testCreateChildUnderWriteableCollection($id)
    {
        $name = uniqid();
        $res = $this->controller->post($id, null, $name);
        $this->assertInstanceOf(Response::class, $res);
        $this->assertSame(201, $res->getCode());
        $id = new ObjectId($res->getBody()['id']);
        $this->assertInstanceOf(ObjectId::class, $id);
    }
}
