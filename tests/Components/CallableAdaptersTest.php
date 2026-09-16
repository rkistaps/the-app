<?php

namespace TheApp\Tests\Components;

use DI\Container;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryTestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use stdClass;
use TheApp\Components\CallableMiddleware;
use TheApp\Components\CallableRequestHandler;

class CallableAdaptersTest extends MockeryTestCase
{
    private Container $container;

    protected function setUp(): void
    {
        $this->container = new Container();
    }

    public function testCallableRequestHandlerPassesRequestAndResolvesOtherParameters()
    {
        $service = new stdClass();
        $this->container->set(stdClass::class, $service);

        $request = Mockery::mock(ServerRequestInterface::class);
        $response = Mockery::mock(ResponseInterface::class);

        $received = null;
        $handler = new CallableRequestHandler(
            function (ServerRequestInterface $request, stdClass $service) use (&$received, $response) {
                $received = [$request, $service];
                return $response;
            },
            $this->container
        );

        $this->assertSame($response, $handler->handle($request));
        $this->assertSame([$request, $service], $received);
    }

    public function testCallableMiddlewarePassesRequestAndNextHandler()
    {
        $request = Mockery::mock(ServerRequestInterface::class);
        $response = Mockery::mock(ResponseInterface::class);
        $next = Mockery::mock(RequestHandlerInterface::class);
        $next->shouldReceive('handle')->once()->with($request)->andReturn($response);

        $middleware = new CallableMiddleware(
            fn(ServerRequestInterface $request, RequestHandlerInterface $next) => $next->handle($request),
            $this->container
        );

        $this->assertSame($response, $middleware->process($request, $next));
    }
}
