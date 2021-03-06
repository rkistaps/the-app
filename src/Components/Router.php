<?php

namespace TheApp\Components;

use DI\Container;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TheApp\Components\Repositories\RouteRepository;
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
    private RouteRepository $repository;
    private Container $container;

    private string $basePath = '';

    public function __construct(
        RouteRepository $repository,
        Container $container
    ) {
        $this->repository = $repository;
        $this->container = $container;
    }

    public function withBasePath(string $basePath): Router
    {
        $router = clone $this;
        $router->basePath = $basePath;

        return $router;
    }

    /**
     * @param ServerRequestInterface $request
     * @return RouteHandlerInterface
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

        $handler = is_callable($route->handler)
            ? new CallableRequestHandler($route->handler, $this->container)
            : $this->container->get($route->handler);

        if (!is_a($handler, RequestHandlerInterface::class)) {
            throw new InvalidConfigException(get_class($handler) . ' does not implement ' . RequestHandlerInterface::class);
        }

        $handler = new RouteHandler($handler);
        $handler->addMiddlewares(...array_map(function (string $middlewareClassName) {
            return $this->container->get($middlewareClassName);
        }, $route->middlewareClassnames));

        foreach ($matchResult->getParameters() as $name => $value) {
            $handler->addAttribute($name, $value);
        }

        return $handler;
    }

    /**
     * Add route for GET request
     * @param string $path
     * @param string|callable $handler
     * @param string|null $name
     * @return Route
     */
    public function get(string $path, $handler, string $name = null): Route
    {
        $route = $this->buildRoute(Route::METHOD_GET, $this->basePath . $path, $handler, $name);

        $this->repository->addRoute($route);

        return $route;
    }

    /**
     * Add route for POST request
     * @param string $path
     * @param string|callable $handler
     * @param string|null $name
     * @return Route
     */
    public function post(string $path, $handler, string $name = null): Route
    {
        $route = $this->buildRoute(Route::METHOD_POST, $this->basePath . $path, $handler, $name);

        $this->repository->addRoute($route);

        return $route;
    }

    /**
     * Add route for any type of request
     * @param string $path
     * @param string|callable $handler
     * @param string|null $name
     * @return Route
     */
    public function any(string $path, $handler, string $name = null): Route
    {
        $route = $this->buildRoute(Route::METHOD_ANY, $path, $handler, $name);

        $this->repository->addRoute($route);

        return $route;
    }

    public function buildRoute(string $method, string $path, $handler, string $name = null): Route
    {
        $route = new Route();
        $route->method = $method;
        $route->path = $path;
        $route->handler = $handler;
        $route->name = $name;

        return $route;
    }
}
