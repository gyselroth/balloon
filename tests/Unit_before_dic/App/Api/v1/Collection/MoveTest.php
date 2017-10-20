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
class MoveTest extends Test
{
    protected static $delta = [];
    protected static $server;

    public static function setUpBeforeClass()
    {
        $server = self::setupMockServer();
        self::$controller = new Collection($server, $server->getLogger());
        self::$server = $server;
    }

    public function testReceiveLastDelta()
    {
        self::$first_cursor = $this->getLastCursor();
        self::$current_cursor = self::$first_cursor;
    }

    public function testCreate()
    {
        $name = uniqid();
        $res = self::$controller->post(null, null, $name);
        $this->assertInstanceOf(Response::class, $res);
        $this->assertSame(201, $res->getCode());
        $id = new ObjectID($res->getBody());
        $this->assertInstanceOf(ObjectID::class, $id);

        $delta = $this->getDelta(self::$current_cursor);
        $this->assertCount(1, $delta['nodes']);
        $this->assertSame((string) $id, $delta['nodes'][0]['id']);
        self::$current_cursor = $delta['cursor'];
        self::$delta[] = [
            'id' => (string) $id,
            'deleted' => false,
            'path' => '/'.$name,
        ];

        return [
            'id' => (string) $id,
            'name' => $name,
        ];
    }

    public function testCreate2()
    {
        //self::$first_cursor = $this->getLastCursor();
        return $this->testCreate();
    }

    /**
     * @depends testCreate
     * @expectedExceptionCode 18
     *
     * @param mixed $node
     */
    public function testMoveCollectionIntoItself($node)
    {
        self::$controller->postMove($node['id'], null, $node['id']);
    }

    /**
     * @depends testCreate
     * @expectedExceptionCode 17
     *
     * @param mixed $node
     */
    public function testMoveCollectionIntoSameParent($node)
    {
        self::$controller->postMove($node['id'], null, null);
    }

    /**
     * @depends testCreate
     * @depends testCreate2
     *
     * @param mixed $source
     * @param mixed $dest
     */
    public function testMoveCollectionIntoOtherCollection($source, $dest)
    {
        $res = self::$controller->postMove($source['id'], null, $dest['id']);
        $this->assertInstanceOf(Response::class, $res);
        $this->assertSame(204, $res->getCode());

        $delta = $this->getDelta(self::$current_cursor);
        $this->assertCount(2, $delta['nodes']);
        $this->assertSame((string) $source['id'], $delta['nodes'][0]['id']);
        $this->assertSame((string) $source['id'], $delta['nodes'][1]['id']);
        self::$current_cursor = $delta['cursor'];

        self::$delta[0]['path'] = '/'.$dest['name'].'/'.$source['name'];
        self::$delta[] = [
            'id' => (string) $source['id'],
            'deleted' => true,
            'path' => '/'.$source['name'],
        ];

        return [
            'child' => $source,
            'parent' => $dest,
        ];
    }

    /**
     * @depends testMoveCollectionIntoOtherCollection
     * @expectedExceptionCode 18
     *
     * @param mixed $nodes
     */
    public function testMoveParentIntoChild($nodes)
    {
        self::$controller->postMove($nodes['parent']['id'], null, $nodes['child']['id']);
    }

    public function testCreateA()
    {
        $name = uniqid();
        $res = self::$controller->post(null, null, $name);
        $this->assertInstanceOf(Response::class, $res);
        $this->assertSame(201, $res->getCode());
        $id = new ObjectID($res->getBody());
        $this->assertInstanceOf(ObjectID::class, $id);

        $delta = $this->getDelta(self::$current_cursor);
        $this->assertCount(1, $delta['nodes']);
        $this->assertSame((string) $id, $delta['nodes'][0]['id']);
        self::$current_cursor = $delta['cursor'];

        self::$delta[] = [
            'id' => (string) $id,
            'deleted' => false,
            'path' => '/'.$name,
        ];

        return [
            'id' => (string) $id,
            'name' => $name,
        ];
    }

    /**
     * @depends testCreateA
     *
     * @param mixed $a
     */
    public function testCreateB($a)
    {
        $name = uniqid();
        $res = self::$controller->post(null, null, $name);
        $this->assertInstanceOf(Response::class, $res);
        $this->assertSame(201, $res->getCode());
        $id = new ObjectID($res->getBody());
        $this->assertInstanceOf(ObjectID::class, $id);

        $delta = $this->getDelta(self::$current_cursor);
        $this->assertCount(1, $delta['nodes']);
        $this->assertSame((string) $id, $delta['nodes'][0]['id']);
        self::$current_cursor = $delta['cursor'];

        self::$delta[] = [
            'id' => (string) $id,
            'deleted' => false,
            'path' => '/'.$name,
        ];

        return [
            'a' => $a,
            'b' => [
                'id' => (string) $id,
                'name' => $name,
            ],
        ];
    }

