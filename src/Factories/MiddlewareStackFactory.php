<?php

declare(strict_types=1);

namespace TheApp\Factories;

use DI\Container;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TheApp\Components\CallableMiddleware;
use TheApp\Components\MiddlewareStack;
use TheApp\Exceptions\InvalidConfigException;
use TheApp\Interfaces\RouteHandlerInterface;

/**
 * @internal Internal to TheApp, not covered by the backwards-compatibility promise.
 */
class MiddlewareStackFactory
{
    public function __construct(private Container $container)
    {
    }

    public function buildFromRouteHandler(RouteHandlerInterface $routeHandler): MiddlewareStack
    {
        return new MiddlewareStack(
            $routeHandler->getHandler(),
            ...$routeHandler->getMiddlewares()
        );
    }

    /**
     * @param array<MiddlewareInterface|callable|string> $middlewares
     * @throws InvalidConfigException When a class name doesn't resolve to a MiddlewareInterface
     */
    public function build(RequestHandlerInterface $handler, array $middlewares): MiddlewareStack
    {
        return new MiddlewareStack($handler, ...array_map($this->resolve(...), array_values($middlewares)));
    }

    /**
     * Use an instance as is, wrap a callable, or get a class name from the container
     *
     * @throws InvalidConfigException When a class name doesn't resolve to a MiddlewareInterface
     */
    public function resolve(MiddlewareInterface|callable|string $middleware): MiddlewareInterface
    {
        if ($middleware instanceof MiddlewareInterface) {
            return $middleware;
        }

        if (is_callable($middleware)) {
            return new CallableMiddleware($middleware, $this->container);
        }

        $instance = $this->container->get($middleware);
        if (!$instance instanceof MiddlewareInterface) {
            throw new InvalidConfigException(get_debug_type($instance) . ' does not implement ' . MiddlewareInterface::class);
        }

        return $instance;
    }
}
