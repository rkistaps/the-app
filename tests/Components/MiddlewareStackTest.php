<?php

namespace TheApp\Tests\Components;

use Mockery;
use Mockery\Adapter\Phpunit\MockeryTestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TheApp\Components\MiddlewareStack;

class MiddlewareStackTest extends MockeryTestCase
{
    public function testWithoutMiddlewareHandlerHandlesRequest()
    {
        $request = Mockery::mock(ServerRequestInterface::class);
        $response = Mockery::mock(ResponseInterface::class);
        $handler = Mockery::mock(RequestHandlerInterface::class);
        $handler->shouldReceive('handle')->once()->with($request)->andReturn($response);

        $this->assertSame($response, (new MiddlewareStack($handler))->handle($request));
    }

    public function testMiddlewareRunsInOrderAroundHandler()
    {
        $calls = [];
        $request = Mockery::mock(ServerRequestInterface::class);
        $response = Mockery::mock(ResponseInterface::class);

        $handler = Mockery::mock(RequestHandlerInterface::class);
        $handler->shouldReceive('handle')->once()->andReturnUsing(function () use (&$calls, $response) {
            $calls[] = 'handler';
            return $response;
        });

        $stack = new MiddlewareStack($handler, $this->middleware('first', $calls), $this->middleware('second', $calls));

        $this->assertSame($response, $stack->handle($request));
        $this->assertSame(['first before', 'second before', 'handler', 'second after', 'first after'], $calls);
    }

    public function testMiddlewareCanReturnEarly()
    {
        $response = Mockery::mock(ResponseInterface::class);
        $handler = Mockery::mock(RequestHandlerInterface::class);
        $handler->shouldNotReceive('handle');

        $blocking = Mockery::mock(MiddlewareInterface::class);
        $blocking->shouldReceive('process')->once()->andReturn($response);

        $stack = new MiddlewareStack($handler, $blocking);

        $this->assertSame($response, $stack->handle(Mockery::mock(ServerRequestInterface::class)));
    }

    /**
     * @param string[] $calls
     */
    private function middleware(string $name, array &$calls): MiddlewareInterface
    {
        $middleware = Mockery::mock(MiddlewareInterface::class);
        $middleware->shouldReceive('process')->once()->andReturnUsing(
            function (ServerRequestInterface $request, RequestHandlerInterface $next) use ($name, &$calls) {
                $calls[] = $name . ' before';
                $response = $next->handle($request);
                $calls[] = $name . ' after';

                return $response;
            }
        );

        return $middleware;
    }
}
