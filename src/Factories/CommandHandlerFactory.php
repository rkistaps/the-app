<?php

namespace TheApp\Factories;

use DI\Container;
use Psr\Container\ContainerInterface;
use TheApp\Components\CallableCommandHandler;
use TheApp\Exceptions\InvalidConfigException;
use TheApp\Interfaces\CommandHandlerInterface;
use TheApp\Structures\Command;

class CommandHandlerFactory
{
    private ContainerInterface $container;

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    public function fromCommand(Command $command): CommandHandlerInterface
    {
        $handler = is_callable($command->handler)
            ? new CallableCommandHandler($command->handler, $this->container)
            : $this->container->get($command->handler);

        if (!is_a($handler, CommandHandlerInterface::class)) {
            throw new InvalidConfigException(get_class($handler) . ' does not implement ' . CommandHandlerInterface::class);
        }

        return $handler;
    }
}