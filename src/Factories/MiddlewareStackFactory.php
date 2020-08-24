<?php

namespace TheApp\Factories;

use Psr\Container\ContainerInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TheApp\Components\MiddlewareStack;
use TheApp\Exceptions\InvalidConfigException;
use TheApp\Structures\Route;

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

    /**
     * @param Route $route
     * @return MiddlewareStack
     * @throws InvalidConfigException
     */
    public function buildFromRoute(Route $route): MiddlewareStack
    {
        $middlewares = array_map(function (string $classname) {
            $middleware = $this->container->get($classname);

            if (!is_a($middleware, MiddlewareInterface::class)) {
                throw new InvalidConfigException(get_class($middleware) . ' does not implement ' . MiddlewareInterface::class);
            }

            return $middleware;
        }, $route->middlewareClassnames);

        $handler = is_callable($route->handler)
            ? $this->requestHandlerFactory->getCallableRequestHandler($route->handler)
            : $this->container->get($route->handler);

        if (!is_a($handler, RequestHandlerInterface::class)) {
            throw new InvalidConfigException(get_class($handler) . ' does not implement ' . RequestHandlerInterface::class);
        }

        return new MiddlewareStack(
            $handler,
            ...$middlewares
        );
    }
}
