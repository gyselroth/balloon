<?php
namespace Balloon\Testsuite;

use \PHPUnit\Framework\TestCase;
use \GuzzleHttp;
use \StdClass;
use \Exception;

class Test extends TestCase
{
    protected $type = 'node';

    const API_VERSION  = 1;

    public function request($method, $resource, $params=[])
    {
        if(array_key_exists('BALLOON_API_HOST', $_SERVER)) {
           $host = $_SERVER['BALLOON_API_HOST'];
        } else {
            throw new Exception('env variable BALLOON_API_HOST not set');
        }
        if(array_key_exists('BALLOON_API_USER', $_SERVER)) {
           $user = $_SERVER['BALLOON_API_USER'];
        } else {
            throw new Exception('env variable BALLOON_API_USER not set');
        }
        if(array_key_exists('BALLOON_API_PW', $_SERVER)) {
           $pw = $_SERVER['BALLOON_API_PW'];
        } else {
            throw new Exception('env variable BALLOON_API_PW not set');
        }

        $url = $host.'/api/v'.self::API_VERSION.$resource;
        $client = new GuzzleHttp\Client();
        $res = $client->request($method, $url, array_merge($params,[
            'http_errors' => false,
            'auth' => [$user, $pw]
        ]));

        return $res;
    }

    public function jsonBody($res)
    {
        $array = json_decode($res->getBody());
        $this->assertInstanceOf('stdClass', $array);
        $array = (array)$array;
        $this->assertEquals(true, is_array($array));
        $this->assertArrayHasKey('status', $array);
        $this->assertArrayHasKey('data', $array);
        $this->assertEquals($array['status'], $res->getStatusCode());
        $this->assertEquals('application/json; charset=utf-8', $res->getHeaderLine('Content-Type'));

        if($array['data'] instanceof StdClass) {
            return (array)$array['data'];
        } else {
            return $array['data'];
        }
    }

    public function getLastCursor()
    {
        $res = $this->request('GET', '/node/last-cursor');
        $this->assertEquals(200, $res->getStatusCode());
        $body = $this->jsonBody($res);
        
        $cursor = base64_decode($body);
        $parts  = explode('|',$cursor);
        $this->assertCount(5, $parts);

        return $body;
    }

    public function getLastDelta($cursor)
    {
        $this->assertNotEmpty($cursor);
        $res = $this->request('GET', '/node/delta?cursor='.$cursor);
        
        $this->assertEquals(200, $res->getStatusCode());
        $body = $this->jsonBody($res);
        $this->assertCount(4, $body);
        
        $cursor = base64_decode($body['cursor']);
        $parts  = explode('|',$cursor);
        $this->assertCount(5, $parts);
        $this->assertEquals('delta',$parts[0]);
        $this->assertEquals('0',$parts[1]);
        $this->assertEquals('0',$parts[2]);
        $ts = new \MongoDB\BSON\UTCDateTime($parts[4]);
        $this->assertFalse($body['reset']);
        $this->assertFalse($body['has_more']);
        $this->assertTrue(is_array($body['nodes']));
        
        return $body;
    }
}
