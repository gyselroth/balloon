<?php

declare(strict_types=1);

/**
 * Balloon
 *
 * @author      Raffael Sahli <sahli@gyselroth.net>
 * @copyright   Copryright (c) 2012-2017 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Testsuite\Unit\Filesystem\Delta;

use Balloon\Filesystem\Delta;
use Balloon\Filesystem\Node\File;
use Balloon\Testsuite\Unit\Test;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;

/**
 * @coversNothing
 */
class GetLastRecordTest extends Test
{
    protected $fs;
    protected $delta;

    public function setUp()
    {
        $server = $this->getMockServer();
        $this->fs = $server->getFilesystem();
        $this->delta = new Delta($this->fs);
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

    /*public function testGetEmpty()
    {
        // get record
        $from_delta = $this->delta->getLastRecord();
        $this->assertNull($from_delta);
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
                $this->fs
            ),
            new File(
                [
                    'owner' => $this->fs->getUser()->getId(),
                    '_id' => new ObjectId(),
                ],
                $this->fs
            ),
        ];
        $data = [
            [
                'owner' => $this->fs->getUser()->getId(),
                'timestamp' => new UTCDateTime(0),
                'operation' => 'test1',
                'node' => $files[0]->getId(),
                'name' => uniqid(),
            ], [
                'owner' => $this->fs->getUser()->getId(),
                'timestamp' => new UTCDateTime(),
                'operation' => 'test2',
                'node' => $files[1]->getId(),
                'name' => uniqid(),
            ],
        ];

        // create records
        foreach ($data as $record) {
            $this->delta->add($record);
        }

        // get record
        $from_delta = $this->delta->getLastRecord($files[0]);
        // unset _id property to be able to compare
        unset($from_delta['_id']);
        $this->assertEquals($data[0], $from_delta);
    }
}
