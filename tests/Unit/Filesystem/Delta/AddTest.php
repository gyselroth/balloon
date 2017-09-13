<?php
namespace Balloon\Testsuite\Unit\Filesystem\Delta;

use \Balloon\Filesystem;
use \Balloon\Filesystem\Delta;
use \Balloon\Testsuite\Unit\Test;
use \MongoDB\BSON\UTCDateTime;

class AddTest extends Test
{
    protected $fs;
    protected $delta;

    public function setUp()
    {
        $server = self::setupMockServer();
        $this->fs = $server->getFilesystem();
        $this->delta = new Delta($this->fs);
    }


    public function testValidArrayWithTimestamp()
    {
        $data = [
            'owner' => $this->fs->getUser()->getId(),
            'operation' => 'test',
            'timestamp' => new UTCDateTime(0),
        ];

        $this->assertTrue($this->delta->add($data));
        return $this->delta;
    }


   /**
    * @depends testValidArrayWithTimestamp
    */
    public function testTimestampNotChanged(Delta $delta)
    {
        $this->assertEquals(new UTCDateTime(0), $delta->getLastRecord()['timestamp']);
    }


    public function testValidArrayWithoutTimestamp()
    {
        $data = [
            'owner' => $this->fs->getUser()->getId(),
            'operation' => 'test'
        ];

        $this->assertTrue($this->delta->add($data));
        return $this->delta;
    }


   /**
    * @depends testValidArrayWithoutTimestamp
    */
    public function testTimestampAdded(Delta $delta)
    {
        $this->assertLessThanOrEqual(new UTCDateTime(), $delta->getLastRecord()['timestamp']);
    }


   /**
    * @expectedException \Balloon\Filesystem\Delta\Exception
    */
    public function testInvalidArray()
    {
        $data = ['a', 'b'];
        $this->delta->add($data);
    }
}
