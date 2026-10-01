<?php

declare(strict_types=1);

namespace TheApp\Tests\Structures;

use Mockery\Adapter\Phpunit\MockeryTestCase;
use TheApp\Structures\Route;

class RouteTest extends MockeryTestCase
{
    public function testConstructorKeepsValuesAndNormalizesMethods()
    {
        $route = new Route(['get', 'post', 'GET'], '/users', 'Handler', 'users');

        $this->assertSame(['GET', 'POST'], $route->getMethods());
        $this->assertSame('/users', $route->getPath());
        $this->assertSame('Handler', $route->getHandler());
        $this->assertSame('users', $route->getName());
        $this->assertSame([], $route->getMiddlewares());
    }

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
        $anyPath = $this->route([Route::METHOD_GET], '*');
        $this->assertTrue($anyPath->isForAnyPath());
        $this->assertFalse($anyPath->isCustomPath());

        $regex = $this->route([Route::METHOD_GET], '@^/legacy$');
        $this->assertTrue($regex->isCustomPath());
        $this->assertFalse($regex->hasParameters());

        $parameters = $this->route([Route::METHOD_GET], '/users/[i:id]');
        $this->assertFalse($parameters->isForAnyPath());
        $this->assertTrue($parameters->hasParameters());
    }

    public function testAddMiddlewareAppendsAndReturnsRoute()
    {
        $route = $this->route([Route::METHOD_GET]);
        $callable = fn() => null;

        $this->assertSame($route, $route->addMiddleware('Auth')->addMiddleware($callable));
        $this->assertSame(['Auth', $callable], $route->getMiddlewares());
    }

    /**
     * @param string[] $methods
     */
    private function route(array $methods, string $path = '/'): Route
    {
        return new Route($methods, $path, 'Handler');
    }
}
