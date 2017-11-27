<?php

declare(strict_types=1);

/**
 * Balloon
 *
 * @author      Raffael Sahli <sahli@gyselroth.net>
 * @copyright   Copryright (c) 2012-2017 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Testsuite\Unit\App\Api\v1\Admin\User;

use Micro\Http\Response;

/**
 * @coversNothing
 */
class ModifyTest extends Test
{
    public function testCreateUser()
    {
        return $this->createUser();
    }

    /**
     * @depends testCreateUser
     *
     * @param mixed $user
     */
    public function testModifyAttributes($user)
    {
        // fixture
        $attributes = [
            'mail' => 'test1@example.org',
            'soft_quota' => 567,
            'hard_quota' => 567,
        ];

        // execute SUT
        $res = self::$controller->postAttributes($attributes, null, $user['username']);

        // assertions
        $this->assertInstanceOf(Response::class, $res);
        $this->assertSame(204, $res->getCode());

        $modifiedUser = self::$server->getUserByName($user['username'])->getAttribute();
        $modifiedUser = $this->removeUnpredictableAttributes($modifiedUser);
        $this->assertSame($user, $modifiedUser);
    }

    /**
     * @depends testCreateUser
     * @expectedException \Balloon\Exception\InvalidArgument
     *
     * @param mixed $user
     */
    public function testModifyInvalidAttributes($user)
    {
        // fixture
        $attributes = [
            'invalid_attribute' => 'something',
        ];

        // execute SUT
        $res = self::$controller->postAttributes($attributes, null, $user['username']);
    }

    /**
     * @depends testCreateUser
     * @expectedException \Balloon\Exception\NotFound
     * @expectedExceptionCode 53
     *
     * @param mixed $user
     */
    public function testModifyInexistingUser($user)
    {
        // execute SUT
        $res = self::$controller->postAttributes([], null, 'notExistingUser');
    }
}
