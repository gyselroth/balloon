<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2018 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Testsuite\Unit\Filesystem\Delta;

use Balloon\Filesystem\Acl;
use Balloon\Filesystem\Delta;
use Balloon\Filesystem\Node\File;
use Balloon\Filesystem\Storage;
use Balloon\Hook;
use Balloon\Testsuite\Unit\Test;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;
use Psr\Log\LoggerInterface;

/**
 * @coversNothing
 */
class GetLastRecordTest extends Test
{
    protected $fs;
    protected $delta;
    protected $server;

    public function setUp()
    {
        $this->server = $this->getMockServer();
        $this->fs = $this->server->getFilesystem();
        $this->delta = new Delta($this->fs, self::getMockDatabase());
    }

    public function testGetOneRecord()
    {
        // fixture
        $data = [
            'owner' => $this->fs->getUser()->getId(),
            'timestamp' => new UTCDateTime(),
            'operation' => 'test',
            'name' => uniqid(),
        ];

        // create record
        $this->delta->add($data);

        // get record
        $from_delta = $this->delta->getLastRecord();
        $this->assertSame($data['name'], $from_delta['name']);
    }

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
            $this->delta->add($record);
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
                $this->createMock(Storage::class)
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
                $this->createMock(Storage::class)
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

        // get record
        $from_delta = $this->delta->getLastRecord($files[0]);
        // unset _id property to be able to compare
        unset($from_delta['_id']);
        $this->assertSame($data[0], $from_delta);
    }
}
