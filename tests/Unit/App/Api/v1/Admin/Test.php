<?php
namespace Balloon\Testsuite\Unit\App\Api\v1\Admin;

use \Balloon\Testsuite\Unit\Test as UnitTest;
use \Balloon\Testsuite\Unit\Mock;


abstract class Test extends UnitTest
{
    public static function setupMockServer($context='cli')
    {
        $server = parent::setupMockServer($context);
        $identity   = new Mock\Identity('adminuser', ['admin' => true], self::$logger);
        $server->addUser(['username' => 'adminuser']);
        $server->setIdentity($identity);
        return $server;
    }
}
