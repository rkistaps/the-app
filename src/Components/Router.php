<?php

namespace TheApp\Components;

use DI\Container;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TheApp\Components\Repositories\RouteRepository;
use TheApp\Factories\RequestHandlerFactory;
use TheApp\Exceptions\InvalidConfigException;
use TheApp\Exceptions\NoRouteMatchException;
use TheApp\Interfaces\RouteHandlerInterface;
use TheApp\Interfaces\RouterInterface;
use TheApp\Structures\Route;
use TheApp\Structures\RouteMatchResult;

/**
 * Class Router
 * @package TheApp\Components
 */
class Router implements RouterInterface
{
    private string $basePath = '';

    public function __construct(
        private RouteRepository $repository,
        private RequestHandlerFactory $requestHandlerFactory,
        private Container $container
    ) {
    }

    public function withBasePath(string $basePath): Router
    {
        $router = clone $this;
        $router->basePath = $basePath;

        return $router;
    }

    public function getBasePath(): string
    {
        return $this->basePath;
    }

    /**
     * @throws NoRouteMatchException|InvalidConfigException
     */
    public function getRouteHandler(ServerRequestInterface $request): RouteHandlerInterface
    {
        $matchResult = $this->repository->matchRoute($request);
        if (!$matchResult) {
            throw new NoRouteMatchException('No route match');
        }

        return $this->initializeRoute($matchResult);
    }

    protected function initializeRoute(RouteMatchResult $matchResult): RouteHandlerInterface
    {
        $route = $matchResult->getRoute();

        $requestHandler = $this->requestHandlerFactory->fromRoute($route);

        $handler = new RouteHandler($requestHandler);
        $handler->addMiddlewares(
            ...
            array_map(
                fn($middleware) => is_callable($middleware)
                    ? new CallableMiddleware($middleware, $this->container)
                    : $this->container->get($middleware),
                $route->middlewares
            )
        );

        foreach ($matchResult->getParameters() as $name => $value) {
            $handler->addAttribute($name, $value);
        }

        return $handler;
    }

    /**
     * Add route for GET requests. HEAD requests to the path are matched too.
     */
    public function get(string $path, callable|string $handler, ?string $name = null): Route
    {
        return $this->map([Route::METHOD_GET], $path, $handler, $name);
    }

    /**
     * Add route for POST requests
     */
    public function post(string $path, callable|string $handler, ?string $name = null): Route
    {
        return $this->map([Route::METHOD_POST], $path, $handler, $name);
    }

    /**
     * Add route for PUT requests
     */
    public function put(string $path, callable|string $handler, ?string $name = null): Route
    {
        return $this->map([Route::METHOD_PUT], $path, $handler, $name);
    }

    /**
     * Add route for PATCH requests
     */
    public function patch(string $path, callable|string $handler, ?string $name = null): Route
    {
        return $this->map([Route::METHOD_PATCH], $path, $handler, $name);
    }

    /**
     * Add route for DELETE requests
     */
    public function delete(string $path, callable|string $handler, ?string $name = null): Route
    {
        return $this->map([Route::METHOD_DELETE], $path, $handler, $name);
    }

    /**
     * Add route for OPTIONS requests
     */
    public function options(string $path, callable|string $handler, ?string $name = null): Route
    {
        return $this->map([Route::METHOD_OPTIONS], $path, $handler, $name);
    }

    /**
     * Add route for any type of request
     */
    public function any(string $path, callable|string $handler, ?string $name = null): Route
    {
        return $this->map([Route::METHOD_ANY], $path, $handler, $name);
    }

    /**
     * Add route for several HTTP methods, such as ['GET', 'POST']
     * @param string[] $methods
     */
    public function map(array $methods, string $path, callable|string $handler, ?string $name = null): Route
    {
        $route = $this->buildRoute($methods, $this->withBasePathPrefix($path), $handler, $name);

        $this->repository->addRoute($route);

        return $route;
    }

    /**
     * Prefix a route path with the base path. The "*" (any path) and "@" (custom regex) paths
     * are special forms that a prefix would break, so they are left as they are.
     */
    private function withBasePathPrefix(string $path): string
    {
        if ($path === '*' || str_starts_with($path, '@')) {
            return $path;
        }

        return $this->basePath . $path;
    }

    /**
     * @param string[] $methods
     */
    protected function buildRoute(array $methods, string $path, callable|string $handler, ?string $name = null): Route
    {
        $route = new Route();
        $route->methods = array_values(array_unique(array_map('strtoupper', $methods)));
        $route->path = $path;
        $route->handler = $handler;
        $route->name = $name;

        return $route;
    }
}
