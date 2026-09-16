<?php

namespace TheApp\Tests\Components\Repositories;

use Mockery;
use Mockery\Adapter\Phpunit\MockeryTestCase;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;
use TheApp\Components\Repositories\RouteRepository;
use TheApp\Structures\Route;

class RouteRepositoryTest extends MockeryTestCase
{
    private RouteRepository $repository;

    protected function setUp(): void
    {
        $this->repository = new RouteRepository();
    }

    public function testMatchRouteSkipsRoutesForOtherMethods()
    {
        $get = $this->addRoute([Route::METHOD_GET], '/users');
        $post = $this->addRoute([Route::METHOD_POST], '/users');

        $this->assertSame($post, $this->repository->matchRoute($this->request('POST', '/users'))->getRoute());
        $this->assertSame($get, $this->repository->matchRoute($this->request('HEAD', '/users'))->getRoute());
        $this->assertNull($this->repository->matchRoute($this->request('DELETE', '/users')));
    }

    public function testFindAllowedMethodsForMatchingPaths()
    {
        $this->addRoute([Route::METHOD_GET], '/users/[i:id]');
        $this->addRoute([Route::METHOD_PUT, Route::METHOD_DELETE], '/users/[i:id]');
        $this->addRoute([Route::METHOD_POST], '/users');

        $this->assertSame(
            ['GET', 'HEAD', 'PUT', 'DELETE'],
            $this->repository->findAllowedMethods($this->request('POST', '/users/5'))
        );
    }

    public function testFindAllowedMethodsIsEmptyWhenNoPathMatches()
    {
        $this->addRoute([Route::METHOD_GET], '/users');

        $this->assertSame([], $this->repository->findAllowedMethods($this->request('GET', '/posts')));
    }

    /**
     * @param string[] $methods
     */
    private function addRoute(array $methods, string $path): Route
    {
        $route = new Route();
        $route->methods = $methods;
        $route->path = $path;
        $route->handler = 'Handler';

        $this->repository->addRoute($route);

        return $route;
    }

    private function request(string $method, string $path): ServerRequestInterface
    {
        $uri = Mockery::mock(UriInterface::class);
        $uri->shouldReceive('getPath')->andReturn($path);

        $request = Mockery::mock(ServerRequestInterface::class);
        $request->shouldReceive('getUri')->andReturn($uri);
        $request->shouldReceive('getMethod')->andReturn($method);

        return $request;
    }
}
