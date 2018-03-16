<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2018 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Testsuite\Unit;

use Balloon\Filesystem\Acl;
use Balloon\Filesystem\Storage;
use Balloon\Hook;
use Balloon\Server;
use Helmich\MongoMock\MockDatabase;
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

    public function getMockServer()
    {
        $acl = $this->createMock(Acl::class);

        $acl->expects($this->any())
             ->method('isAllowed')
             ->will($this->returnValue(true));

        $server = new Server(
            self::getMockDatabase(),
            $this->createMock(Storage::class),
            $this->createMock(LoggerInterface::class),
            $this->createMock(Hook::class),
            $acl
        );

        $identity = new Mock\Identity('testuser', [], $this->createMock(LoggerInterface::class));

        if (!$server->usernameExists('testuser')) {
            $server->addUser('testuser');
        }

        $server->setIdentity($identity);

        return $server;
    }
}
