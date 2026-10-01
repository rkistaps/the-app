<?php

declare(strict_types=1);

namespace TheApp\Tests\Components;

use DI\Container;
use InvalidArgumentException;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryTestCase;
use TheApp\Components\Repositories\RouteRepository;
use TheApp\Factories\MiddlewareStackFactory;
use TheApp\Factories\RequestHandlerFactory;
use TheApp\Components\Router;
use TheApp\Exceptions\MethodNotAllowedException;
use TheApp\Exceptions\NoRouteMatchException;
use Psr\Http\Message\ServerRequestInterface;
use TheApp\Structures\Route;

class RouterTest extends MockeryTestCase
{
    private $repository;
    private $container;
    private $requestHandlerFactory;
    private $router;

    protected function setUp(): void
    {
        $this->repository = Mockery::mock(RouteRepository::class);
        $this->container = Mockery::mock(Container::class);
        $this->requestHandlerFactory = Mockery::mock(RequestHandlerFactory::class);

        $this->router = new Router($this->repository, $this->requestHandlerFactory, new MiddlewareStackFactory($this->container));
    }

    public function testWithBasePath()
    {
        $basePath = '/api';
        $newRouter = $this->router->withBasePath($basePath);

        $this->assertNotSame($this->router, $newRouter);
        $this->assertEquals($basePath, $newRouter->getBasePath());
    }

    public function testGetRouteHandlerNoMatch()
    {
        $this->repository->shouldReceive('matchRoute')->andReturn(null);
        $this->repository->shouldReceive('findAllowedMethods')->andReturn([]);

        $request = Mockery::mock(ServerRequestInterface::class);

        try {
            $this->router->getRouteHandler($request);
            $this->fail('Expected NoRouteMatchException');
        } catch (NoRouteMatchException $exception) {
            $this->assertNotInstanceOf(MethodNotAllowedException::class, $exception);
        }
    }

    public function testGetRouteHandlerMethodNotAllowed()
    {
        $this->repository->shouldReceive('matchRoute')->andReturn(null);
        $this->repository->shouldReceive('findAllowedMethods')->andReturn(['GET', 'HEAD']);

        $request = Mockery::mock(ServerRequestInterface::class);

        try {
            $this->router->getRouteHandler($request);
            $this->fail('Expected MethodNotAllowedException');
        } catch (MethodNotAllowedException $exception) {
            $this->assertInstanceOf(NoRouteMatchException::class, $exception);
            $this->assertSame(['GET', 'HEAD'], $exception->getAllowedMethods());
        }
    }

    public function testUrlBuildsPathOfNamedRoute()
    {
        $route = new Route([Route::METHOD_GET], '/users/[i:id]', 'Handler', 'user');
        $this->repository->shouldReceive('findRouteByName')->with('user')->andReturn($route);
        $this->repository->shouldReceive('buildPath')->with($route, ['id' => 5])->andReturn('/users/5');

        $this->assertSame('/users/5', $this->router->url('user', ['id' => 5]));
    }

    public function testUrlThrowsForUnknownName()
    {
        $this->repository->shouldReceive('findRouteByName')->andReturn(null);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('No route named "missing"');

        $this->router->url('missing');
    }

    public function testMethodHelpersRegisterRoutesForTheirMethod()
    {
        $this->repository->shouldReceive('addRoute')->times(7);

        $this->assertSame(['GET'], $this->router->get('/a', 'Handler')->getMethods());
        $this->assertSame(['POST'], $this->router->post('/a', 'Handler')->getMethods());
        $this->assertSame(['PUT'], $this->router->put('/a', 'Handler')->getMethods());
        $this->assertSame(['PATCH'], $this->router->patch('/a', 'Handler')->getMethods());
        $this->assertSame(['DELETE'], $this->router->delete('/a', 'Handler')->getMethods());
        $this->assertSame(['OPTIONS'], $this->router->options('/a', 'Handler')->getMethods());
        $this->assertSame(['ANY'], $this->router->any('/a', 'Handler')->getMethods());
    }

    public function testMapRegistersRouteForSeveralMethods()
    {
        $this->repository->shouldReceive('addRoute')->once();

        $route = $this->router->map(['get', 'POST', 'GET'], '/users', 'Handler', 'users');

        $this->assertSame(['GET', 'POST'], $route->getMethods());
        $this->assertSame('/users', $route->getPath());
        $this->assertSame('users', $route->getName());
    }

    public function testRouteMethodsApplyBasePath()
    {
        $this->repository->shouldReceive('addRoute');
        $router = $this->router->withBasePath('/api');

        $this->assertEquals('/api/users', $router->get('/users', 'Handler')->getPath());
        $this->assertEquals('/api/users', $router->post('/users', 'Handler')->getPath());
        $this->assertEquals('/api/users', $router->any('/users', 'Handler')->getPath());
    }

    public function testBasePathSkipsAnyAndCustomPaths()
    {
        $this->repository->shouldReceive('addRoute');
        $router = $this->router->withBasePath('/api');

        $this->assertEquals('*', $router->any('*', 'Handler')->getPath());
        $this->assertEquals('@^/users/\d+$', $router->get('@^/users/\d+$', 'Handler')->getPath());
    }

    public function testGroupPrefixesPathsAndPutsRoutesInGroup()
    {
        $this->repository->shouldReceive('addRoute');
        $routes = [];

        $group = $this->router->withBasePath('/api')->group('/admin', function (Router $admin) use (&$routes) {
            $routes[] = $admin->get('/users', 'Handler');
            $routes[] = $admin->any('*', 'Handler');
        });

        $this->assertSame('/api/admin', $group->getPrefix());
        $this->assertSame('/api/admin/users', $routes[0]->getPath());
        $this->assertSame('*', $routes[1]->getPath());
        $this->assertSame($group, $routes[0]->getGroup());
    }

    public function testRoutesOutsideGroupAreNotInIt()
    {
        $this->repository->shouldReceive('addRoute');

        $this->router->group('/admin', function (Router $admin) {
            $admin->get('/users', 'Handler');
        });
        $route = $this->router->get('/users', 'Handler');

        $this->assertSame('/users', $route->getPath());
        $this->assertNull($route->getGroup());
    }

    public function testNestedGroupsAddUpPrefixesAndMiddleware()
    {
        $this->repository->shouldReceive('addRoute');
        $route = null;

        $outer = $this->router->group('/admin', function (Router $admin) use (&$route, &$inner) {
            $inner = $admin->group('/reports', function (Router $reports) use (&$route) {
                $route = $reports->get('/daily', 'Handler')->addMiddleware('RouteMiddleware');
            });
            $inner->addMiddleware('InnerMiddleware');
        });
        // Added after the routes were registered, and still applied
        $outer->addMiddleware('OuterMiddleware');

        $this->assertSame('/admin/reports/daily', $route->getPath());
        $this->assertSame(['OuterMiddleware', 'InnerMiddleware', 'RouteMiddleware'], $route->getMiddlewares());
    }

    protected function tearDown(): void
    {
        Mockery::close();
    }
}