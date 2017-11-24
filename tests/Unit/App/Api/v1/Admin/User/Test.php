<?php
namespace Balloon\Testsuite\Unit\App\Api\v1\Admin\User;

use \Balloon\Api\v1\Admin\User;
use \Micro\Http\Response;
use \Balloon\Testsuite\Unit\App\Api\v1\Admin\Test as ApiTest;

abstract class Test extends ApiTest
{
    protected static $server;

    public static function setUpBeforeClass()
    {
        self::$server = self::setupMockServer();
        self::$controller = new User(self::$server, self::$server->getLogger());
    }

    public function createUser()
    {
        // fixture
        $user = [
            'username' => 'test.user1',
            'mail' => 'test.user1@example.com',
            'namespace' => 'test',
            'soft_quota' => 123,
            'hard_quota' => 123
        ];
        // execute SUT
        $res = self::$controller->post($user['username'], $user['mail'], $user['namespace'], null, $user['hard_quota'], $user['soft_quota']);

        // assertions
        $this->assertInstanceOf(Response::class, $res);
        $this->assertEquals(204, $res->getCode());

        $createdUser = self::$server->getUserByName($user['username'])->getAttribute();
        // unset unpredictable properties to be able to compare
        $createdUser = $this->removeUnpredictableAttributes($createdUser);
        $this->assertEquals($user, $createdUser);

        return $createdUser;
    }

    protected function removeUnpredictableAttributes($user)
    {
        $this->assertArrayHasKey('id', $user);
        $this->assertArrayHasKey('created', $user);
        unset($user['id']);
        unset($user['created']);

        return $user;
    }
}
