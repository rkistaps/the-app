<?php

namespace TheApp\Tests\Components;

use Mockery;
use Mockery\Adapter\Phpunit\MockeryTestCase;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TheApp\Components\RouteHandler;

class RouteHandlerTest extends MockeryTestCase
{
    public function testHoldsHandlerMiddlewaresAndAttributes()
    {
        $handler = Mockery::mock(RequestHandlerInterface::class);
        $first = Mockery::mock(MiddlewareInterface::class);
        $second = Mockery::mock(MiddlewareInterface::class);

        $routeHandler = new RouteHandler($handler);
        $routeHandler->addMiddlewares($first, $second);
        $routeHandler->addAttribute('id', '5');
        $routeHandler->addAttribute('tab', 'posts');

        $this->assertSame($handler, $routeHandler->getHandler());
        $this->assertSame([$first, $second], $routeHandler->getMiddlewares());
        $this->assertSame(['id' => '5', 'tab' => 'posts'], $routeHandler->getAttributes());
    }
}
