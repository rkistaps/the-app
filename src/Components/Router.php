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
