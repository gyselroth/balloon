<?php
namespace Balloon\Testsuite\Unit\Api\Collection;

use Balloon\Testsuite\Unit\Test;
use \GuzzleHttp;

class GlobalShareLinkTest extends Test
{
    public function testCreate()
    {
        $name = uniqid();
        $res = $this->request('POST', '/collection?name='.$name);
        $this->assertEquals(201, $res->getStatusCode());
        $body = $this->jsonBody($res);
        $id = new \MongoDB\BSON\ObjectID($body);
        $this->assertInstanceOf('\MongoDB\BSON\ObjectID', $id);
        return $id;
    }

    /**
     * @depends testCreate
     */
    public function testCreateShareLink($id)
    {
        $res = $this->request('POST', '/collection/share-link?id='.$id);
        $this->assertEquals(204, $res->getStatusCode());
        return $id;
    }
    

    /**
     * @depends testCreateShareLink
     */
    public function testGetShareLink($node)
    {
        $res = $this->request('GET', '/collection/'.$node.'/share-link');
        $this->assertEquals(200, $res->getStatusCode());
        $body = $this->jsonBody($res);
        $this->assertArrayHasKey('token', $body);
        return [
            'token' => $body['token'],
            'id'    => $node,
        ];
    }
    
    /**
     * @depends testGetShareLink
     */
    public function testVerifyShareLink($node)
    {
        $client = new GuzzleHttp\Client();
        $res = $client->request('GET', $_SERVER['BALLOON_API_HOST'].'/share?t='.$node['token'], ['http_errors' => false]);
        $this->assertEquals(200, $res->getStatusCode());
        $this->assertEquals('application/zip', $res->getHeaderLine('Content-Type'));
        $this->assertNotEmpty($res->getHeaderLine('Content-Disposition'));
        return $node;
    }    

    /**
     * @depends testVerifyShareLink
     */
    public function testDeleteShareLink($node)
    {
        $res = $this->request('DELETE', '/collection/share-link?id='.$node['id']);
        $this->assertEquals(204, $res->getStatusCode());
        return $node;
    }
    
    /**
     * @depends testDeleteShareLink
     */
    public function testCheckIfShareLinkIsDeleted($node)
    {
        $client = new GuzzleHttp\Client();
        $res = $client->request('GET', $_SERVER['BALLOON_API_HOST'].'/share?t='.$node['token'], ['http_errors' => false]);
        $this->assertEquals(404, $res->getStatusCode());
    }

    /**
     * @depends testCreate
     */
    public function testCreateExpiredShareLink($id)
    {
        $res = $this->request('POST', '/collection/share-link?id='.$id, ['form_params' => ['options' => ['expiration' => 1999999]]]);
        $this->assertEquals(204, $res->getStatusCode());
        $res = $this->request('GET', '/collection/'.$id.'/share-link');
        $this->assertEquals(200, $res->getStatusCode());
        $body = $this->jsonBody($res);

        return [
            'id'  => $id,
            'token' => $body['token']
        ];
    }
    
    /**
     * @depends testCreateExpiredShareLink
     */
    public function testIfShareLinkIsExpired($node)
    {
        $client = new GuzzleHttp\Client();
        $res = $client->request('GET', $_SERVER['BALLOON_API_HOST'].'/share?t='.$node['token'], ['http_errors' => false]);
        $this->assertEquals(404, $res->getStatusCode());
    }
}
