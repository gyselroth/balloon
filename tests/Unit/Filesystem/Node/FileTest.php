<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Testsuite\Filesystem\Node;

use Balloon\Filesystem;
use Balloon\Filesystem\Acl;
use Balloon\Filesystem\Exception;
use Balloon\Filesystem\Node\Collection;
use Balloon\Filesystem\Node\File;
use Balloon\Hook;
use Balloon\Testsuite\Unit\Test;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;
use Psr\Log\LoggerInterface;

class FileTest extends Test
{
    protected $file;

    public function setUp()
    {
        $this->file = $this->getMockFile();
    }

    public function testGetVersion()
    {
        $this->assertSame(0, $this->file->getVersion());
    }

    public function testGetId()
    {
        $this->assertEquals(new ObjectId('5aac274870a5a63ca827ceab'), $this->file->getId());
    }

    public function testGetETag()
    {
        $this->assertSame('"'.md5('').'"', $this->file->getETag());
    }

    public function testGetHash()
    {
        $this->assertSame(md5(''), $this->file->getHash());
    }

    public function testSize()
    {
        $this->assertSame(1, $this->file->getSize());
    }

    public function testGetContentType()
    {
        $this->assertSame('text/plain', $this->file->getContentType());
    }

    public function testGetExtension()
    {
        $this->assertSame('bar', $this->file->getExtension());
    }

    public function testGetFileWithNoExtension()
    {
        $file = $this->getMockFile(['name' => 'bar']);
        $this->expectException(Exception::class);
        $file->getExtension();
    }

    public function testIsNotTemporaryFile()
    {
        $this->assertFalse($this->file->isTemporaryFile());
    }

    public function testIsTemporaryFile()
    {
        $file = $this->getMockFile(['name' => '.test.swp']);
        $this->assertTrue($file->isTemporaryFile());
    }

    public function testGetContents()
    {
    }

    public function testGetHistory()
    {
    }

    public function testRestore()
    {
    }

    public function testGetAttributes()
    {
    }

    public function testDelete()
    {
    }

    public function testDeleteVersion()
    {
    }

    public function testCleanHistory()
    {
    }

    protected function getMockFile(array $data = [])
    {
        $stub = [
            '_id' => new ObjectId('5aac274870a5a63ca827ceab'),
            'name' => 'foo.bar',
            'size' => 1,
            'mime' => 'text/plain',
            'readonly' => false,
            'created' => new UTCDateTime(0),
            'changed' => new UTCDateTime(0),
            'deleted' => new UTCDateTime(0),
            'destroy' => new UTCDateTime(0),
            'version' => 0,
            'hash' => md5(''),
        ];

        $stub = array_merge($stub, $data);

        return new File(
            $stub,
            $this->createMock(Filesystem::class),
            $this->createMock(LoggerInterface::class),
            $this->createMock(Hook::class),
            $this->createMock(Acl::class),
            $this->createMock(Collection::class)
        );
    }
}
