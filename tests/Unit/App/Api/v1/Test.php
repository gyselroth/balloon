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

use Balloon\Filesystem\Acl;
use Balloon\Filesystem\Storage;
use Balloon\Api\v1\Collection;
use Balloon\Hook;
use Balloon\Filesystem\Node\AttributeDecorator;
use Balloon\Server\AttributeDecorator as RoleAttributeDecorator;
use Balloon\Hook\Delta;
use Balloon\Server;
use Balloon\Testsuite\Unit\Mock;
use Balloon\Testsuite\Unit\Test as UnitTest;
use Micro\Http\Response;
use Psr\Log\LoggerInterface;

abstract class Test extends UnitTest
{
    protected $type = 'node';
    protected $controller;
    protected static $first_cursor;
    protected static $current_cursor;

    public function getMockServer()
    {
        $hook = new Hook($this->createMock(LoggerInterface::class));
        $hook->injectHook(new Delta());

        $acl = $this->createMock(Acl::class);
        $acl->expects($this->any())
             ->method('isAllowed')
             ->will($this->returnValue(true));

        $server = new Server(
            self::getMockDatabase(),
            $this->createMock(Storage::class),
            $this->createMock(LoggerInterface::class),
            $hook,
            $acl
        );

        $identity = new Mock\Identity('testuser', [], $this->createMock(LoggerInterface::class));

        if (!$server->userExists('testuser')) {
            $server->addUser('testuser');
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

    public function getCollectionController()
    {
        $server = $this->getMockServer();
        $decorator = new RoleAttributeDecorator($server);
        return new Collection($server, new AttributeDecorator($server, $this->createMock(Acl::class), $decorator),
          $decorator, $this->createMock(LoggerInterface::class));
    }
}
