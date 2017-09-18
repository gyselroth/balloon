<?php
namespace Balloon\Testsuite\Unit\Bootstrap;

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

class Http extends AbstractBootstrap
{
    public static function setupMockServer()
    {
        $server = parent::setupMockServer();
        global $composer;

        $app   = new App(App::CONTEXT_HTTP, $composer, $server, self::$logger, self::$app
            'Balloon.App.Delta' => [],  
            'Balloon.App.Sharelink' => []
        ]);

        return $server;
    }
}