    /**
     * @depends testCreateB
     *
     * @param mixed $nodes
     */
    public function testCreateAUnderB($nodes)
    {
        $res = self::$controller->post($nodes['b']['id'], null, $nodes['a']['name']);
        $this->assertInstanceOf(Response::class, $res);
        $this->assertSame(201, $res->getCode());
        $id = new ObjectID($res->getBody());
        $this->assertInstanceOf(ObjectID::class, $id);

        $delta = $this->getDelta(self::$current_cursor);
        $this->assertCount(1, $delta['nodes']);
        $this->assertSame((string) $id, $delta['nodes'][0]['id']);
        self::$current_cursor = $delta['cursor'];

        self::$delta[] = [
            'id' => (string) $id,
            'deleted' => false,
            'path' => '/'.$nodes['b']['name'].'/'.$nodes['a']['name'],
        ];

        $nodes['a2'] = [
            'id' => $id,
            'name' => $nodes['a']['name'],
        ];

        return $nodes;
    }

    /**
     * @depends testCreateAUnderB
     * @expectedExceptionCode 19
     *
     * @param mixed $nodes
     */
    public function testMoveAToBConflict($nodes)
    {
        self::$controller->postMove($nodes['a']['id'], null, $nodes['b']['id']);
    }

    /**
     * @depends testCreateAUnderB
     *
     * @param mixed $nodes
     */
    public function testMoveAToBResolvedConflictMerge($nodes)
    {
        $res = self::$controller->postMove($nodes['a']['id'], null, $nodes['b']['id'], null, 2);
        $this->assertInstanceOf(Response::class, $res);
        $this->assertSame(204, $res->getCode());
        $delta = $this->getDelta(self::$current_cursor);

        $this->assertCount(2, $delta['nodes']);
        $path_a_under_b = '/'.$nodes['b']['name'].'/'.$nodes['a']['name'];

        $this->assertSame((string) $nodes['a2']['id'], $delta['nodes'][0]['id']);
        $this->assertSame($path_a_under_b, $delta['nodes'][0]['path']);
        $this->assertFalse($delta['nodes'][0]['deleted']);

        $this->assertSame((string) $nodes['a']['id'], $delta['nodes'][1]['id']);
        $this->assertSame('/'.$nodes['a']['name'], $delta['nodes'][1]['path']);
        $this->assertTrue($delta['nodes'][1]['deleted']);

        self::$current_cursor = $delta['cursor'];
        self::$delta[3]['deleted'] = true;
    }

    public function testMoveAToBResolvedConflictRename()
    {
        $nodes = $this->testCreateAUnderB($this->testCreateB($this->testCreateA()));
        $res = self::$controller->postMove($nodes['a']['id'], null, $nodes['b']['id'], null, 1);
        $this->assertInstanceOf(Response::class, $res);
        $this->assertSame(200, $res->getCode());
        $body = $res->getBody();

        $delta = $this->getDelta(self::$current_cursor);
        $this->assertCount(3, $delta['nodes']);

        $this->assertSame((string) $nodes['a']['id'], $delta['nodes'][0]['id']);
        $this->assertSame('/'.$nodes['a']['name'], $delta['nodes'][0]['path']);
        $this->assertTrue($delta['nodes'][0]['deleted']);

        $this->assertSame((string) $nodes['a']['id'], $delta['nodes'][1]['id']);
        $this->assertSame('/'.$body, $delta['nodes'][2]['path']);
        $this->assertTrue($delta['nodes'][2]['deleted']);

        $this->assertSame((string) $nodes['a']['id'], $delta['nodes'][1]['id']);
        $this->assertSame('/'.$nodes['b']['name'].'/'.$body, $delta['nodes'][1]['path']);
        $this->assertFalse($delta['nodes'][1]['deleted']);

        self::$delta[] = [
            'id' => (string) $nodes['a']['id'],
            'deleted' => true,
            'path' => self::$delta[6]['path'],
        ];

        self::$delta[6]['path'] = '/'.$nodes['b']['name'].'/'.$body;

        self::$delta[] = [
            'id' => (string) $nodes['a']['id'],
            'deleted' => true,
            'path' => '/'.$body,
        ];
    }

    public function testDelta()
    {
        $delta = $this->getDelta(self::$first_cursor);
        $this->assertCount(count(self::$delta), $delta['nodes']);

        foreach ($delta['nodes'] as $key => $node) {
            $node = (array) $node;
            $this->assertSame($node['id'], self::$delta[$key]['id']);
            $this->assertSame($node['path'], self::$delta[$key]['path']);
            $this->assertSame($node['deleted'], self::$delta[$key]['deleted']);

            if (array_key_exists('directoy', $node)) {
                $this->assertTrue($node['directory']);
            }
        }
    }
}
