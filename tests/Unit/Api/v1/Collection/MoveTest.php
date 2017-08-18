<?php
namespace Balloon\Testsuite\Unit\Api\Collection;

use \Balloon\Testsuite\Unit\Test;
use \Balloon\Api\v1\Collection;
use \Micro\Http\Response;
use \MongoDB\BSON\ObjectID;

class MoveTest extends Test
{
    protected static $delta = [];

    public static function setUpBeforeClass()
    {
        $server = self::setupMockServer();
        self::$controller = new Collection($server, $server->getLogger());
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
        $this->assertEquals(201, $res->getCode());
        $id = new ObjectID($res->getBody());
        $this->assertInstanceOf(ObjectID::class, $id);

        $delta = $this->getDelta(self::$current_cursor);
        $this->assertCount(1, $delta['nodes']);
        $this->assertEquals((string)$id, $delta['nodes'][0]['id']);
        self::$current_cursor = $delta['cursor'];
        self::$delta[] = [
            'id'        => (string)$id,
            'deleted'   => false,
            'path'      => '/'.$name
        ];
        
        return [
            'id'  => (string)$id,
            'name'=> $name
        ];
    }

    public function testCreate2()
    {
        #self::$first_cursor = $this->getLastCursor();
        return $this->testCreate();
    }
    
    /**
     * @depends testCreate
     * @expectedException \Balloon\Exception\Conflict
     * @expectedExceptionCode 18
     */
    public function testMoveCollectionIntoItself($node)
    {
        self::$controller->postMove($node['id'], null, $node['id']);
    }
    
    /**
     * @depends testCreate
     * @expectedException \Balloon\Exception\Conflict
     * @expectedExceptionCode 17
     */
    public function testMoveCollectionIntoSameParent($node)
    {
        self::$controller->postMove($node['id'], null, null);
    }

    /**
     * @depends testCreate
     * @depends testCreate2
     */
    public function testMoveCollectionIntoOtherCollection($source, $dest)
    {
        $res = self::$controller->postMove($source['id'], null, $dest['id']);
        $this->assertInstanceOf(Response::class, $res);
        $this->assertEquals(204, $res->getCode());
        
        $delta = $this->getDelta(self::$current_cursor);
        $this->assertCount(2, $delta['nodes']);
        $this->assertEquals((string)$source['id'], $delta['nodes'][0]['id']);
        $this->assertEquals((string)$source['id'], $delta['nodes'][1]['id']);
        self::$current_cursor = $delta['cursor'];

        self::$delta[0]['path'] = '/'.$dest['name'].'/'.$source['name'];
        self::$delta[] = [
            'id'      => (string)$source['id'],
            'deleted' => true,
            'path'    => '/'.$source['name'] 
        ];

        return [
            'child' => $source,
            'parent'=> $dest,
        ];
    }

    /**
     * @depends testMoveCollectionIntoOtherCollection
     * @expectedException \Balloon\Exception\Conflict
     * @expectedExceptionCode 18
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
        $this->assertEquals(201, $res->getCode());
        $id = new ObjectID($res->getBody());
        $this->assertInstanceOf(ObjectID::class, $id);
        
        $delta = $this->getDelta(self::$current_cursor);
        $this->assertCount(1, $delta['nodes']);
        $this->assertEquals((string)$id, $delta['nodes'][0]['id']);
        self::$current_cursor = $delta['cursor'];
        
        self::$delta[] = [
            'id'        => (string)$id,
            'deleted'   => false,
            'path'      => '/'.$name
        ];

        return [
            'id'   => (string)$id,
            'name' => $name,
        ];
    }
    
    /**
     * @depends testCreateA
     */
    public function testCreateB($a)
    {
        $name = uniqid();
        $res = self::$controller->post(null, null, $name);
        $this->assertInstanceOf(Response::class, $res);
        $this->assertEquals(201, $res->getCode());
        $id = new ObjectID($res->getBody());
        $this->assertInstanceOf(ObjectID::class, $id);
        
        $delta = $this->getDelta(self::$current_cursor);
        $this->assertCount(1, $delta['nodes']);
        $this->assertEquals((string)$id, $delta['nodes'][0]['id']);
        self::$current_cursor = $delta['cursor'];
        
        self::$delta[] = [
            'id'        => (string)$id,
            'deleted'   => false,
            'path'      => '/'.$name
        ];
        
        return [
            'a' => $a,
            'b' => [
                'id'   => (string)$id,
                'name' => $name,
            ]
        ];
    }
    
