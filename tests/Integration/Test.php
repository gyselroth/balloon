<?php

declare(strict_types=1);

/**
 * Balloon
 *
 * @author      Raffael Sahli <sahli@gyselroth.net>
 * @copyright   Copryright (c) 2012-2017 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Testsuite;

use Exception;
use GuzzleHttp;
use PHPUnit\Framework\TestCase;
use StdClass;

/**
 * @coversNothing
 */
class Test extends TestCase
{
    const API_VERSION = 1;
    protected $type = 'node';

    public function request($method, $resource, $params = [])
    {
        if (array_key_exists('BALLOON_API_HOST', $_SERVER)) {
            $host = $_SERVER['BALLOON_API_HOST'];
        } else {
            throw new Exception('env variable BALLOON_API_HOST not set');
        }
        if (array_key_exists('BALLOON_API_USER', $_SERVER)) {
            $user = $_SERVER['BALLOON_API_USER'];
        } else {
            throw new Exception('env variable BALLOON_API_USER not set');
        }
        if (array_key_exists('BALLOON_API_PW', $_SERVER)) {
            $pw = $_SERVER['BALLOON_API_PW'];
        } else {
            throw new Exception('env variable BALLOON_API_PW not set');
        }

        $url = $host.'/api/v'.self::API_VERSION.$resource;
        $client = new GuzzleHttp\Client();
        $res = $client->request($method, $url, array_merge($params, [
            'http_errors' => false,
            'auth' => [$user, $pw],
        ]));

        return $res;
    }

    public function jsonBody($res)
    {
        $array = json_decode($res->getBody());
        $this->assertInstanceOf('stdClass', $array);
        $array = (array) $array;
        $this->assertSame(true, is_array($array));
        $this->assertArrayHasKey('status', $array);
        $this->assertArrayHasKey('data', $array);
        $this->assertSame($array['status'], $res->getStatusCode());
        $this->assertSame('application/json; charset=utf-8', $res->getHeaderLine('Content-Type'));

        if ($array['data'] instanceof StdClass) {
            return (array) $array['data'];
        }

        return $array['data'];
    }

    public function getLastCursor()
    {
        $res = $this->request('GET', '/node/last-cursor');
        $this->assertSame(200, $res->getStatusCode());
        $body = $this->jsonBody($res);

        $cursor = base64_decode($body, true);
        $parts = explode('|', $cursor);
        $this->assertCount(5, $parts);

        return $body;
    }

    public function getLastDelta($cursor)
    {
        $this->assertNotEmpty($cursor);
        $res = $this->request('GET', '/node/delta?cursor='.$cursor);

        $this->assertSame(200, $res->getStatusCode());
        $body = $this->jsonBody($res);
        $this->assertCount(4, $body);

        $cursor = base64_decode($body['cursor'], true);
        $parts = explode('|', $cursor);
        $this->assertCount(5, $parts);
        $this->assertSame('delta', $parts[0]);
        $this->assertSame('0', $parts[1]);
        $this->assertSame('0', $parts[2]);
        $ts = new \MongoDB\BSON\UTCDateTime($parts[4]);
        $this->assertFalse($body['reset']);
        $this->assertFalse($body['has_more']);
        $this->assertTrue(is_array($body['nodes']));

        return $body;
    }
}
