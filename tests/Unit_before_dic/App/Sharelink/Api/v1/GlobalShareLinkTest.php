<?php

declare(strict_types=1);

/**
 * Balloon
 *
 * @author      Raffael Sahli <sahli@gyselroth.net>
 * @copyright   Copryright (c) 2012-2017 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Testsuite\Unit\App\Sharelink\Api\v1;

use Balloon\Api\v1\Collection;
use Balloon\App;
use Balloon\App\Sharelink\Http as ShareLiunkApp;
use Balloon\App\Sharelink\Api\v1\ShareLink;
use Balloon\Testsuite\Unit\Test;
use Micro\Http\Response;
use MongoDB\BSON\ObjectID;

/**
 * @coversNothing
 */
class GlobalShareLinkTest extends Test
{
    protected static $sharelink;

    public static function setUpBeforeClass()
    {
        $server = self::setupMockServer('http');

        $router = new Router(self::$logger);
        $app = new ShareLinkApp($router, $hook, $server);

        self::$sharelink = new ShareLink($app, $server, $server->getLogger());
        self::$controller = new Collection($server, $server->getLogger());
    }

    public function testCreate()
    {
        $name = uniqid();
        $res = self::$controller->post(null, null, $name);
        $this->assertInstanceOf(Response::class, $res);
        $this->assertSame(201, $res->getCode());
        $id = new ObjectID($res->getBody());
        $this->assertInstanceOf(ObjectID::class, $id);

        return (string) $id;
    }

    /**
     * @depends testCreate
     *
     * @param mixed $id
     */
    public function testCreateShareLink($id)
    {
        $res = self::$sharelink->post($id);
        $this->assertInstanceOf(Response::class, $res);
        $this->assertSame(204, $res->getCode());

        return $id;
    }

    /**
     * @depends testCreateShareLink
     *
     * @param mixed $id
     */
    public function testGetShareLink($id)
    {
        $res = self::$sharelink->get($id);
        $this->assertInstanceOf(Response::class, $res);
        $this->assertSame(200, $res->getCode());
        $this->assertArrayHasKey('token', $res->getBody());

        return [
            'token' => $res->getBody()['token'],
            'id' => $id,
        ];
    }

    /**
     * @depends testGetShareLink
     *
     * @param mixed $node
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
     *
     * @param mixed $node
     */
    public function testDeleteShareLink($node)
    {
        $res = self::$sharelink->delete($node['id']);
        $this->assertInstanceOf(Response::class, $res);
        $this->assertSame(204, $res->getCode());

        return $node;
    }

    /**
     * @depends testDeleteShareLink
     *
     * @param mixed $node
     */
    public function testCheckIfShareLinkIsDeleted($node)
    {
        /*$client = new GuzzleHttp\Client();
        $res = $client->request('GET', $_SERVER['BALLOON_API_HOST'].'/share?t='.$node['token'], ['http_errors' => false]);
        $this->assertEquals(404, $res->getStatusCode());*/
    }

    /**
     * @depends testCreate
     *
     * @param mixed $id
     */
    public function testCreateExpiredShareLink($id)
    {
        $res = self::$sharelink->post($id, null, ['expiration' => '1999999']);
        $this->assertInstanceOf(Response::class, $res);
        $this->assertSame(204, $res->getCode());
        $res = self::$sharelink->get($id);
        $this->assertInstanceOf(Response::class, $res);
        $this->assertSame(200, $res->getCode());

        return [
            'id' => $id,
            'token' => $res->getBody()['token'],
        ];
    }

    /**
     * @depends testCreateExpiredShareLink
     *
     * @param mixed $node
     */
    public function testIfShareLinkIsExpired($node)
    {
        /*$client = new GuzzleHttp\Client();
        $res = $client->request('GET', $_SERVER['BALLOON_API_HOST'].'/share?t='.$node['token'], ['http_errors' => false]);
        $this->assertEquals(404, $res->getStatusCode());*/
    }
}
