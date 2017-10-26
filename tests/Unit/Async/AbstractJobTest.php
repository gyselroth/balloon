<?php

declare(strict_types=1);

/**
 * Balloon
 *
 * @author      Raffael Sahli <sahli@gyselroth.net>
 * @copyright   Copryright (c) 2012-2017 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Testsuite\Unit\Async;

use Balloon\Async\AbstractJob;
use Balloon\Testsuite\Unit\Test;

/**
 * @coversNothing
 */
class AbstractJobTest extends Test
{
    public function testGetData()
    {
        // fixture
        $testData = ['key' => 'test'];
        $stub = $this->getMockForAbstractClass(AbstractJob::class);
        $stub->setData($testData);

        // assertion
        $this->assertSame($testData, $stub->getData());
    }
}
