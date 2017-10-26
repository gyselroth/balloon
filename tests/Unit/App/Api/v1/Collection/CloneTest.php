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
class CloneTest extends Test
{
    protected static $delta = [];

    public function setUp()
    {
        $server = $this->getMockServer();
        $this->controller = new Collection($server, $this->createMock(LoggerInterface::class));
    }

    public function testReceiveLastDelta()
    {
        self::$first_cursor = $this->getLastCursor();
        self::$current_cursor = self::$first_cursor;
    }

    public function testCreate()
    {
        $name = uniqid();
        $res = $this->controller->post(null, null, $name);
        $this->assertInstanceOf(Response::class, $res);
        $this->assertSame(201, $res->getCode());
        $id = new ObjectID($res->getBody());
        $this->assertInstanceOf(ObjectID::class, $id);
        self::$delta[] = (string) $id;

        return (string) $id;
    }

    public function testCreate2()
    {
        return $this->testCreate();
    }

    /**
     * @depends testCreate
     * @expectedException \Balloon\Exception\Conflict
     * @expectedExceptionCode 272
     *
     * @param mixed $id
     */
    public function testCloneCollectionIntoItself($id)
    {
        $this->controller->postClone($id, null, $id);
    }

    /**
     * @depends testCreate
     * @expectedException \Balloon\Exception\Conflict
     * @expectedExceptionCode 19
     *
     * @param mixed $id
     */
    public function testCloneCollectionIntoSameParent($id)
    {
        $this->controller->postClone($id);
    }

    /**
     * @depends testCreate
     * @depends testCreate2
     *
     * @param mixed $source
     * @param mixed $dest
     */
    public function testCloneCollectionIntoOtherCollection($source, $dest)
    {
        $res = $this->controller->postClone($source, null, $dest);
        $this->assertSame(201, $res->getCode());
        $id = new ObjectID($res->getBody());
        $this->assertInstanceOf(ObjectID::class, $id);
        self::$delta[] = (string) $id;
    }

    public function testDelta()
    {
        $delta = $this->getDelta(self::$current_cursor);
        $this->assertCount(count(self::$delta), $delta['nodes']);

        foreach ($delta['nodes'] as $key => $node) {
            $node = (array) $node;
            $this->assertSame($node['id'], self::$delta[$key]);
            $this->assertArrayHasKey('path', $node);
            $this->assertFalse($node['deleted']);
        }
    }
}
