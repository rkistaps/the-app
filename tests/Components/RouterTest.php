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

    protected function tearDown(): void
    {
        Mockery::close();
    }
}