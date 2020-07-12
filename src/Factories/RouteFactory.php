<?php

namespace TheApp\Factories;

use TheApp\Structures\Route;

class RouteFactory
{
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