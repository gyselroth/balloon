<?php

declare(strict_types=1);

/**
 * Balloon
 *
 * @author      Raffael Sahli <sahli@gyselroth.net>
 * @copyright   Copryright (c) 2012-2017 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Testsuite\Api\Collection;

use Balloon\Testsuite\Test;

/**
 * @coversNothing
 */
class ParentTest extends Test
{
    public function testCreate()
    {
        $name = uniqid();
        $res = $this->request('POST', '/collection?name='.$name);
        $this->assertSame(201, $res->getStatusCode());
        $body = $this->jsonBody($res);
        $id = new \MongoDB\BSON\ObjectID($body);
        $this->assertInstanceOf('\MongoDB\BSON\ObjectID', $id);

        return $id;
    }

    /**
     * @depends testCreate
     *
     * @param mixed $id
     */
    public function testCreateChild($id)
    {
        $name = uniqid();
        $res = $this->request('POST', '/collection?id='.$id.'&name='.$name);
        $this->assertSame(201, $res->getStatusCode());
        $body = $this->jsonBody($res);
        $id = new \MongoDB\BSON\ObjectID($body);
        $this->assertInstanceOf('\MongoDB\BSON\ObjectID', $id);

        return $id;
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
        $res = $this->request('GET', '/collection/'.$child.'/parent');
        $this->assertSame(200, $res->getStatusCode());
        $body = $this->jsonBody($res);
        $this->assertSame($parent, new \MongoDB\BSON\ObjectID($body['id']));
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
        $res = $this->request('GET', '/collection/'.$child.'/parents');
        $this->assertSame(200, $res->getStatusCode());
        $body = $this->jsonBody($res);
        $this->assertCount(1, $body);
        $this->assertSame($parent, new \MongoDB\BSON\ObjectID($body[0]->id));
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
        $res = $this->request('GET', '/collection/'.$child.'/parents?self=true');
        $this->assertSame(200, $res->getStatusCode());
        $body = $this->jsonBody($res);
        $this->assertCount(2, $body);
        $this->assertSame($child, new \MongoDB\BSON\ObjectID($body[0]->id));
        $this->assertSame($parent, new \MongoDB\BSON\ObjectID($body[1]->id));
    }
}
