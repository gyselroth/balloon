<?php

declare(strict_types=1);

/**
 * Balloon
 *
 * @author      Raffael Sahli <sahli@gyselroth.net>
 * @copyright   Copryright (c) 2012-2017 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Testsuite\Unit\App\Api\Latest\Collection;

use Balloon\Testsuite\Unit\App\Api\Latest\Node;
use Micro\Http\Response;
use MongoDB\BSON\ObjectId;

/**
 * @coversNothing
 */
class RenameTest extends Node\RenameTest
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
        $id = new ObjectId($res->getBody());
        $this->assertInstanceOf(ObjectId::class, $id);

        return (string) $id;
    }
}
