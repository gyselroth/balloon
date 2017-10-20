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

/**
 * @coversNothing
 */
class CliTest extends Test
{
    protected static $server;
    protected static $app;

    public static function setUpBeforeClass()
    {
        self::$server = self::setupMockServer();
        $factory = new Factory();
        self::$app = new ClamAvApp($factory, self::$server, self::$server->getLogger());
    }

    public function testHandleCleanFile()
    {
        // setup file
        $file = new File(
            ['owner' => self::$server->getIdentity()->getId()],
            self::$server->getFilesystem()
        );

        // execute SUT
        self::$app->handleFile($file);

        // assertion
        $this->assertFalse($file->getAppAttribute(self::$app, 'quarantine'));
    }

    public function testHandleInfectedFileLevel1()
    {
        // setup file
        $file = new File(
            ['owner' => self::$server->getIdentity()->getId()],
            self::$server->getFilesystem()
        );

        // setup SUT
        self::$app->setOptions([
            'aggressiveness' => 1,
        ]);

        // execute SUT
        self::$app->handleFile($file, true);

        // assertion
        $this->assertTrue($file->getAppAttribute(self::$app, 'quarantine'));
        $this->assertFalse($file->isDeleted());
    }

    public function testHandleInfectedFileLevel2()
    {
        // setup file
        $file = new File(
            ['owner' => self::$server->getIdentity()->getId()],
            self::$server->getFilesystem()
        );

        // setup SUT
        self::$app->setOptions([
            'aggressiveness' => 2,
        ]);

        // execute SUT
        self::$app->handleFile($file, true);

        // assertion
        $this->assertTrue($file->getAppAttribute(self::$app, 'quarantine'));
        $this->assertTrue($file->isDeleted());
    }

    public function testHandleInfectedFileLevel3()
    {
        // setup mock
        $mockFile = $this->getMockBuilder(File::class)
            ->setMethods(['_verifyAccess', 'delete'])
            ->setConstructorArgs([
                ['owner' => self::$server->getIdentity()->getId()],
                self::$server->getFilesystem(),
            ])
            ->getMock();

        // setup SUT
        self::$app->setOptions([
            'aggressiveness' => 3,
        ]);

        // setup expectation
        $mockFile->expects($this->once())
            ->method('delete')
            ->with($this->equalTo(true));

        // execute SUT
        self::$app->handleFile($mockFile, true);
    }
}
