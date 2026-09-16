<?php

namespace TheApp\Tests\Components\Repositories;

use InvalidArgumentException;
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

    public function testFindRouteByNameReturnsFirstMatch()
    {
        $first = $this->addRoute([Route::METHOD_GET], '/users', 'users');
        $this->addRoute([Route::METHOD_POST], '/people', 'users');

        $this->assertSame($first, $this->repository->findRouteByName('users'));
        $this->assertNull($this->repository->findRouteByName('missing'));
    }

    public function testBuildPathFillsParameters()
    {
        $route = $this->addRoute([Route::METHOD_GET], '/users/[i:id]/posts/[a:slug]');

        $this->assertSame('/users/5/posts/hello', $this->repository->buildPath($route, ['id' => 5, 'slug' => 'hello']));
    }

    public function testBuildPathWithoutParametersReturnsPath()
    {
        $route = $this->addRoute([Route::METHOD_GET], '/about');

        $this->assertSame('/about', $this->repository->buildPath($route));
    }

    public function testBuildPathLeavesOutMissingOptionalParameters()
    {
        $route = $this->addRoute([Route::METHOD_GET], '/archive/[i:year]/[i:month]?');

        $this->assertSame('/archive/2024', $this->repository->buildPath($route, ['year' => 2024]));
        $this->assertSame('/archive/2024/5', $this->repository->buildPath($route, ['year' => 2024, 'month' => 5]));
    }

    public function testBuildPathEncodesValuesButKeepsSlashesForWildcards()
    {
        $segment = $this->addRoute([Route::METHOD_GET], '/tags/[:tag]');
        $wildcard = $this->addRoute([Route::METHOD_GET], '/files/[**:path]');

        $this->assertSame('/tags/c%2B%2B%20tips', $this->repository->buildPath($segment, ['tag' => 'c++ tips']));
        $this->assertSame('/files/docs/my%20file.txt', $this->repository->buildPath($wildcard, ['path' => 'docs/my file.txt']));
    }

    public function testBuiltPathMatchesItsRoute()
    {
        $route = $this->addRoute([Route::METHOD_GET], '/users/[i:id]/[:tab]?');
        $path = $this->repository->buildPath($route, ['id' => 42, 'tab' => 'posts']);

        $match = $this->repository->matchRoute($this->request('GET', $path));

        $this->assertSame($route, $match->getRoute());
        $this->assertSame(['id' => '42', 'tab' => 'posts'], $match->getParameters());
    }

    public function testBuildPathThrowsForMissingRequiredParameter()
    {
        $route = $this->addRoute([Route::METHOD_GET], '/users/[i:id]', 'user');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing parameter "id" for route "user"');

        $this->repository->buildPath($route);
    }

    public function testBuildPathThrowsWhenValueDoesNotMatchType()
    {
        $route = $this->addRoute([Route::METHOD_GET], '/users/[i:id]', 'user');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Value "abc" of parameter "id" does not match its type in route "user"');

        $this->repository->buildPath($route, ['id' => 'abc']);
    }

    public function testBuildPathThrowsForAnyPathAndRegexRoutes()
    {
        foreach (['*', '@^/legacy/(?<id>\d+)$'] as $path) {
            try {
                $this->repository->buildPath($this->addRoute([Route::METHOD_GET], $path));
                $this->fail('Expected InvalidArgumentException for ' . $path);
            } catch (InvalidArgumentException $exception) {
                $this->assertStringContainsString('not supported', $exception->getMessage());
            }
        }
    }

    /**
     * @param string[] $methods
     */
    private function addRoute(array $methods, string $path, ?string $name = null): Route
    {
        $route = new Route();
        $route->methods = $methods;
        $route->path = $path;
        $route->name = $name;
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
