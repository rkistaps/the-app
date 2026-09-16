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

    /**
     * @return iterable<string, array{string, string, array<string, string>|null}>
     */
    public static function paths(): iterable
    {
        yield 'exact path' => ['/about', '/about', []];
        yield 'exact path mismatch' => ['/about', '/about-us', null];
        yield 'integer' => ['/users/[i:id]', '/users/42', ['id' => '42']];
        yield 'integer rejects letters' => ['/users/[i:id]', '/users/abc', null];
        yield 'alphanumeric' => ['/posts/[a:slug]', '/posts/Post1', ['slug' => 'Post1']];
        yield 'alphanumeric rejects dash' => ['/posts/[a:slug]', '/posts/my-post', null];
        yield 'hexadecimal' => ['/colors/[h:hex]', '/colors/ff00AA', ['hex' => 'ff00AA']];
        yield 'hexadecimal rejects g' => ['/colors/[h:hex]', '/colors/fg', null];
        yield 'segment' => ['/pages/[:name]', '/pages/about-us', ['name' => 'about-us']];
        yield 'segment stops at slash' => ['/pages/[:name]', '/pages/a/b', null];
        yield 'segment stops at dot' => ['/files/[:name].[:ext]', '/files/report.pdf', ['name' => 'report', 'ext' => 'pdf']];
        yield 'lazy wildcard' => ['/files/[*:path]', '/files/docs/a.txt', ['path' => 'docs/a.txt']];
        yield 'greedy wildcard' => ['/files/[**:path]', '/files/docs/a.txt', ['path' => 'docs/a.txt']];
        yield 'optional given' => ['/archive/[i:year]/[i:month]?', '/archive/2024/5', ['year' => '2024', 'month' => '5']];
        yield 'optional missing' => ['/archive/[i:year]/[i:month]?', '/archive/2024', ['year' => '2024']];
        yield 'custom type regex' => ['/codes/[\d{3}:code]', '/codes/123', ['code' => '123']];
        yield 'custom type regex mismatch' => ['/codes/[\d{3}:code]', '/codes/12', null];
        yield 'raw regex' => ['@^/legacy/(?<id>\d+)$', '/legacy/7', ['id' => '7']];
        yield 'raw regex mismatch' => ['@^/legacy/(?<id>\d+)$', '/legacy/x', null];
        yield 'any path' => ['*', '/anything/at/all', []];
        yield 'different prefix' => ['/users/[i:id]', '/people/42', null];
    }

    /**
     * @param array<string, string>|null $expected
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('paths')]
    public function testPathMatching(string $routePath, string $requestPath, ?array $expected)
    {
        $this->addRoute([Route::METHOD_GET], $routePath);

        $match = $this->repository->matchRoute($this->request('GET', $requestPath));

        $this->assertSame($expected, $match?->getParameters());
    }

    public function testFirstRegisteredMatchWins()
    {
        $first = $this->addRoute([Route::METHOD_GET], '/users/[i:id]');
        $this->addRoute([Route::METHOD_GET], '/users/[:name]');

        $this->assertSame($first, $this->repository->matchRoute($this->request('GET', '/users/5'))->getRoute());
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
