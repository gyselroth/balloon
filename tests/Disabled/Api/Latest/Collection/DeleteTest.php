<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2018 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Testsuite\Unit\App\Api\Latest\Collection;

use Balloon\Testsuite\Unit\App\Api\Latest\Node;
use Micro\Http\Response;
use MongoDB\BSON\ObjectId;

/**
 * @coversNothing
 */
class DeleteTest extends Node\DeleteTest
{
    public function setUp()
    {
        $this->controller = $this->getCollectionController();
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
        $id = new ObjectId($res->getBody()['id']);
        $this->assertInstanceOf(ObjectId::class, $id);

        $delta = $this->getDelta(self::$current_cursor);
        $this->assertCount(1, $delta['nodes']);
        $this->assertSame((string) $id, $delta['nodes'][0]['id']);
        self::$current_cursor = $delta['cursor'];

        return (string) $id;
    }
}
