<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Testsuite\Unit\App\ClamAv;

use Balloon\App\ClamAv\Scanner;
use Balloon\Filesystem\Acl;
use Balloon\Filesystem\Node\Collection;
use Balloon\Filesystem\Node\File;
use Balloon\Hook;
use Balloon\Session\Factory as SessionFactory;
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

    public function testHandleCleanFile()
    {
        $file = $this->getFile();
        $this->scanner->handleFile($file, Scanner::FILE_OK);
        $this->assertFalse($file->getAppAttribute('Balloon\\App\\ClamAv', 'quarantine'));
    }

    public function testHandleInfectedFileLevel1()
    {
        $file = $this->getFile();

        $this->scanner->setOptions([
            'aggressiveness' => 1,
        ]);

        $this->scanner->handleFile($file, Scanner::FILE_INFECTED);

        $this->assertTrue($file->getAppAttribute('Balloon\\App\\ClamAv', 'quarantine'));
        $this->assertFalse($file->isDeleted());
    }

    public function testHandleInfectedFileLevel2()
    {
        $file = $this->getFile();

        $this->scanner->setOptions([
            'aggressiveness' => 2,
        ]);

        $this->scanner->handleFile($file, Scanner::FILE_INFECTED);

        $this->assertTrue($file->getAppAttribute('Balloon\\App\\ClamAv', 'quarantine'));
        $this->assertTrue($file->isDeleted());
    }

    public function testHandleInfectedFileLevel3()
    {
        $acl = $this->createMock(Acl::class);
        $acl->expects($this->any())
             ->method('isAllowed')
             ->will($this->returnValue(true));

        $mockFile = $this->getMockBuilder(File::class)
            ->setMethods(['delete'])
            ->setConstructorArgs([
                ['owner' => $this->server->getIdentity()->getId()],
                $this->server->getFilesystem(),
                $this->createMock(LoggerInterface::class),
                $this->createMock(Hook::class),
                $acl,
                $this->createMock(Collection::class),
                $this->createMock(SessionFactory::class),
            ])
            ->getMock();

        $this->scanner->setOptions([
            'aggressiveness' => 3,
        ]);

        $mockFile->expects($this->once())
            ->method('delete')
            ->with($this->equalTo(true));

        $this->scanner->handleFile($mockFile, Scanner::FILE_INFECTED);
    }

    protected function getFile()
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
            $this->createMock(Collection::class),
            $this->createMock(SessionFactory::class),
        );
    }
}
