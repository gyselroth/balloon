<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2018 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Testsuite\Unit\App\ClamAv;

use Balloon\App\ClamAv\Scanner;
use Balloon\Filesystem\Acl;
use Balloon\Filesystem\Node\File;
use Balloon\Filesystem\Storage;
use Balloon\Hook;
use Balloon\Testsuite\Unit\Test;
use Psr\Log\LoggerInterface;
use Socket\Raw\Factory;

/**
 * @coversNothing
 */
class ScannerTest extends Test
{
    protected $scanner;
    protected $server;

    public function setUp()
    {
        $this->server = $this->getMockServer();
        $this->scanner = new Scanner($this->createMock(Factory::class), $this->createMock(LoggerInterface::class));
    }

    public function getFile()
    {
        $acl = $this->createMock(Acl::class);
        $acl->expects($this->any())
             ->method('isAllowed')
             ->will($this->returnValue(true));

        return new File(
            ['owner' => $this->server->getIdentity()->getId()],
            $this->server->getFilesystem(),
            $this->createMock(LoggerInterface::class),
            $this->createMock(Hook::class),
            $acl,
            $this->createMock(Storage::class)
        );
    }

    public function testHandleCleanFile()
    {
        $file = $this->getFile();

        // execute SUT
        $this->scanner->handleFile($file, ['status' => false]);

        // assertion
        $this->assertFalse($file->getAppAttribute('Balloon\\App\\ClamAv', 'quarantine'));
    }

    public function testHandleInfectedFileLevel1()
    {
        $file = $this->getFile();

        // setup SUT
        $this->scanner->setOptions([
            'aggressiveness' => 1,
        ]);

        // execute SUT
        $this->scanner->handleFile($file, [
            'status' => true,
        ]);

        // assertion
        $this->assertTrue($file->getAppAttribute('Balloon\\App\\ClamAv', 'quarantine'));
        $this->assertFalse($file->isDeleted());
    }

    public function testHandleInfectedFileLevel2()
    {
        $file = $this->getFile();

        // setup SUT
        $this->scanner->setOptions([
            'aggressiveness' => 2,
        ]);

        // execute SUT
        $this->scanner->handleFile($file, ['status' => true]);

        // assertion
        $this->assertTrue($file->getAppAttribute('Balloon\\App\\ClamAv', 'quarantine'));
        $this->assertTrue($file->isDeleted());
    }

    public function testHandleInfectedFileLevel3()
    {
        $acl = $this->createMock(Acl::class);
        $acl->expects($this->any())
             ->method('isAllowed')
             ->will($this->returnValue(true));

        // setup mock
        $mockFile = $this->getMockBuilder(File::class)
            ->setMethods(['delete'])
            ->setConstructorArgs([
                ['owner' => $this->server->getIdentity()->getId()],
                $this->server->getFilesystem(),
                $this->createMock(LoggerInterface::class),
                $this->createMock(Hook::class),
                $acl,
                $this->createMock(Storage::class),
            ])
            ->getMock();

        // setup SUT
        $this->scanner->setOptions([
            'aggressiveness' => 3,
        ]);

        // setup expectation
        $mockFile->expects($this->once())
            ->method('delete')
            ->with($this->equalTo(true));

        // execute SUT
        $this->scanner->handleFile($mockFile, ['status' => true]);
    }
}
