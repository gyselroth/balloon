<?php
namespace Balloon\Testsuite\Unit\Api\v1\Node;

use \Balloon\Testsuite\Unit\Test;
use \Balloon\Exception;
use \Micro\Http\Response;

abstract class RenameTest extends Test
{
    /**
     * @depends testCreate
     */
    public function testRename($id)
    {
        $name = uniqid().'.txt';
        $res = self::$controller->postName($name, $id);
        $this->assertInstanceOf(Response::class, $res);
        $this->assertEquals(204, $res->getCode());
        return $id;
    }
    
    /**
     * @depends testRename
     */
    public function testRenameInvalidChar($id)
    {
        $chars = '\<>:"/*?|';
        $exceptions = 0;
        
        foreach(str_split($chars) as $char) {
            $name = uniqid().$char;
            try {
                $res = self::$controller->postName($name, $id);
            } catch(Exception\InvalidArgument $e) {
                $exceptions++;
            }
        }

       $this->assertEquals(strlen($chars), $exceptions);
    }
}
