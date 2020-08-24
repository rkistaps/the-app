<?php

namespace TheApp\Components\Repositories;

use Psr\Http\Message\ServerRequestInterface;
use TheApp\Interfaces\RouteRepositoryInterface;
use TheApp\Structures\Route;

class RouteRepository implements RouteRepositoryInterface
{
    /** @var Route[] */
    private array $routes = [];

    protected array $matchTypes = [
        '[i]' => '[0-9]+',
        '[s]' => '[a-zA-Z\-]+',
        '[*]' => '[a-zA-Z0-9\-]+',
    ];

    public function addRoute(Route $route)
    {
        $this->routes[] = $route;

        return $this;
    }

    public function findRouteByRequest(ServerRequestInterface $request): ?Route
    {
        /** @var Route[] $routes */
        $routes = array_filter($this->routes, fn(Route $route) => $route->isAnyMethod() || $request->getMethod() === $route->method);

        foreach ($routes as $route) {
            if ($route->isForAnyPath()) {
                return $route;
            }

            $regex = $this->buildRegexForRoute($route);
            preg_match('/' . $regex . '/', $request->getUri()->getPath(), $match);
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
