<?php
namespace Balloon\Testsuite\Unit\App\Api\v1;

use \Exception;
use \Balloon\Testsuite\Unit\Test as UnitTest;
use \Balloon\Filesystem;
use \Balloon\Hook;
use \Balloon\App;
use \Balloon\Async;
use \Balloon\Server;
use \Balloon\Testsuite\Unit\Mock;
use \Helmich\MongoMock\MockDatabase;
use \Balloon\App\Delta;
use \Micro\Http\Response;

abstract class Test extends UnitTest
{
    public static function setupMockServer()
    {
        $server = parent::setupMockServer();
        $server->getHook()->registerHook(Delta\Hook::class);
        return $server;
    }
}
