<?php

declare(strict_types=1);

namespace TheApp\Tests\Structures;

use Mockery\Adapter\Phpunit\MockeryTestCase;
use TheApp\Structures\Route;
use TheApp\Structures\RouteGroup;

class RouteGroupTest extends MockeryTestCase
{
    public function testAddMiddlewareAppendsAndReturnsGroup()
    {
        $group = new RouteGroup('/admin');
        $callable = fn() => null;

        $this->assertSame($group, $group->addMiddleware('Auth')->addMiddleware($callable));
        $this->assertSame('/admin', $group->getPrefix());
        $this->assertSame(['Auth', $callable], $group->getMiddlewares());
    }

    public function testParentMiddlewareComesFirst()
    {
        $parent = (new RouteGroup('/admin'))->addMiddleware('Auth');
        $child = (new RouteGroup('/admin/reports', $parent))->addMiddleware('Audit');

        $this->assertSame(['Auth', 'Audit'], $child->getMiddlewares());
    }

    public function testRouteGetsGroupMiddlewareBeforeItsOwn()
    {
        $group = new RouteGroup('/admin');
        $route = (new Route([Route::METHOD_GET], '/admin/users', 'Handler', null, $group))->addMiddleware('Cache');
        $group->addMiddleware('Auth');

        $this->assertSame(['Auth', 'Cache'], $route->getMiddlewares());
        $this->assertSame($group, $route->getGroup());
    }
}
