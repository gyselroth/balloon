<?php
namespace Balloon\Testsuite\Unit\App\Sharelink\Api\v1;

use \Balloon\Testsuite\Unit\Test;
use \Balloon\Testsuite\Unit\App\Sharelink\HttpApp;
use \Micro\Http\Response;
use \MongoDB\BSON\ObjectID;
use \Balloon\App\Sharelink\Api\v1\ShareLink;
use \Balloon\Api\v1\Collection;
use \Balloon\App;

class GlobalShareLinkTest extends Test
{
    protected static $sharelink;

    public static function setUpBeforeClass()
    {
        $server = self::setupMockServer(App::CONTEXT_HTTP);
        self::$sharelink  = new ShareLink($server, $server->getLogger());
        self::$controller = new Collection($server, $server->getLogger());
        var_dump($server->getApp()->registerApp('Balloon.App.Sharelink'));
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
        $res = self::$sharelink->post($id);
        $this->assertInstanceOf(Response::class, $res);
        $this->assertEquals(204, $res->getCode());
        return $id;
    }


    /**
     * @depends testCreateShareLink
     */
    public function testGetShareLink($id)
    {
        $res = self::$sharelink->get($id);
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
        $res = self::$sharelink->delete($node['id']);
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
        $res = self::$sharelink->post($id, null, ['expiration' => '1999999']);
        $this->assertInstanceOf(Response::class, $res);
        $this->assertEquals(204, $res->getCode());
        $res = self::$sharelink->get($id);
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
