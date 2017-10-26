<?php

declare(strict_types=1);

/**
 * Balloon
 *
 * @author      Raffael Sahli <sahli@gyselroth.net>
 * @copyright   Copryright (c) 2012-2017 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Testsuite\Unit\App\ClamAv;

use Balloon\App\ClamAv\Cli as ClamAvApp;
use Balloon\Filesystem\Node\File;
use Balloon\Testsuite\Unit\Test;
use Socket\Raw\Factory;
use Psr\Log\LoggerInterface;
use Balloon\Server\User;
use Balloon\Hook;
use Balloon\Filesystem\Storage;

/**
 * @coversNothing
 */
class CliTest extends Test
{
    protected $app;
    protected $server;

    public function setUp()
    {
        $this->server = $this->getMockServer();
        $factory = new Factory();
        $this->app = new ClamAvApp($factory, $this->createMock(LoggerInterface::class));
    }

    public function getFile()
    {
        return new File(
            ['owner' => $this->server->getIdentity()->getId()],
            $this->server->getFilesystem(),
            $this->createMock(LoggerInterface::class),
            $this->createMock(Hook::class),
            $this->createMock(Storage::class)
        );
    }

    public function testHandleCleanFile()
    {
        $file = $this->getFile();

        // execute SUT
        $this->app->handleFile($file);

        // assertion
        $this->assertFalse($file->getAppAttribute($this->app, 'quarantine'));
    }

    public function testHandleInfectedFileLevel1()
    {
        $file = $this->getFile();

        // setup SUT
        $this->app->setOptions([
            'aggressiveness' => 1,
        ]);

        // execute SUT
        $this->app->handleFile($file, true);

        // assertion
        $this->assertTrue($file->getAppAttribute($this->app, 'quarantine'));
        $this->assertFalse($file->isDeleted());
    }

    public function testHandleInfectedFileLevel2()
    {
        $file = $this->getFile();

        // setup SUT
        $this->app->setOptions([
            'aggressiveness' => 2,
        ]);

        // execute SUT
        $this->app->handleFile($file, true);

        // assertion
        $this->assertTrue($file->getAppAttribute($this->app, 'quarantine'));
        $this->assertTrue($file->isDeleted());
    }

    public function testHandleInfectedFileLevel3()
    {
        // setup mock
        $mockFile = $this->getMockBuilder(File::class)
            ->setMethods(['_verifyAccess', 'delete'])
            ->setConstructorArgs([
                ['owner' => $this->server->getIdentity()->getId()],
                $this->server->getFilesystem(),
                $this->createMock(LoggerInterface::class),
                $this->createMock(Hook::class),
                $this->createMock(Storage::class)
            ])
            ->getMock();

        // setup SUT
        $this->app->setOptions([
            'aggressiveness' => 3,
        ]);

        // setup expectation
        $mockFile->expects($this->once())
            ->method('delete')
            ->with($this->equalTo(true));

        // execute SUT
        $this->app->handleFile($mockFile, true);
    }
}