    /**
     * @depends testCreateB
     */
    public function testCreateAUnderB($nodes)
    {
        $res = self::$controller->post($nodes['b']['id'], null, $nodes['a']['name']);
        $this->assertInstanceOf(Response::class, $res);
        $this->assertEquals(201, $res->getCode());
        $id = new ObjectID($res->getBody());
        $this->assertInstanceOf(ObjectID::class, $id);
       
        $delta = $this->getDelta(self::$current_cursor);
        $this->assertCount(1, $delta['nodes']);
        $this->assertEquals((string)$id, $delta['nodes'][0]['id']);
        self::$current_cursor = $delta['cursor'];
        
        self::$delta[] = [
            'id'        => (string)$id,
            'deleted'   => false,
            'path'      => '/'.$nodes['b']['name'].'/'.$nodes['a']['name']
        ];

        $nodes['a2'] = [
            'id'   => $id,
            'name' => $nodes['a']['name']
        ];

        return $nodes;
    }

    /**
     * @depends testCreateAUnderB
     * @expectedException \Balloon\Exception\Conflict
     * @expectedExceptionCode 19
     */
    public function testMoveAToBConflict($nodes)
    {
        self::$controller->postMove($nodes['a']['id'], null, $nodes['b']['id']);
    }

    /**
     * @depends testCreateAUnderB
     */
    public function testMoveAToBResolvedConflictMerge($nodes)
    {
        $res = self::$controller->postMove($nodes['a']['id'], null, $nodes['b']['id'], null, 2);
        $this->assertInstanceOf(Response::class, $res);
        $this->assertEquals(204, $res->getCode());
        $delta = $this->getDelta(self::$current_cursor);

        $this->assertCount(2, $delta['nodes']);
        $path_a_under_b = '/'.$nodes['b']['name'].'/'.$nodes['a']['name'];        

        $this->assertEquals((string)$nodes['a2']['id'], $delta['nodes'][0]['id']);
        $this->assertEquals($path_a_under_b, $delta['nodes'][0]['path']);
        $this->assertFalse($delta['nodes'][0]['deleted']);

        $this->assertEquals((string)$nodes['a']['id'], $delta['nodes'][1]['id']);
        $this->assertEquals('/'.$nodes['a']['name'], $delta['nodes'][1]['path']);
        $this->assertTrue($delta['nodes'][1]['deleted']);

        self::$current_cursor = $delta['cursor'];
        self::$delta[3]['deleted'] = true;
    }

    public function testMoveAToBResolvedConflictRename()
    {
        $nodes = $this->testCreateAUnderB($this->testCreateB($this->testCreateA()));
        $res = self::$controller->postMove($nodes['a']['id'], null, $nodes['b']['id'], null, 1);
        $this->assertInstanceOf(Response::class, $res);
        $this->assertEquals(200, $res->getCode());
        $body = $res->getBody();

        $delta = $this->getDelta(self::$current_cursor);
        $this->assertCount(3, $delta['nodes']);

        $this->assertEquals((string)$nodes['a']['id'], $delta['nodes'][0]['id']);
        $this->assertEquals('/'.$nodes['a']['name'], $delta['nodes'][0]['path']);
        $this->assertTrue($delta['nodes'][0]['deleted']);
        
        $this->assertEquals((string)$nodes['a']['id'], $delta['nodes'][1]['id']);
        $this->assertEquals('/'.$body, $delta['nodes'][2]['path']);
        $this->assertTrue($delta['nodes'][2]['deleted']);

        $this->assertEquals((string)$nodes['a']['id'], $delta['nodes'][1]['id']);
        $this->assertEquals('/'.$nodes['b']['name'].'/'.$body, $delta['nodes'][1]['path']);
        $this->assertFalse($delta['nodes'][1]['deleted']);
        
        self::$delta[] = [
            'id'        => (string)$nodes['a']['id'],
            'deleted'   => true,
            'path'      => self::$delta[6]['path']
        ];

        self::$delta[6]['path'] =  '/'.$nodes['b']['name'].'/'.$body;

        self::$delta[] = [
            'id'        => (string)$nodes['a']['id'],
            'deleted'   => true,
            'path'      => '/'.$body
        ];
    }

    public function testDelta()
    {
        $delta = $this->getDelta(self::$first_cursor);
        $this->assertCount(count(self::$delta), $delta['nodes']);

        foreach($delta['nodes'] as $key => $node) {
            $node = (array)$node;
            $this->assertEquals($node['id'], self::$delta[$key]['id']);
            $this->assertEquals($node['path'], self::$delta[$key]['path']);
            $this->assertEquals($node['deleted'], self::$delta[$key]['deleted']);

            if(array_key_exists('directoy', $node)) {
                $this->assertTrue($node['directory']);
            }
        }
    }
}
