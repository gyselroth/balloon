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
        $server = new Server(
            self::getMockDatabase(),
            $this->createMock(Storage::class),
            $this->createMock(LoggerInterface::class),
            $this->createMock(Hook::class),
            $this->createMock(Acl::class)
        );

        $identity = new Mock\Identity('testuser', [], $this->createMock(LoggerInterface::class));

        if (!$server->userExists('testuser')) {
            $server->addUser(['username' => 'testuser']);
        }

        $server->setIdentity($identity);

        return $server;
    }
}
