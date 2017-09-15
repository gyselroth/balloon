<?php
namespace Balloon\Testsuite\Unit\Filesystem\Delta;

use \Balloon\Filesystem\Delta;
use \Balloon\Testsuite\Unit\Test;
use Balloon\Filesystem\Node\Collection;
use Balloon\Filesystem\Node\File;
use \MongoDB\BSON\UTCDateTime;

class GetLastRecordTest extends Test
{
    protected $fs;
    protected $delta;

    public function setUp()
    {
        $server = self::setupMockServer();
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
            'name' => uniqid()
        ];

        // create record
        $this->delta->add($data);

        // get record
        $from_delta = $this->delta->getLastRecord();
        $this->assertEquals($data['name'], $from_delta['name']);
    }

    public function testGetEmpty()
    {
        // get record
        $from_delta = $this->delta->getLastRecord();
        $this->assertNull($from_delta);
    }

    public function testGetLastRecord()
    {
        $data = [
            [
                'owner' => $this->fs->getUser()->getId(),
                'timestamp' => new UTCDateTime(0),
                'operation' => 'test1',
                'name' => uniqid()
            ], [
                'owner' => $this->fs->getUser()->getId(),
                'timestamp' => new UTCDateTime(),
                'operation' => 'test2',
                'name' => uniqid()
            ]
        ];

        foreach ($data as $record) {
            $this->delta->add($record);
        }

        $from_delta = $this->delta->getLastRecord();
        $this->assertEquals($from_delta['name'], $data[1]['name']);
    }

    // public function testGetCollectionRecord()
    // {
    //     // fixture
    //     $data = [
    //         [
    //             'owner' => $this->fs->getUser()->getId(),
    //             'timestamp' => new UTCDateTime(0),
    //             'operation' => 'test1',
    //             'node'  =>  new File(
    //                 new BSONDocument([
    //                     'owner' => $this->fs->getUser()->getId(),
    //                     '_id' => uniqid()
    //                 ]),
    //                 $this->fs
    //             ),
    //             'name' => uniqid()
    //         ], [
    //             'owner' => $this->fs->getUser()->getId(),
    //             'timestamp' => new UTCDateTime(),
    //             'operation' => 'test2',
    //             'node'  =>  new File(
    //                 new BSONDocument([
    //                     'owner' => $this->fs->getUser()->getId(),
    //                     '_id' => uniqid()
    //                 ]),
    //                 $this->fs
    //             ),
    //             'name' => uniqid()
    //         ]
    //     ];
    //
    //     // create records
    //     foreach ($data as $record) {
    //         $this->delta->add($record);
    //     }
    //
    //     var_dump("0 " . $data[0]['node']->getId());
    //     var_dump("1 " . $data[1]['node']->getId());
    //     // get record
    //     $from_delta = $this->delta->getLastRecord($data[0]['node']);
    //     var_dump($from_delta['operation']);
    //     $this->assertTrue(self::looseCompareBSON($from_delta, new BSONDocument($data[0]), ['_id', 'node']));
    // }
}
