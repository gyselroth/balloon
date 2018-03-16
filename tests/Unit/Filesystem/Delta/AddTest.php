<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2018 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Testsuite\Unit\Filesystem\Delta;

use Balloon\Filesystem\Delta;
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
        $this->delta = new Delta($this->fs, parent::getMockDatabase());
    }

    public function testTimestamp()
    {
        $this->assertTrue($this->delta->add('test', $this->createMock(File::class)));
        $this->assertLessThanOrEqual(new UTCDateTime(), $delta->getLastRecord()['timestamp']);
    }
}
