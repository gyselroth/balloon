<?php
namespace Balloon\Testsuite\Unit;

use \PHPUnit\Framework\TestCase;
use \Exception;
use \Balloon\Filesystem;
use \Balloon\Hook;
use \Balloon\Async;
use \Balloon\Server;
use \Balloon\Testsuite\Unit\Mock;
use \Helmich\MongoMock\MockDatabase;

class Test extends TestCase
{
    protected $type = 'node';

    public static function setupMockServer()
    {
        $db         = new MockDatabase();
        $logger     = new Mock\Log();
        $async      = new Async($db, $logger);
        $hook       = new Hook($logger);
        $server     = new Server($db, $logger, $async, $hook);
        $identity   = new Mock\Identity('testuser', [], $logger);
        $filesystem = new Filesystem($server, $logger);
        $server->addUser(['username' => 'testuser']);  
        $server->setIdentity($identity);

        #$this->fs = $filesystem;
        return $server;
    }

    public function jsonBody($res)
    {
        /*$array = json_decode($res->getBody());
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
        }*/
    }

    public function getLastCursor()
    {
        //return $body;
    }

    public function getLastDelta($cursor)
    {
        //return $body;
    }
}
