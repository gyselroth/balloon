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
use \Balloon\App\Delta;

class Test extends TestCase
{
    protected $type = 'node';

    protected static $first_cursor;
    protected static $current_cursor;
    protected static $controller;

    public static function setupMockServer()
    {
        $db         = new MockDatabase();
        $logger     = new Mock\Log();
        $async      = new Async($db, $logger);
        $hook       = new Hook($logger);
        $hook->registerHook(Delta\Hook::class);

        $server     = new Server($db, $logger, $async, $hook);
        $identity   = new Mock\Identity('testuser', [], $logger);
        $filesystem = new Filesystem($server, $logger);
        $server->addUser(['username' => 'testuser']);  
        $server->setIdentity($identity);
        return $server;
    }

    public function getDelta($cursor=null)
    {
        $res = self::$controller->getDelta(null, null, $cursor);
        $this->assertInstanceOf('\Micro\Http\Response', $res);
        $this->assertEquals(200, $res->getCode());
        return $res->getBody();
    }

    public function getLastCursor()
    {
        $res = self::$controller->getLastCursor();
        $this->assertInstanceOf('\Micro\Http\Response', $res);
        $this->assertEquals(200, $res->getCode());

        //if we have an empty delta collection this would be 4 and not 5
        //$cursor = base64_decode($res->getBody());
        //$parts  = explode('|',$cursor);
        //$this->assertCount(5, $parts);

        return $res->getBody();
    }
}
