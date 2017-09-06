<?php
namespace Balloon\Testsuite\Unit\Http\Router;

use \Balloon\Testsuite\Unit\Test;
use \Balloon\Testsuite\Unit\Mock;
use \Balloon\Http\Router;

class TestException extends \Exception
{
}

class SendExceptionTest extends Test
{
    protected $router;
    protected $body;

    protected static $logger;

    public static function setUpBeforeClass()
    {
        self::$logger = new Mock\Log();
    }

    public function setUp()
    {
        // setup mock
        $this->router = $this->getMockBuilder(Router::class)
            ->setConstructorArgs([[], self::$logger])
            ->setMethods(['sendResponse'])
            ->getMock();

        // prepare fixture
        $this->body = [
            'message' => 'message',
            'code' => 0,
        ];
    }

    public function testBalloonExceptionInvalidArgument()
    {
        $this->exceptionTest(\Balloon\Exception\InvalidArgument::class, 400);
    }

    public function testMicroHttpException()
    {
        $this->exceptionTest(\Micro\Http\Exception::class, 400);
    }

    public function testBalloonExceptionNotFound()
    {
        $this->exceptionTest(\Balloon\Exception\NotFound::class, 404);
    }

    public function testBalloonExceptionForbidden()
    {
        $this->exceptionTest(\Balloon\Exception\Forbidden::class, 403);
    }

    public function testBalloonExceptionInsufficientStorage()
    {
        $this->exceptionTest(\Balloon\Exception\InsufficientStorage::class, 507);
    }

    public function testAnyException()
    {
        $this->exceptionTest(TestException::class, 500);
    }

    private function exceptionTest(string $exceptionClass, int $httpStatus)
    {
        // complete fixture
        $this->body['error'] = $exceptionClass;

        // assertion
        $this->router->expects($this->once())
            ->method('sendResponse')
            ->with(
                $this->equalTo($httpStatus),
                $this->equalTo($this->body)
            );

        // execute SUT
        $this->router->sendException(
            new $this->body['error']($this->body['message'], $this->body['code'])
        );
    }
}
