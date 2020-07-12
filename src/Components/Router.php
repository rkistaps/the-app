<?php

namespace TheApp\Components;

use Psr\Http\Message\ServerRequestInterface;
use TheApp\Factories\RouteFactory;
use TheApp\Structures\Route;

/**
 * Class Router
 * @package TheApp\Components
 */
class Router
{
    private RouteFactory $routeFactory;
    /** @var Route[] */
    private array $routes = [];
    private string $basePath = '';
    protected array $matchTypes = [
        '[i]' => '[0-9]+',
        '[s]' => '[a-zA-Z\-]+',
        '[*]' => '[a-zA-Z0-9\-]+',
    ];

    public function __construct(
        RouteFactory $routeFactory
    ) {
        $this->routeFactory = $routeFactory;
    }

    public function withBasePath(string $basePath): Router
    {
        $router = clone $this;
        $router->basePath = $basePath;

        return $router;
    }

    public function generateRoutePath(string $routeName, array $params = []): string
    {
        $route = $this->findRouteByName($routeName);
        if (!$route) {
            throw new \Exception("Route '" . $routeName . "' does not exist.");
        }

        // prepend base path to route url again
        $url = $this->basePath . $route->path;

        if (preg_match_all('`(/|\.|)\[([^:\]]*+)(?::([^:\]]*+))?\](\?|)`', $route, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                [$block, $pre, $type, $param, $optional] = $match;

                if ($pre) {
                    $block = substr($block, 1);
                }

                if (isset($params[$param])) {
                    $url = str_replace($block, $params[$param], $url);
                } elseif ($optional) {
                    $url = str_replace($pre . $block, '', $url);
                }
            }
        }

        return $url;
    }

    public function findRouteByName(string $name): ?Route
    {
        return collect($this->routes)->first(
            function (Route $route) use ($name) {
                return $route->name === $name;
            }
        );
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
        $route = $this->routeFactory->buildRoute(Route::METHOD_GET, $path, $handler, $name);

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
     * @param string|callable $handler
     * @param string $name
     * @return Route
     */
    public function post(string $path, $handler, string $name = null): Route
    {
        $route = $this->routeFactory->buildRoute(Route::METHOD_POST, $path, $handler, $name);

        $this->addRoute($route);

        return $route;
    }

    /**
     * Add route for PUT request
     * @param string $path
     * @param string|callable $handler
     * @param string $name
     * @return Route
     */
    public function put(string $path, $handler, string $name = null): Route
    {
        $route = $this->routeFactory->buildRoute(Route::METHOD_PUT, $path, $handler, $name);

        $this->addRoute($route);

        return $route;
    }

    /**
     * Add route for PATCH request
     * @param string $path
     * @param string|callable $handler
     * @param string $name
     * @return Route
     */
    public function patch(string $path, $handler, string $name = null): Route
    {
        $route = $this->routeFactory->buildRoute(Route::METHOD_PATCH, $path, $handler, $name);

        $this->addRoute($route);

        return $route;
    }

    /**
     * Add route for DELETE request
     * @param string $path
     * @param string|callable $handler
     * @param string $name
     * @return Route
     */
    public function delete(string $path, $handler, string $name = null): Route
    {
        $route = $this->routeFactory->buildRoute(Route::METHOD_DELETE, $path, $handler, $name);

        $this->addRoute($route);

        return $route;
    }

    /**
     * Add route for any type of request
     * @param string $path
     * @param string|callable $handler
     * @param string $name
     * @return Route
     */
    public function any(string $path, $handler, string $name = null): Route
    {
        $route = $this->routeFactory->buildRoute(Route::METHOD_ANY, $path, $handler, $name);

        $this->addRoute($route);

        return $route;
    }

    public function findRouteForRequest(ServerRequestInterface $request): ?Route
    {
        $requestUrl = substr($request->getUri()->getPath(), strlen($this->basePath));

        /** @var Route[] $routes */
        $routes = collect($this->routes)
            ->filter(function (Route $route) use ($request) {
                return $route->isAnyMethod() || $request->getMethod() === $route->method;
            })
            ->all();

        foreach ($routes as $route) {
            if ($route->isForAnyPath()) {
                return $route;
            }

            $regex = $this->buildRegexForRoute($route);
            preg_match('/' . $regex . '/', $requestUrl, $match);
            if ($match) {
                return $route;
            }
        }

        return null;
    }

    protected function buildRegexForRoute(Route $route): string
    {
        $regex = $route->path;
        foreach ($this->matchTypes as $search => $replace) {
            $regex = str_replace($search, $replace, $regex);
        }

        return '^' . str_replace('/', '\/', $regex) . '$';
    }
}
