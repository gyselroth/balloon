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
            'hard_quota' => 123,
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
            'namespace' => 'test',
        ];

        // execute SUT
        $res = self::$controller->post($user['username'], $user['mail'], $user['namespace']);

        // assertions
        $this->assertInstanceOf(Response::class, $res);
        $this->assertSame(204, $res->getCode());

        $createdUser = self::$server->getUserByName($user['username'])->getAttribute();
        $this->assertSame(10000000, $createdUser['soft_quota']);
        $this->assertSame(10000000, $createdUser['hard_quota']);
    }
}
