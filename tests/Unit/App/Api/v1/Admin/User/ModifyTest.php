<?php
namespace Balloon\Testsuite\Unit\App\Api\v1\Admin\User;

use \Micro\Http\Response;

class ModifyTest extends Test
{
    public function testCreateUser()
    {
        return $this->createUser();
    }

    /**
     * @depends testCreateUser
     */
    public function testModifyAttributes($user)
    {
        // fixture
        $attributes = [
            'mail' => 'test1@example.org',
            'soft_quota' => 567,
            'hard_quota' => 567
        ];

        // execute SUT
        $res = self::$controller->postAttributes($attributes, null, $user['username']);

        // assertions
        $this->assertInstanceOf(Response::class, $res);
        $this->assertEquals(204, $res->getCode());

        $modifiedUser = self::$server->getUserByName($user['username'])->getAttribute();
        $modifiedUser = $this->removeUnpredictableAttributes($modifiedUser);
        $this->assertEquals($user, $modifiedUser);
    }

    /**
     * @depends testCreateUser
     * @expectedException \Balloon\Exception\InvalidArgument
     */
    public function testModifyInvalidAttributes($user)
    {
        // fixture
        $attributes = [
            'invalid_attribute' => 'something'
        ];

        // execute SUT
        $res = self::$controller->postAttributes($attributes, null, $user['username']);
    }

    /**
     * @depends testCreateUser
     * @expectedException \Balloon\Exception\NotFound
     * @expectedExceptionCode 53
     */
    public function testModifyInexistingUser($user)
    {
        // execute SUT
        $res = self::$controller->postAttributes([], null, 'notExistingUser');
    }
}
