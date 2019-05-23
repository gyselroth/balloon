<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Testsuite\Filesystem\Node;

use Balloon\Filesystem\Acl;
use Balloon\Filesystem\Node\AttributeDecorator as NodeAttributeDecorator;
use Balloon\Filesystem\Node\Collection;
use Balloon\Filesystem\Node\File;
use Balloon\Server;
use Balloon\Server\AttributeDecorator as RoleAttributeDecorator;
use Balloon\Testsuite\Unit\Test;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;
use stdClass;

class AttributeDecoratorTest extends Test
{
    protected $decorator;

    public function setUp()
    {
        $this->decorator = new NodeAttributeDecorator($this->createMock(Server::class), $this->createMock(Acl::class), $this->createMock(RoleAttributeDecorator::class));
    }

    public function testDecorateFileAllAttributes()
    {
        $attributes = $this->decorator->decorate($this->getMockFile());
        $expect = [
            'id' => '5aac274870a5a63ca827ceab',
            'name' => 'foo',
            'size' => 1,
            'mime' => 'text/plain',
            'path' => '/foo',
            'directory' => false,
            'created' => '1970-01-01T00:00:00+00:00',
            'changed' => '1970-01-01T00:00:00+00:00',
            'deleted' => '1970-01-01T00:00:00+00:00',
            'destroy' => '1970-01-01T00:00:00+00:00',
            'version' => 0,
            'hash' => 'd41d8cd98f00b204e9800998ecf8427e',
            'readonly' => false,
            'access' => '',
            'meta' => new stdClass(),
        ];

        $this->assertEquals($expect, $attributes);
    }

    public function testDecorateFileSpecificAttributes()
    {
        $attributes = $this->decorator->decorate($this->getMockFile(), ['id', 'name']);
        $expect = [
            'id' => '5aac274870a5a63ca827ceab',
            'name' => 'foo',
        ];

        $this->assertSame($expect, $attributes);
    }

    public function testDecorateCollectionAllAttributes()
    {
        $attributes = $this->decorator->decorate($this->getMockCollection());
        $expect = [
            'id' => '5aac274870a5a63ca827ceab',
            'name' => 'foo',
            'size' => 1,
            'mime' => 'inode/directory',
            'path' => '/foo',
            'directory' => true,
            'created' => '1970-01-01T00:00:00+00:00',
            'changed' => '1970-01-01T00:00:00+00:00',
            'deleted' => '1970-01-01T00:00:00+00:00',
            'destroy' => '1970-01-01T00:00:00+00:00',
            'readonly' => false,
            'access' => '',
            'shared' => false,
            'reference' => false,
            'meta' => new stdClass(),
            'filter' => ['name' => 'foo'],
        ];

        $this->assertEquals($expect, $attributes);
    }

    public function testDecorateCollectionSpecificAttributes()
    {
        $attributes = $this->decorator->decorate($this->getMockCollection(), ['id', 'name']);
        $expect = [
            'id' => '5aac274870a5a63ca827ceab',
            'name' => 'foo',
        ];

        $this->assertSame($expect, $attributes);
    }

    public function testAddCustomDecorator()
    {
        $this->decorator->addDecorator('foo', function ($node) {
            if ($node instanceof Collection) {
                return 'foo';
            }

            if ($node instanceof File) {
                return 'bar';
            }
        });

        $file = $this->decorator->decorate($this->getMockFile(), ['foo']);
        $this->assertSame('bar', $file['foo']);
        $col = $this->decorator->decorate($this->getMockCollection(), ['foo']);
        $this->assertSame('foo', $col['foo']);
    }

    public function testNullValueRemoved()
    {
        $this->decorator->addDecorator('foo', function ($node) {
            return null;
        });

        $file = $this->decorator->decorate($this->getMockFile(), ['foo']);
        $this->assertTrue(!isset($file['foo']));
    }

    protected function getMockFile()
    {
        $stub = $this->createMock(File::class);
        $stub->method('getName')
            ->willReturn('foo');
        $stub->method('getPath')
            ->willReturn('/foo');
        $stub->method('getSize')
            ->willReturn(1);
        $stub->method('getAttributes')
            ->willReturn([
            '_id' => new ObjectId('5aac274870a5a63ca827ceab'),
            'name' => 'foo',
            'size' => 1,
            'mime' => 'text/plain',
            'readonly' => false,
            'created' => new UTCDateTime(0),
            'changed' => new UTCDateTime(0),
            'deleted' => new UTCDateTime(0),
            'destroy' => new UTCDateTime(0),
            'version' => 0,
            'hash' => md5(''),
        ]);

        return $stub;
    }

    protected function getMockCollection()
    {
        $stub = $this->createMock(Collection::class);
        $stub->method('getName')
            ->willReturn('foo');
        $stub->method('getPath')
            ->willReturn('/foo');
        $stub->method('getSize')
            ->willReturn(1);
        $stub->method('getAttributes')
            ->willReturn([
            '_id' => new ObjectId('5aac274870a5a63ca827ceab'),
            'name' => 'foo',
            'mime' => 'inode/directory',
            'readonly' => false,
            'created' => new UTCDateTime(0),
            'changed' => new UTCDateTime(0),
            'deleted' => new UTCDateTime(0),
            'destroy' => new UTCDateTime(0),
            'filter' => json_encode(['name' => 'foo']),
            'mount' => [],
        ]);

        return $stub;
    }
}
