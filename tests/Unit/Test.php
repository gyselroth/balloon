<?php
namespace Balloon\Testsuite\Unit;

use \PHPUnit\Framework\TestCase;
use \Exception;
use \Balloon\Filesystem;
use \Balloon\Hook;
use \Balloon\App;
use \Balloon\Async;
use \Balloon\Server;
use \Balloon\Testsuite\Unit\Mock;
use \Helmich\MongoMock\MockDatabase;
use \Balloon\App\Delta;
use \Micro\Http\Response;

abstract class Test extends TestCase
{
    protected $type = 'node';

    protected static $first_cursor;
    protected static $current_cursor;
    protected static $controller;
    protected static $logger;

    public static function setupMockServer($context='test')
    {
        $db = new MockDatabase('balloon', [
            'typeMap' => [
                'root' => 'array',
                'document' => 'array',
                'array' => 'array'
            ]
        ]);

        self::registerAppNamespaces();
        self::$logger = new Mock\Log();
        $async = new Async($db, self::$logger);
        $hook  = new Hook(self::$logger);

        $server= new Server($db, self::$logger, $async, $hook);

        $identity   = new Mock\Identity('testuser', [], self::$logger);
        $filesystem = new Filesystem($server, self::$logger);
        $server->addUser(['username' => 'testuser']);
        $server->setIdentity($identity);

        global $composer;
        $app = new App($context, $composer, $server, self::$logger);

        return $server;
    }

    protected static function registerAppNamespaces()
    {
        global $composer;
        foreach(glob(APPLICATION_PATH.DIRECTORY_SEPARATOR.'src'.DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.'*') as $app) {
            $app = basename($app);
            $ns = str_replace('.', '\\', $app).'\\';
            $composer->addPsr4($ns, APPLICATION_PATH."/src/app/$app");
        }
    }

    public function getDelta($cursor=null)
    {
        $res = self::$controller->getDelta(null, null, $cursor);
        $this->assertInstanceOf(Response::class, $res);
        $this->assertEquals(200, $res->getCode());
        return $res->getBody();
    }

    public function getLastCursor()
    {
        $res = self::$controller->getLastCursor();
        $this->assertInstanceOf(Response::class, $res);
        $this->assertEquals(200, $res->getCode());

        return $res->getBody();
    }
}
