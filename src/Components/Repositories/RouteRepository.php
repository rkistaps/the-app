<?php

namespace TheApp\Components\Repositories;

use Psr\Http\Message\ServerRequestInterface;
use TheApp\Structures\Route;
use TheApp\Structures\RouteMatchResult;

class RouteRepository
{
    /** @var Route[] */
    private array $routes = [];

    protected array $matchTypes = [
        'i' => '[0-9]++',
        'a' => '[0-9A-Za-z]++',
        'h' => '[0-9A-Fa-f]++',
        '*' => '.+?',
        '**' => '.++',
        '' => '[^/\.]++',
    ];

    public function addRoute(Route $route)
    {
        $this->routes[] = $route;

        return $this;
    }

    /**
     * Find the first route that matches the request's path and method
     */
    public function matchRoute(ServerRequestInterface $request): ?RouteMatchResult
    {
        $requestPath = $request->getUri()->getPath();

        foreach ($this->routes as $route) {
            if (!$route->allowsMethod($request->getMethod())) {
                continue;
            }

            $parameters = $this->matchPath($route, $requestPath);
            if ($parameters !== null) {
                return new RouteMatchResult($route, $parameters);
            }
        }

        return null;
    }

    /**
     * Methods accepted by routes whose path matches the request, regardless of the request's method.
     * GET routes also accept HEAD. Empty when no route path matches.
     *
     * @return string[]
     */
    public function findAllowedMethods(ServerRequestInterface $request): array
    {
        $requestPath = $request->getUri()->getPath();
        $methods = [];

        foreach ($this->routes as $route) {
            if ($this->matchPath($route, $requestPath) === null) {
                continue;
            }

            $methods = [...$methods, ...$route->methods];
            if (in_array(Route::METHOD_GET, $route->methods, true)) {
                $methods[] = Route::METHOD_HEAD;
            }
        }

        return array_values(array_unique($methods));
    }

    /**
     * Match a route's path against the request path
     *
     * @return array<string, string>|null Named parameters when the path matches, null otherwise
     */
    protected function matchPath(Route $route, string $requestPath): ?array
    {
        $parameters = [];

        if ($route->isForAnyPath()) {
            return [];
        }

        if ($route->isCustomPath()) {
            // remove "@" regex delimiter
            $pattern = '`' . substr($route->path, 1) . '`u';
            $isMatch = preg_match($pattern, $requestPath, $parameters) === 1;
        } elseif (($position = strpos($route->path, '[')) === false) {
            // No params in url, do string comparison
            return strcmp($requestPath, $route->path) === 0 ? [] : null;
        } else {
            // Compare longest non-param string with url before moving on to regex
            // Check if last character before param is a slash, because it could be optional if param is optional too (see https://github.com/dannyvankooten/AltoRouter/issues/241)
            $lastRequestUrlChar = $requestPath !== '' ? $requestPath[strlen($requestPath) - 1] : '';
            if (strncmp($requestPath, $route->path, $position) !== 0 && ($lastRequestUrlChar === '/' || $route->path[$position - 1] !== '/')) {
                return null;
            }

            $regex = $this->compileRoute($route->path);
            $isMatch = preg_match($regex, $requestPath, $parameters) === 1;
        }

        if (!$isMatch) {
            return null;
        }

        return array_filter($parameters, fn($key) => !is_numeric($key), ARRAY_FILTER_USE_KEY);
    }

    /**
     * Compile the regex for a given route (EXPENSIVE)
     * @param string $routePath
     * @return string
     */
    protected function compileRoute(string $routePath): string
    {
        if (preg_match_all('`(/|\.|)\[([^:\]]*+)(?::([^:\]]*+))?\](\?|)`', $routePath, $matches, PREG_SET_ORDER)) {
            $matchTypes = $this->matchTypes;
            foreach ($matches as $match) {
                [$block, $pre, $type, $param, $optional] = $match;

                if (isset($matchTypes[$type])) {
                    $type = $matchTypes[$type];
                }
                if ($pre === '.') {
                    $pre = '\.';
                }

                $optional = $optional !== '' ? '?' : null;

                //Older versions of PCRE require the 'P' in (?P<named>)
                $pattern = '(?:'
                    . ($pre !== '' ? $pre : null)
                    . '('
                    . ($param !== '' ? "?P<$param>" : null)
                    . $type
                    . ')'
                    . $optional
                    . ')'
                    . $optional;

                $routePath = str_replace($block, $pattern, $routePath);
            }
        }
        return "`^$routePath$`u";
    }
}
