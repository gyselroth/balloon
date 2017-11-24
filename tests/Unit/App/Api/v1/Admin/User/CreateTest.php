<?php
namespace Balloon\Testsuite\Unit\App\Api\v1\Admin\User;

use \Balloon\Api\v1\Admin\User;
use \Micro\Http\Response;

class CreateTest extends Test
{
    public function testCreateFirst()
    {
        return $this->createUser();
    }

    /**
     * @depends testCreateFirst
     * @expectedException \Balloon\Exception\Conflict
     * @expectedExceptionCode 17
     */
    public function testCreateSameAgain()
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
        $res = self::$controller->post($user['username'], $user['mail'], $user['namespace']);
    }

    /**
     * @depends testCreateFirst
     */
    public function testCreateWithDefaultQuotas()
    {
        // fixture
        $user = [
            'username' => 'test.user2',
            'mail' => 'test.user2@example.com',
            'namespace' => 'test'
        ];

        // execute SUT
        $res = self::$controller->post($user['username'], $user['mail'], $user['namespace']);

        // assertions
        $this->assertInstanceOf(Response::class, $res);
        $this->assertEquals(204, $res->getCode());

        $createdUser = self::$server->getUserByName($user['username'])->getAttribute();
        $this->assertEquals(10000000, $createdUser['soft_quota']);
        $this->assertEquals(10000000, $createdUser['hard_quota']);
    }
}
