<?php

declare(strict_types=1);

/**
 * Balloon
 *
 * @author      Raffael Sahli <sahli@gyselroth.net>
 * @copyright   Copryright (c) 2012-2017 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Testsuite\Unit\App\Api\v1;

use Balloon\Testsuite\Unit\Test as UnitTest;
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
use Balloon\Testsuite\Unit\Mock;

abstract class Test extends UnitTest
{
    protected $type = 'node';
    protected $controller;
    protected static $first_cursor;
    protected static $current_cursor;

    public function getMockServer()
    {
        $app = $this->getMockApp();
        $hook = new Hook($this->createMock(LoggerInterface::class));
        $hook->injectHook(new Delta());

        $server = new Server(
            self::getMockDatabase(),
            $this->createMock(Storage::class),
            $this->createMock(LoggerInterface::class),
            $this->createMock(Async::class),
            $hook,
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

    public function getDelta($cursor = null)
    {
        $res = $this->controller->getDelta(null, null, $cursor);
        $this->assertInstanceOf(Response::class, $res);
        $this->assertSame(200, $res->getCode());

        return $res->getBody();
    }

    public function getLastCursor()
    {
        $res = $this->controller->getLastCursor();
        $this->assertInstanceOf(Response::class, $res);
        $this->assertSame(200, $res->getCode());

        return $res->getBody();
    }
}
