<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copyright (c) 2012-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Testsuite\Unit\Filesystem\Delta;

use Balloon\Filesystem\Acl;
use Balloon\Filesystem\Delta;
use Balloon\Filesystem\Node\Collection;
use Balloon\Filesystem\Node\File;
use Balloon\Hook;
use Balloon\Session\Factory as SessionFactory;
use Balloon\Testsuite\Unit\Test;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;
use Psr\Log\LoggerInterface;

/**
 * @coversNothing
 */
class DeltaTest extends Test
{
    protected $fs;
    protected $delta;
    protected $server;

    public function setUp()
    {
        $this->server = $this->getMockServer();
        $this->fs = $this->server->getFilesystem();
        $this->delta = new Delta($this->fs, self::getMockDatabase(), $this->createMock(Acl::class));
    }

    public function testAddRecord()
    {
        $file = $this->createMock(File::class);
        $file->method('getId')->willReturn(new ObjectId());
        $file->method('getOwner')->willReturn(new ObjectId());
        $id = $this->delta->add('test', $file);
        $this->assertInstanceOf(ObjectId::class, $id);
    }

    /*public function testGetOneRecord()
    {
        $id = new ObjectId();
        $file = $this->createMock(File::class);
        $file->method('getId')->willReturn($id);
        $file->method('getOwner')->willReturn(new ObjectId());
        $id = $this->delta->add('test', $file);
        $last = $this->delta->getLastRecord();
        $this->assertEquals($id, $last['node']);
    }*/

    public function testGetLastRecord()
    {
        $data = [
            [
                'owner' => $this->fs->getUser()->getId(),
                'timestamp' => new UTCDateTime(0),
                'operation' => 'test1',
                'name' => uniqid(),
            ], [
                'owner' => $this->fs->getUser()->getId(),
                'timestamp' => new UTCDateTime(),
                'operation' => 'test2',
                'name' => uniqid(),
            ],
        ];

        foreach ($data as $record) {
            self::getMockDatabase()->delta->insertOne($record);
        }

        $from_delta = $this->delta->getLastRecord();
        $this->assertSame($from_delta['name'], $data[1]['name']);
    }

    public function testGetLastRecordForNode()
    {
        // fixture
        $files = [
            new File(
                [
                    'owner' => $this->fs->getUser()->getId(),
                    '_id' => new ObjectId(),
                ],
                $this->fs,
                $this->createMock(LoggerInterface::class),
                $this->createMock(Hook::class),
                $this->createMock(Acl::class),
                $this->createMock(Collection::class),
                $this->createMock(SessionFactory::class),
            ),
            new File(
                [
                    'owner' => $this->fs->getUser()->getId(),
                    '_id' => new ObjectId(),
                ],
                $this->fs,
                $this->createMock(LoggerInterface::class),
                $this->createMock(Hook::class),
                $this->createMock(Acl::class),
                $this->createMock(Collection::class),
                $this->createMock(SessionFactory::class),
            ),
        ];
        $data = [
            [
                'owner' => $this->fs->getUser()->getId(),
                'operation' => 'test1',
                'name' => uniqid(),
            ], [
                'owner' => $this->fs->getUser()->getId(),
                'operation' => 'test2',
                'name' => uniqid(),
            ],
        ];

        // create records
        foreach ($data as $key => $record) {
            $this->delta->add($record['operation'], $files[$key], $record);
        }

        $from_delta = $this->delta->getLastRecord($files[0]);
        $this->assertSame((string) $files[0]->getId(), (string) $from_delta['node']);
    }
}
