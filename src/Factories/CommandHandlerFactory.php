<?php

namespace TheApp\Factories;

use DI\Container;
use TheApp\Components\CallableCommandHandler;
use TheApp\Exceptions\InvalidConfigException;
use TheApp\Interfaces\CommandHandlerInterface;
use TheApp\Structures\Command;

/**
 * @internal Internal to TheApp, not covered by the backwards-compatibility promise.
 */
class CommandHandlerFactory
{
    private Container $container;

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    public function fromCommand(Command $command): CommandHandlerInterface
    {
        $handler = is_callable($command->handler)
            ? new CallableCommandHandler($command->handler, $this->container)
            : $this->container->get($command->handler);

        if (!$handler instanceof CommandHandlerInterface) {
            throw new InvalidConfigException(get_debug_type($handler) . ' does not implement ' . CommandHandlerInterface::class);
        }

        return $handler;
    }
}