<?php

declare(strict_types=1);

/**
 * Balloon
 *
 * @author      Raffael Sahli <sahli@gyselroth.net>
 * @copyright   Copryright (c) 2012-2017 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Testsuite\Unit;

use Balloon\App;
use Balloon\Async;
use Balloon\Filesystem;
use Balloon\Filesystem\Storage;
use Balloon\Hook;
use Balloon\Server;
use Helmich\MongoMock\MockDatabase;
use Micro\Http\Response;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

abstract class Test extends TestCase
{
    protected $type = 'node';

    protected static $first_cursor;
    protected static $current_cursor;
    protected static $controller;
    protected static $logger;
    protected static $db;

    public static function getMockDatabase()
    {
        if(self::$db instanceof MockDatabase) {
            return self::$db;
        }

        return self::$db = new MockDatabase('balloon', [
            'typeMap' => [
                'root' => 'array',
                'document' => 'array',
                'array' => 'array',
            ],
        ]);
    }


    public static function setupMockServer($context = 'test')
    {
        $db = new MockDatabase('balloon', [
            'typeMap' => [
                'root' => 'array',
                'document' => 'array',
                'array' => 'array',
            ],
        ]);

        self::registerAppNamespaces();
        self::$logger = new Mock\Log();
        $async = new Async($db, self::$logger);
        $hook = new Hook(self::$logger);
        $storage = new Storage(self::$logger);

        global $composer;
        $app = new App($composer, self::$logger, $hook);

        $server = new Server($db, $storage, self::$logger, $async, $hook, $app);

        $identity = new Mock\Identity('testuser', [], self::$logger);
        $filesystem = new Filesystem($server, self::$logger);
        $server->addUser(['username' => 'testuser']);
        $server->setIdentity($identity);

        return $server;
    }

    public function getMockServer()
    {
        global $composer;
        $app = new App($composer,  $this->createMock(LoggerInterface::class), $this->createMock(Hook::class));

        $server = new Server(
            self::getMockDatabase(),
            $this->createMock(Storage::class),
            $this->createMock(LoggerInterface::class),
            $this->createMock(Async::class),
            $this->createMock(Hook::class),
            $app
        );

        $identity = new Mock\Identity('testuser', [], $this->createMock(LoggerInterface::class));
        $filesystem = new Filesystem($server, $this->createMock(LoggerInterface::class));
        $server->addUser(['username' => 'testuser']);
        $server->setIdentity($identity);

        return $server;
    }

    public function getDelta($cursor = null)
    {
        $res = self::$controller->getDelta(null, null, $cursor);
        $this->assertInstanceOf(Response::class, $res);
        $this->assertSame(200, $res->getCode());

        return $res->getBody();
    }

    public function getLastCursor()
    {
        $res = self::$controller->getLastCursor();
        $this->assertInstanceOf(Response::class, $res);
        $this->assertSame(200, $res->getCode());

        return $res->getBody();
    }

    protected static function registerAppNamespaces()
    {
        global $composer;
        foreach (glob(APPLICATION_PATH.DIRECTORY_SEPARATOR.'src'.DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.'*') as $app) {
            $app = basename($app);
            $ns = str_replace('.', '\\', $app).'\\';
            $composer->addPsr4($ns, APPLICATION_PATH."/src/app/$app");
        }
    }
}
