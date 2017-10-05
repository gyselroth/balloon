<?php
namespace Balloon\Testsuite\Unit\App\Sharelink;

use \Balloon\Database;
use \Balloon\Database\DatabaseInterface;
use \Balloon\Testsuite\Unit\DatabaseTest as CoreDatabaseTest;

class DatabaseTest extends CoreDatabaseTest
{
    protected $server;

    public function setUp()
    {
        $server = self::setupMockServer();
        $server->getApp()->registerApp('Balloon.App.Sharelink');
        $this->expected_indices[] = 'balloon.storage.app_attributes.Balloon_App_Sharelink.token_1';

        $this->server = $server;
    }
}
