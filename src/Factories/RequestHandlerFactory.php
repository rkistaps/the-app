<?php

namespace TheApp\Factories;

use Psr\Container\ContainerInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TheApp\Exceptions\InvalidConfigException;

class RequestHandlerFactory
{
    private ContainerInterface $container;

    public function __construct(
        ContainerInterface $container
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
}