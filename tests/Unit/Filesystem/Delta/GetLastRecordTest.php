<?php
namespace Balloon\Testsuite\Unit\Filesystem\Delta;

use \Balloon\Filesystem\Delta;
use \Balloon\Testsuite\Unit\Test;
use Balloon\Filesystem\Node\Collection;
use Balloon\Filesystem\Node\File;
use \MongoDB\BSON\UTCDateTime;
use \MongoDB\Model\BSONDocument;

class GetLastRecordTest extends Test
{
    protected $fs;
    protected $delta;

    public function setUp()
    {
        $server = self::setupMockServer();
        $this->fs = new \Balloon\Filesystem($server, $server->getLogger());
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
        $fromDelta = $this->delta->getLastRecord();
        $this->assertTrue($this->looseCompareBSON($fromDelta, new BSONDocument($data), ['_id']));
    }

    public function testGetEmpty()
    {
        // get record
        $fromDelta = $this->delta->getLastRecord();
        $this->assertNull($fromDelta);
    }

    public function testGetLastRecord()
    {
        // fixture
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

        // verify comparsion function
        $this->assertFalse(self::looseCompareBSON(new BSONDocument($data[0]), new BSONDocument($data[1]), ['_id']));

        // create records
        foreach ($data as $record) {
            $this->delta->add($record);
        }

        // get record
        $fromDelta = $this->delta->getLastRecord();
        $this->assertTrue(self::looseCompareBSON($fromDelta, new BSONDocument($data[1]), ['_id']));
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
    //     $fromDelta = $this->delta->getLastRecord($data[0]['node']);
    //     var_dump($fromDelta['operation']);
    //     $this->assertTrue(self::looseCompareBSON($fromDelta, new BSONDocument($data[0]), ['_id', 'node']));
    // }

    protected static function looseCompareBSON(BSONDocument $docA, BSONDocument $docB, array $ignoreFields = []) : bool
    {
        if ($docA === $docB) {
            return true;
        }
        if ($docA == $docB) {
            return true;
        }
        if (empty($ignoreFields)) {
            return false;
        }

        foreach (get_object_vars($docA) as $key => $value) {
            if (in_array($key, $ignoreFields)) {
                continue;
            }
            if ($docA->$key != $docB->$key) {
                return false;
            }
        }

        return true;
    }
}
