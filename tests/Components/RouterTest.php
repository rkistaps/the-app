<?php

namespace TheApp\Tests\Components;

use DI\Container;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryTestCase;
use TheApp\Components\Repositories\RouteRepository;
use TheApp\Factories\RequestHandlerFactory;
use TheApp\Components\Router;
use TheApp\Exceptions\InvalidConfigException;
use TheApp\Exceptions\NoRouteMatchException;
use Psr\Http\Message\ServerRequestInterface;
use TheApp\Interfaces\RouteHandlerInterface;
use TheApp\Structures\Route;
use TheApp\Structures\RouteMatchResult;

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

        $this->router = new Router($this->repository, $this->requestHandlerFactory, $this->container);
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

        $this->expectException(NoRouteMatchException::class);

        $request = Mockery::mock(ServerRequestInterface::class);
        $this->router->getRouteHandler($request);
    }

    public function testMethodHelpersRegisterRoutesForTheirMethod()
    {
        $this->repository->shouldReceive('addRoute')->times(7);

        $this->assertSame(['GET'], $this->router->get('/a', 'Handler')->methods);
        $this->assertSame(['POST'], $this->router->post('/a', 'Handler')->methods);
        $this->assertSame(['PUT'], $this->router->put('/a', 'Handler')->methods);
        $this->assertSame(['PATCH'], $this->router->patch('/a', 'Handler')->methods);
        $this->assertSame(['DELETE'], $this->router->delete('/a', 'Handler')->methods);
        $this->assertSame(['OPTIONS'], $this->router->options('/a', 'Handler')->methods);
        $this->assertSame(['ANY'], $this->router->any('/a', 'Handler')->methods);
    }

    public function testMapRegistersRouteForSeveralMethods()
    {
        $this->repository->shouldReceive('addRoute')->once();

        $route = $this->router->map(['get', 'POST', 'GET'], '/users', 'Handler', 'users');

        $this->assertSame(['GET', 'POST'], $route->methods);
        $this->assertSame('/users', $route->path);
        $this->assertSame('users', $route->name);
    }

    public function testRouteMethodsApplyBasePath()
    {
        $this->repository->shouldReceive('addRoute');
        $router = $this->router->withBasePath('/api');

        $this->assertEquals('/api/users', $router->get('/users', 'Handler')->path);
        $this->assertEquals('/api/users', $router->post('/users', 'Handler')->path);
        $this->assertEquals('/api/users', $router->any('/users', 'Handler')->path);
    }

    public function testBasePathSkipsAnyAndCustomPaths()
    {
        $this->repository->shouldReceive('addRoute');
        $router = $this->router->withBasePath('/api');

        $this->assertEquals('*', $router->any('*', 'Handler')->path);
        $this->assertEquals('@^/users/\d+$', $router->get('@^/users/\d+$', 'Handler')->path);
    }

    protected function tearDown(): void
    {
        Mockery::close();
    }
}