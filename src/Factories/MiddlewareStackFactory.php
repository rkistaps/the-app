<?php

namespace TheApp\Factories;

use Psr\Container\ContainerInterface;
use TheApp\Components\MiddlewareStack;
use TheApp\Interfaces\RouteHandlerInterface;

class MiddlewareStackFactory
{
    public function buildFromRouteHandler(RouteHandlerInterface $routeHandler): MiddlewareStack
    {
        return new MiddlewareStack(
            $routeHandler->getHandler(),
            ...$routeHandler->getMiddlewares()
        );
    }
}
