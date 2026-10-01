<?php

declare(strict_types=1);

namespace TheApp\Factories;

use TheApp\Components\MiddlewareStack;
use TheApp\Interfaces\RouteHandlerInterface;

/**
 * @internal Internal to TheApp, not covered by the backwards-compatibility promise.
 */
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
