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

    public function matchRoute(ServerRequestInterface $request): ?RouteMatchResult
    {
        $parameters = [];

        $requestPath = $request->getUri()->getPath();
        $lastRequestUrlChar = $request->getUri()->getPath() ? $requestPath[strlen($requestPath) - 1] : '';

        $routes = array_filter($this->routes, fn(Route $route) => $route->isAnyMethod() || $request->getMethod() === $route->method);
        foreach ($routes as $route) {
            if ($route->isForAnyPath()) {
                $isMatch = true;
            } elseif ($route->isCustomPath()) {
                // remove "@" regex delimiter
                $pattern = '`' . substr($route, 1) . '`u';
                $isMatch = preg_match($pattern, $requestPath, $parameters) === 1;
            } elseif (!$route->hasParameters()) {
                // No params in url, do string comparison
                $isMatch = strcmp($requestPath, $route) === 0;
            } else {
                $position = strpos($route->path, '[');
                // Compare longest non-param string with url before moving on to regex
                // Check if last character before param is a slash, because it could be optional if param is optional too (see https://github.com/dannyvankooten/AltoRouter/issues/241)
                if (strncmp($requestPath, $route->path, $position) !== 0 && ($lastRequestUrlChar === '/' || $route->path[$position - 1] !== '/')) {
                    continue;
                }

                $regex = $this->compileRoute($route->path);
                $isMatch = preg_match($regex, $requestPath, $parameters) === 1;
            }

            if ($isMatch) {
                if ($parameters) {
                    foreach ($parameters as $key => $value) {
                        if (is_numeric($key)) {
                            unset($parameters[$key]);
                        }
                    }
                }

                return new RouteMatchResult($route, $parameters);
            }
        }

        return null;
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
