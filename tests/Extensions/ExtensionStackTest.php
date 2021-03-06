<?php

use mobypolo\RoadRunnerLumen\Extensions\ExtensionInterface;
use mobypolo\RoadRunnerLumen\Extensions\ExtensionStack;
use mobypolo\RoadRunnerLumen\WorkerError;
use Illuminate\Container\Container;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;

class ExtensionStackTest extends TestCase
{
    public function testAfterRequestNotTerminated()
    {
        $extensions = [
            $this->mockExtension(['afterRequest' => false]),
            $this->mockExtension(['afterRequest' => false]),
        ];

        $stack = new ExtensionStack($extensions);

        $result = $stack->afterRequest(
            $this->mockContainer(),
            new ServerRequest('GET', '/'),
            new Response
        );

        $this->assertEquals(false, $result);
    }

    public function testAfterRequestTerminated()
    {
        $extensions = [
            $this->mockExtension(['afterRequest' => false]),
            $this->mockExtension(['afterRequest' => false]),
            $this->mockExtension(['afterRequest' => true]),
            $this->mockExtension(['afterRequest' => false]),
        ];

        $stack = new ExtensionStack($extensions);

        $result = $stack->afterRequest(
            $this->mockContainer(),
            new ServerRequest('GET', '/'),
            new Response
        );

        $this->assertEquals(true, $result);
    }

    public function testHandleRequestHandled()
    {
        $extensions = [
            $this->mockExtension(['handleRequest' => false]),
            $this->mockExtension(['handleRequest' => false]),
            $this->mockExtension(['handleRequest' => true]),
            $this->mockExtension([], ['handleRequest' => false]),
        ];

        $stack = new ExtensionStack($extensions);

        $result = $stack->handleRequest(
            $this->mockContainer(),
            $this->prophesize(\Spiral\RoadRunner\PSR7Client::class)->reveal(),
            new ServerRequest('GET', '/')
        );

        $this->assertEquals(true, $result);
    }

    public function testHandleRequestUnhandled()
    {
        $extensions = [
            $this->mockExtension(['handleRequest' => false]),
            $this->mockExtension(['handleRequest' => false]),
        ];

        $stack = new ExtensionStack($extensions);

        $result = $stack->handleRequest(
            $this->mockContainer(),
            $this->prophesize(\Spiral\RoadRunner\PSR7Client::class)->reveal(),
            new ServerRequest('GET', '/')
        );

        $this->assertEquals(false, $result);
    }

    public function testError()
    {
        $e1 = new WorkerError(new Exception('test'));
        $e2 = new WorkerError(new Exception('test2'));

        $extensions = [
            $this->mockExtension(['error' => $e1]),
            $this->mockExtension(['error' => $e2])
        ];

        $stack = new ExtensionStack($extensions);

        $result = $stack->error($this->mockContainer(), new ServerRequest('GET', '/'), $e1);

        $this->assertEquals($e2, $result);
    }

    protected function mockContainer(): Container
    {
        return $this->prophesize(Container::class)->reveal();
    }

    protected function mockExtension(array $shouldBeCalled = [], array $shouldNotBeCalled = []): ExtensionInterface
    {
        $mock = $this->prophesize(ExtensionInterface::class);

        foreach ($shouldBeCalled as $method => $result) {
            $mock->$method(Argument::cetera())->willReturn($result)->shouldBeCalled();
        }

        foreach ($shouldNotBeCalled as $method => $result) {
            $mock->$method(Argument::cetera())->willReturn($result)->shouldNotBeCalled();
        }

        return $mock->reveal();
    }
}
