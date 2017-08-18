<?php
namespace Balloon\Testsuite\Unit\Api\Collection;

use \Balloon\Testsuite\Unit\Test;
use \Balloon\Api\v1\Collection;
use \Micro\Http\Response;
use \MongoDB\BSON\ObjectID;

class GlobalShareLinkTest extends Test
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
        $this->assertEquals(201, $res->getCode());
        $id = new ObjectID($res->getBody());
        $this->assertInstanceOf(ObjectID::class, $id);
        return (string)$id;
    }

    /**
     * @depends testCreate
     */
    public function testCreateShareLink($id)
    {
        $res = self::$controller->postShareLink($id);
        $this->assertInstanceOf(Response::class, $res);
        $this->assertEquals(204, $res->getCode());
        return $id;
    }
    

    /**
     * @depends testCreateShareLink
     */
    public function testGetShareLink($id)
    {
        $res = self::$controller->getShareLink($id);
        $this->assertInstanceOf(Response::class, $res);
        $this->assertEquals(200, $res->getCode());
        $this->assertArrayHasKey('token', $res->getBody());
        return [
            'token' => $res->getBody()['token'],
            'id'    => $id,
        ];
    }
    
    /**
     * @depends testGetShareLink
     */
    public function testVerifyShareLink($node)
    {
        /*$client = new GuzzleHttp\Client();
        $res = $client->request('GET', $_SERVER['BALLOON_API_HOST'].'/share?t='.$node['token'], ['http_errors' => false]);
        $this->assertEquals(200, $res->getStatusCode());
        $this->assertEquals('application/zip', $res->getHeaderLine('Content-Type'));
        $this->assertNotEmpty($res->getHeaderLine('Content-Disposition'));
        return $node;*/
        return $node;
    }  

    /**
     * @depends testVerifyShareLink
     */
    public function testDeleteShareLink($node)
    {
        $res = self::$controller->deleteShareLink($node['id']);
        $this->assertInstanceOf(Response::class, $res);
        $this->assertEquals(204, $res->getCode());
        return $node;
    }
    
    /**
     * @depends testDeleteShareLink
     */
    public function testCheckIfShareLinkIsDeleted($node)
    {
        /*$client = new GuzzleHttp\Client();
        $res = $client->request('GET', $_SERVER['BALLOON_API_HOST'].'/share?t='.$node['token'], ['http_errors' => false]);
        $this->assertEquals(404, $res->getStatusCode());*/
    }

    /**
     * @depends testCreate
     */
    public function testCreateExpiredShareLink($id)
    {
        $res = self::$controller->postShareLink($id, null, ['expiration' => '1999999']);
        $this->assertInstanceOf(Response::class, $res);
        $this->assertEquals(204, $res->getCode());
        $res = self::$controller->getShareLink($id);
        $this->assertInstanceOf(Response::class, $res);
        $this->assertEquals(200, $res->getCode());
        return [
            'id'  => $id,
            'token' => $res->getBody()['token']
        ];
    }
    
    /**
     * @depends testCreateExpiredShareLink
     */
    public function testIfShareLinkIsExpired($node)
    {
        /*$client = new GuzzleHttp\Client();
        $res = $client->request('GET', $_SERVER['BALLOON_API_HOST'].'/share?t='.$node['token'], ['http_errors' => false]);
        $this->assertEquals(404, $res->getStatusCode());*/
    }
}
