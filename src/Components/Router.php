<?php

namespace TheApp\Components;

use Exception;
use Psr\Http\Message\RequestInterface;
use TheApp\Factories\RouteFactory;
use TheApp\Structures\Route;

/**
 * Class Router
 * @package TheApp\Components
 */
class Router
{
    private RouteFactory $routeFactory;
    private array $routes = [];

    public function __construct(
        RouteFactory $routeFactory
    ) {
        $this->routeFactory = $routeFactory;
    }

    /**
     * Add route for GET request
     * @param string $path
     * @param string|callable $target
     * @param string|null $name
     * @return Route
     */
    public function get(string $path, $target, string $name = null): Route
    {
        $route = $this->routeFactory->buildRoute(Route::METHOD_GET, $path, $target, $name);

        $this->addRoute($route);

        return $route;
    }

    public function addRoute(Route $route)
    {
        $this->routes[] = $route;

        return $this;
    }

    /**
     * Add route for POST request
     * @param string $path
     * @param string|callable $target
     * @param string $name
     * @return Route
     */
    public function post(string $path, $target, string $name = null): Route
    {
        $route = $this->routeFactory->buildRoute(Route::METHOD_GET, $path, $target, $name);

        $this->addRoute($route);

        return $route;
    }

    /**
     * Add route for PUT request
     * @param string $path
     * @param string|callable $target
     * @param string $name
     * @return Route
     */
    public function put(string $path, $target, string $name = null): Route
    {
        $route = $this->routeFactory->buildRoute(Route::METHOD_PUT, $path, $target, $name);

        $this->addRoute($route);

        return $route;
    }

    /**
     * Add route for PATCH request
     * @param string $path
     * @param string|callable $target
     * @param string $name
     * @return Route
     */
    public function patch(string $path, $target, string $name = null): Route
    {
        $route = $this->routeFactory->buildRoute(Route::METHOD_PATCH, $path, $target, $name);

        $this->addRoute($route);

        return $route;
    }

    /**
     * Add route for DELETE request
     * @param string $path
     * @param string|callable $target
     * @param string $name
     * @return Route
     */
    public function delete(string $path, $target, string $name = null): Route
    {
        $route = $this->routeFactory->buildRoute(Route::METHOD_DELETE, $path, $target, $name);

        $this->addRoute($route);

        return $route;
    }

    /**
     * Add route for any type of request
     * @param string $path
     * @param string|callable $target
     * @param string $name
     * @return Route
     */
    public function any(string $path, $target, string $name = null): Route
    {
        $route = $this->routeFactory->buildRoute(Route::METHOD_ANY, $path, $target, $name);

        $this->addRoute($route);

        return $route;
    }

    /**
     * @param RequestInterface $request
     * @return array|bool
     */
    public function process(RequestInterface $request)
    {
        return parent::match($request->getUri()->getPath(), $request->getMethod());
    }
}
