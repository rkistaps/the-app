<?php

namespace TheApp\Components;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
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
     * @return ResponseInterface
     */
    public function processRequest(RequestInterface $request): ResponseInterface
    {
        $params = [];

        // strip base path from request url
        $requestUrl = substr($request->getUri()->getPath(), strlen($this->basePath));

        // Strip query string (?a=b) from Request Url
        if (($strpos = strpos($requestUrl, '?')) !== false) {
            $requestUrl = substr($requestUrl, 0, $strpos);
        }

        // set Request Method if it isn't passed as a parameter
        if ($requestMethod === null) {
            $requestMethod = isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : 'GET';
        }

        foreach ($this->routes as $handler) {
            [$method, $_route, $target, $name] = $handler;

            $methods = explode('|', $method);
            $method_match = false;

            // Check if request method matches. If not, abandon early. (CHEAP)
            foreach ($methods as $method) {
                if (strcasecmp($requestMethod, $method) === 0) {
                    $method_match = true;
                    break;
                }
            }

            // Method did not match, continue to next route.
            if (!$method_match) {
                continue;
            }

            // Check for a wildcard (matches all)
            if ($_route === '*') {
                $match = true;
            } elseif (isset($_route[0]) && $_route[0] === '@') {
                $pattern = '`' . substr($_route, 1) . '`u';
                $match = preg_match($pattern, $requestUrl, $params);
            } else {
                $route = null;
                $regex = false;
                $j = 0;
                $n = isset($_route[0]) ? $_route[0] : null;
                $i = 0;

                // Find the longest non-regex substring and match it against the URI
                while (true) {
                    if (!isset($_route[$i])) {
                        break;
                    } elseif (false === $regex) {
                        $c = $n;
                        $regex = $c === '[' || $c === '(' || $c === '.';
                        if (false === $regex && false !== isset($_route[$i + 1])) {
                            $n = $_route[$i + 1];
                            $regex = $n === '?' || $n === '+' || $n === '*' || $n === '{';
                        }
                        if (false === $regex && $c !== '/' && (!isset($requestUrl[$j]) || $c !== $requestUrl[$j])) {
                            continue 2;
                        }
                        $j++;
                    }
                    $route .= $_route[$i++];
                }

                $regex = $this->compileRoute($route);
                $match = preg_match($regex, $requestUrl, $params);
            }

            if (($match == true || $match > 0)) {
                if ($params) {
                    foreach ($params as $key => $value) {
                        if (is_numeric($key)) {
                            unset($params[$key]);
                        }
                    }
                }

                return [
                    'target' => $target,
                    'params' => $params,
                    'name' => $name,
                ];
            }
        }
        return false;
    }
}
