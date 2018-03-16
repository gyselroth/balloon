<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2018 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Testsuite\Unit\App\Api\Latest\Node;

use Balloon\Filesystem\Exception;
use Balloon\Testsuite\Unit\App\Api\Latest\Test;
use Micro\Http\Response;

abstract class RenameTest extends Test
{
    /**
     * @depends testCreate
     *
     * @param mixed $id
     */
    public function testRename($id)
    {
        $name = uniqid().'.txt';
        $res = $this->controller->postName($name, $id);
        $this->assertInstanceOf(Response::class, $res);
        $this->assertSame(204, $res->getCode());

        return $id;
    }

    /**
     * @depends testRename
     *
     * @param mixed $id
     */
    public function testRenameInvalidChar($id)
    {
        $chars = '\<>:"/*?|';
        $exceptions = 0;

        foreach (str_split($chars) as $char) {
            $name = uniqid().$char;

            try {
                $res = $this->controller->postName($name, $id);
            } catch (Exception\InvalidArgument $e) {
                ++$exceptions;
            }
        }

        $this->assertSame(strlen($chars), $exceptions);
    }
}
