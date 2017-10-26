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
use Balloon\Hook\Delta;
use Balloon\Server;
use Helmich\MongoMock\MockDatabase;
use Micro\Http\Response;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

abstract class Test extends TestCase
{
    protected static $db;

    public static function getMockDatabase()
    {
        if (self::$db instanceof MockDatabase) {
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

    public function getMockApp()
    {
        global $composer;
        $app = new App($composer, $this->createMock(LoggerInterface::class), $this->createMock(Hook::class));
        $this->registerAppNamespaces();
        return $app;
    }

    public function getMockServer()
    {
        $app = $this->getMockApp();
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

        if (!$server->userExists('testuser')) {
            $server->addUser(['username' => 'testuser']);
        }

        $server->setIdentity($identity);

        return $server;
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
