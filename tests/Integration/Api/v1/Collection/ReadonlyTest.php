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
class ReadonlyTest extends Test
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
    public function testSetReadonly($id)
    {
        $res = $this->request('POST', '/collection/readonly?id='.$id);
        $this->assertSame(204, $res->getStatusCode());

        return $id;
    }

    /**
     * @depends testSetReadonly
     *
     * @param mixed $id
     */
    public function testCreateChildUnderReadonlyCollection($id)
    {
        $name = uniqid();
        $res = $this->request('POST', '/collection?id='.$id.'&name='.$name);
        $this->assertSame(400, $res->getStatusCode());
        $body = $this->jsonBody($res);
        $this->assertSame('Balloon\\Exception\\Conflict', $body['error']);

        return $id;
    }

    /**
     * @depends testCreateChildUnderReadonlyCollection
     *
     * @param mixed $id
     */
    public function testSetWriteable($id)
    {
        $res = $this->request('POST', '/collection/'.$id.'/readonly?readonly=false');
        $this->assertSame(204, $res->getStatusCode());
    }

    /**
     * @depends testSetWriteable
     *
     * @param mixed $id
     */
    public function testCreateChildUnderWriteableCollection($id)
    {
        $name = uniqid();
        $res = $this->request('POST', '/collection/'.$id.'?name='.$name);
        $this->assertSame(201, $res->getStatusCode());
        $body = $this->jsonBody($res);
        $id = new \MongoDB\BSON\ObjectID($body);
        $this->assertInstanceOf('\MongoDB\BSON\ObjectID', $id);
    }
}
