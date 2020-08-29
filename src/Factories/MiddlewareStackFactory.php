<?php

namespace TheApp\Factories;

use Psr\Container\ContainerInterface;
use TheApp\Components\MiddlewareStack;
use TheApp\Interfaces\RouteHandlerInterface;

class MiddlewareStackFactory
{
    private ContainerInterface $container;
    private RequestHandlerFactory $requestHandlerFactory;

    public function __construct(
        ContainerInterface $container,
        RequestHandlerFactory $requestHandlerFactory
    ) {
        $this->container = $container;
        $this->requestHandlerFactory = $requestHandlerFactory;
    }

    public function buildFromRouteHandler(RouteHandlerInterface $routeHandler): MiddlewareStack
    {
        return new MiddlewareStack(
            $routeHandler->getHandler(),
            ...$routeHandler->getMiddlewares()
        );
    }
}
