<?php

namespace TheApp\Factories;

use DI\Container;
use Psr\Http\Server\RequestHandlerInterface;
use TheApp\Components\CallableRequestHandler;
use TheApp\Exceptions\InvalidConfigException;

class RequestHandlerFactory
{
    private Container $container;

    public function __construct(
        Container $container
    ) {
        $this->container = $container;
    }

    /**
     * @param string $handlerClass
     * @return RequestHandlerInterface
     * @throws InvalidConfigException
     */
    public function getHandlerInstance(string $handlerClass): RequestHandlerInterface
    {
        $instance = $this->container->get($handlerClass);

        if (!is_a($instance, RequestHandlerInterface::class)) {
            throw new InvalidConfigException();
        }

        return $instance;
    }

    /**
     * @param callable $callable
     * @return RequestHandlerInterface
     */
    public function getCallableRequestHandler($callable): RequestHandlerInterface
    {
        return new CallableRequestHandler($callable, $this->container);
    }
}