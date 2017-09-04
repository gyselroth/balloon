<?php
namespace Balloon\Testsuite\Unit\Async;

use \Balloon\Testsuite\Unit\Test;
use \Balloon\Async\AbstractJob;

class AbstractJobTest extends Test
{
  public function testGetData(){
    // fixture
    $testData = ['key' => 'test'];
    $stub = $this->getMockForAbstractClass(AbstractJob::class, [$testData]);

    // assertion
    $this->assertEquals($testData, $stub->getData());
  }
}
