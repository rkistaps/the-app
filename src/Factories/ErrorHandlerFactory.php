<?php

namespace TheApp\Factories;

use Psr\Container\ContainerInterface;
use TheApp\Exceptions\InvalidConfigException;
use TheApp\Interfaces\ConfigInterface;
use TheApp\Interfaces\ErrorHandlerInterface;

class ErrorHandlerFactory
{
    private ContainerInterface $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    /**
     * @param ConfigInterface $config
     * @return ErrorHandlerInterface|null
     * @throws InvalidConfigException
     */
    public function buildFromConfig(ConfigInterface $config): ?ErrorHandlerInterface
    {
        $handlerClass = $config->get('error_handler');
        if (!$handlerClass) {
            return null;
        }

        $handler = $this->container->get($handlerClass);
        if (!is_a($handler, ErrorHandlerInterface::class)) {
            throw new InvalidConfigException(get_class($handler) . ' does not implement ' . ErrorHandlerInterface::class);
        }

        return $handler;
    }
}
