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
use GuzzleHttp;

/**
 * @coversNothing
 */
class GlobalShareLinkTest extends Test
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
    public function testCreateShareLink($id)
    {
        $res = $this->request('POST', '/collection/share-link?id='.$id);
        $this->assertSame(204, $res->getStatusCode());

        return $id;
    }

    /**
     * @depends testCreateShareLink
     *
     * @param mixed $node
     */
    public function testGetShareLink($node)
    {
        $res = $this->request('GET', '/collection/'.$node.'/share-link');
        $this->assertSame(200, $res->getStatusCode());
        $body = $this->jsonBody($res);
        $this->assertArrayHasKey('token', $body);

        return [
            'token' => $body['token'],
            'id' => $node,
        ];
    }

    /**
     * @depends testGetShareLink
     *
     * @param mixed $node
     */
    public function testVerifyShareLink($node)
    {
        $client = new GuzzleHttp\Client();
        $res = $client->request('GET', $_SERVER['BALLOON_API_HOST'].'/share?t='.$node['token'], ['http_errors' => false]);
        $this->assertSame(200, $res->getStatusCode());
        $this->assertSame('application/zip', $res->getHeaderLine('Content-Type'));
        $this->assertNotEmpty($res->getHeaderLine('Content-Disposition'));

        return $node;
    }

    /**
     * @depends testVerifyShareLink
     *
     * @param mixed $node
     */
    public function testDeleteShareLink($node)
    {
        $res = $this->request('DELETE', '/collection/share-link?id='.$node['id']);
        $this->assertSame(204, $res->getStatusCode());

        return $node;
    }

    /**
     * @depends testDeleteShareLink
     *
     * @param mixed $node
     */
    public function testCheckIfShareLinkIsDeleted($node)
    {
        $client = new GuzzleHttp\Client();
        $res = $client->request('GET', $_SERVER['BALLOON_API_HOST'].'/share?t='.$node['token'], ['http_errors' => false]);
        $this->assertSame(404, $res->getStatusCode());
    }

    /**
     * @depends testCreate
     *
     * @param mixed $id
     */
    public function testCreateExpiredShareLink($id)
    {
        $res = $this->request('POST', '/collection/share-link?id='.$id, ['form_params' => ['options' => ['expiration' => 1999999]]]);
        $this->assertSame(204, $res->getStatusCode());
        $res = $this->request('GET', '/collection/'.$id.'/share-link');
        $this->assertSame(200, $res->getStatusCode());
        $body = $this->jsonBody($res);

        return [
            'id' => $id,
            'token' => $body['token'],
        ];
    }

    /**
     * @depends testCreateExpiredShareLink
     *
     * @param mixed $node
     */
    public function testIfShareLinkIsExpired($node)
    {
        $client = new GuzzleHttp\Client();
        $res = $client->request('GET', $_SERVER['BALLOON_API_HOST'].'/share?t='.$node['token'], ['http_errors' => false]);
        $this->assertSame(404, $res->getStatusCode());
    }
}
