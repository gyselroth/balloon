<?php

declare(strict_types=1);

/**
 * Balloon
 *
 * @author      Raffael Sahli <sahli@gyselroth.net>
 * @copyright   Copryright (c) 2012-2017 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Testsuite\Unit\App\Api\v1\Collection;

use Balloon\Api\v1\Collection;
use Balloon\Testsuite\Unit\App\Api\v1\Test;
use Micro\Http\Response;
use MongoDB\BSON\ObjectID;

/**
 * @coversNothing
 */
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
        $this->assertSame(201, $res->getCode());
        $id = new ObjectID($res->getBody());
        $this->assertInstanceOf(ObjectID::class, $id);

        return (string) $id;
    }

    /**
     * @depends testCreate
     *
     * @param mixed $id
     */
    public function testSetReadonly($id)
    {
        $res = self::$controller->postReadonly($id);
        $this->assertInstanceOf(Response::class, $res);
        $this->assertSame(204, $res->getCode());

        return $id;
    }

    /**
     * @depends testSetReadonly
     * @expectedExceptionCode 25
     *
     * @param mixed $id
     */
    public function testCreateChildUnderReadonlyCollection($id)
    {
        self::$controller->post($id, null, uniqid());
    }

    /**
     * @depends testSetReadonly
     *
     * @param mixed $id
     */
    public function testSetWriteable($id)
    {
        $res = self::$controller->postReadonly($id, null, false);
        $this->assertInstanceOf(Response::class, $res);
        $this->assertSame(204, $res->getCode());
    }

    /**
     * @depends testSetWriteable
     *
     * @param mixed $id
     */
    public function testCreateChildUnderWriteableCollection($id)
    {
        $name = uniqid();
        $res = self::$controller->post($id, null, $name);
        $this->assertInstanceOf(Response::class, $res);
        $this->assertSame(201, $res->getCode());
        $id = new ObjectID($res->getBody());
        $this->assertInstanceOf(ObjectID::class, $id);
    }
}
