<?php

namespace TheApp\Tests\Structures;

use Mockery\Adapter\Phpunit\MockeryTestCase;
use TheApp\Structures\Route;

class RouteTest extends MockeryTestCase
{
    public function testAllowsListedMethodsCaseInsensitively()
    {
        $route = $this->route([Route::METHOD_PUT, Route::METHOD_PATCH]);

        $this->assertTrue($route->allowsMethod('PUT'));
        $this->assertTrue($route->allowsMethod('patch'));
        $this->assertFalse($route->allowsMethod('GET'));
    }

    public function testHeadIsAllowedByGetRoutes()
    {
        $this->assertTrue($this->route([Route::METHOD_GET])->allowsMethod('HEAD'));
        $this->assertFalse($this->route([Route::METHOD_POST])->allowsMethod('HEAD'));
    }

    public function testAnyAllowsEveryMethod()
    {
        $route = $this->route([Route::METHOD_ANY]);

        $this->assertTrue($route->isAnyMethod());
        foreach (['GET', 'HEAD', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS', 'PURGE'] as $method) {
            $this->assertTrue($route->allowsMethod($method), $method);
        }
    }

    public function testPathKinds()
    {
        $route = $this->route([Route::METHOD_GET]);

        $route->path = '*';
        $this->assertTrue($route->isForAnyPath());
        $this->assertFalse($route->isCustomPath());

        $route->path = '@^/legacy$';
        $this->assertTrue($route->isCustomPath());
        $this->assertFalse($route->hasParameters());

        $route->path = '/users/[i:id]';
        $this->assertFalse($route->isForAnyPath());
        $this->assertTrue($route->hasParameters());
    }

    public function testWithMiddlewareAppendsAndReturnsRoute()
    {
        $route = $this->route([Route::METHOD_GET]);
        $callable = fn() => null;

        $this->assertSame($route, $route->withMiddleware('Auth')->withMiddleware($callable));
        $this->assertSame(['Auth', $callable], $route->middlewares);
    }

    /**
     * @param string[] $methods
     */
    private function route(array $methods): Route
    {
        $route = new Route();
        $route->methods = $methods;
        $route->path = '/';

        return $route;
    }
}
