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
use Psr\Log\LoggerInterface;

/**
 * @coversNothing
 */
class ParentTest extends Test
{
    public function setUp()
    {
        $server = $this->getMockServer();
        $this->controller = new Collection($server, $this->createMock(LoggerInterface::class));
    }

    public function testCreate()
    {
        $name = uniqid();
        $res = $this->controller->post(null, null, $name);
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
    public function testCreateChild($id)
    {
        $name = uniqid();
        $res = $this->controller->post($id, null, $name);
        $this->assertInstanceOf(Response::class, $res);
        $this->assertSame(201, $res->getCode());
        $id = new ObjectID($res->getBody());
        $this->assertInstanceOf(ObjectID::class, $id);

        return (string) $id;
    }

    /**
     * @depends testCreate
     * @depends testCreateChild
     *
     * @param mixed $parent
     * @param mixed $child
     */
    public function testCheckParent($parent, $child)
    {
        $res = $this->controller->getParent($child);
        $this->assertInstanceOf(Response::class, $res);
        $this->assertSame(200, $res->getCode());
        $this->assertSame($parent, $res->getBody()['id']);
    }

    /**
     * @depends testCreate
     * @depends testCreateChild
     *
     * @param mixed $parent
     * @param mixed $child
     */
    public function testCheckParents($parent, $child)
    {
        $res = $this->controller->getParents($child);
        $this->assertInstanceOf(Response::class, $res);
        $this->assertSame(200, $res->getCode());
        $this->assertCount(1, $res->getBody());
        $this->assertSame($parent, $res->getBody()[0]['id']);
    }

    /**
     * @depends testCreate
     * @depends testCreateChild
     *
     * @param mixed $parent
     * @param mixed $child
     */
    public function testCheckParentsIncludingSelf($parent, $child)
    {
        $res = $this->controller->getParents($child, null, [], true);
        $this->assertInstanceOf(Response::class, $res);
        $this->assertSame(200, $res->getCode());
        $body = $res->getBody();
        $this->assertCount(2, $body);
        $this->assertSame($child, $body[0]['id']);
        $this->assertSame($parent, $body[1]['id']);
    }
}
