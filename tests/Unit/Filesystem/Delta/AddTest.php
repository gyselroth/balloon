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
use Balloon\Filesystem\Delta\Exception;
use Balloon\Testsuite\Unit\Test;
use MongoDB\BSON\UTCDateTime;

/**
 * @coversNothing
 */
class AddTest extends Test
{
    protected $fs;
    protected $delta;

    public function setUp()
    {
        $server = $this->getMockServer();
        $this->fs = $server->getFilesystem();
        $this->delta = new Delta($this->fs);
    }

    public function testValidArrayWithTimestamp()
    {
        $data = [
            'owner' => $this->fs->getUser()->getId(),
            'operation' => 'test',
            'timestamp' => new UTCDateTime(),
        ];

        $this->assertTrue($this->delta->add($data));

        return $this->delta;
    }

    public function testValidArrayWithoutTimestamp()
    {
        $data = [
            'owner' => $this->fs->getUser()->getId(),
            'operation' => 'test',
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

    public function testInvalidArray()
    {
        $this->expectException(Exception::class);
        $data = ['a', 'b'];
        $this->delta->add($data);
    }
}
