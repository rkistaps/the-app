<?php

namespace TheApp\Factories;

use DI\Container;
use Psr\Http\Server\RequestHandlerInterface;
use TheApp\Components\CallableRequestHandler;
use TheApp\Exceptions\InvalidConfigException;
use TheApp\Structures\Route;

class RequestHandlerFactory
{
    private Container $container;

    public function __construct(
        Container $container
    ) {
        $this->container = $container;
    }

    /**
     * @throws InvalidConfigException When the class doesn't implement RequestHandlerInterface
     */
    public function getHandlerInstance(string $handlerClass): RequestHandlerInterface
    {
        $instance = $this->container->get($handlerClass);

        if (!$instance instanceof RequestHandlerInterface) {
            throw new InvalidConfigException(get_debug_type($instance) . ' does not implement ' . RequestHandlerInterface::class);
        }

        return $instance;
    }

    /**
     * @throws InvalidConfigException When the route's handler class doesn't implement RequestHandlerInterface
     */
    public function fromRoute(Route $route): RequestHandlerInterface
    {
        return is_callable($route->handler)
            ? $this->getCallableRequestHandler($route->handler)
            : $this->getHandlerInstance($route->handler);
    }

    public function getCallableRequestHandler(callable $callable): RequestHandlerInterface
    {
        return new CallableRequestHandler($callable, $this->container);
    }
}
