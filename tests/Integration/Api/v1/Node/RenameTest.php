<?php

declare(strict_types=1);

/**
 * Balloon
 *
 * @author      Raffael Sahli <sahli@gyselroth.net>
 * @copyright   Copryright (c) 2012-2017 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Testsuite\Api\v1\Node;

use Balloon\Testsuite\Test;

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
        $res = $this->request('POST', '/'.$this->type.'/name?id='.$id.'&name='.$name);
        $this->assertSame(204, $res->getStatusCode());

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

        foreach (str_split($chars) as $char) {
            $name = uniqid().$char;
            $res = $this->request('POST', '/'.$this->type.'/name?id='.$id.'&name='.$name);
            $this->assertSame(400, $res->getStatusCode());
            $body = $this->jsonBody($res);
            $this->assertSame('Balloon\\Exception\\InvalidArgument', $body['error']);
        }
    }
}
