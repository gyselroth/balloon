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
use Balloon\Testsuite\Unit\App\Api\v1\Node;
use Micro\Http\Response;
use MongoDB\BSON\ObjectID;
use Psr\Log\LoggerInterface;

/**
 * @coversNothing
 */
class DeleteTest extends Node\DeleteTest
{
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

        $delta = $this->getDelta(self::$current_cursor);
        $this->assertCount(1, $delta['nodes']);
        $this->assertSame((string) $id, $delta['nodes'][0]['id']);
        self::$current_cursor = $delta['cursor'];

        return (string) $id;
    }
}
