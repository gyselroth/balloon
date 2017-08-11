<?php
namespace Balloon\Testsuite\Unit\Api\v1\Node;

use Balloon\Testsuite\Unit\Test;

abstract class RenameTest extends Test
{
    /**
     * @depends testCreate
     */
    public function testRename($id)
    {
        $name = uniqid().'.txt';
        $res = $this->request('POST', '/'.$this->type.'/name?id='.$id.'&name='.$name);
        $this->assertEquals(204, $res->getStatusCode());
        return $id;
    }
    
    /**
     * @depends testRename
     */
    public function testRenameInvalidChar($id)
    {
        $chars = '\<>:"/*?|';

        foreach(str_split($chars) as $char) {
            $name = uniqid().$char;
            $res = $this->request('POST', '/'.$this->type.'/name?id='.$id.'&name='.$name);
            $this->assertEquals(400, $res->getStatusCode());
            $body = $this->jsonBody($res);
            $this->assertEquals('Balloon\\Exception\\InvalidArgument', $body['error']);
        }
    }
}
