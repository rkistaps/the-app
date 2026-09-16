<?php

namespace TheApp\Components;

use TheApp\Exceptions\InvalidConfigException;
use TheApp\Factories\CommandHandlerFactory;
use TheApp\Structures\Command;

class CommandRunner
{
    private CommandHandlerFactory $commandHandlerFactory;

    /** @var Command[] */
    private array $commands = [];

    public function __construct(CommandHandlerFactory $commandHandlerFactory)
    {
        $this->commandHandlerFactory = $commandHandlerFactory;
    }

    /**
     * @param callable|string $handler A callable, or the class name of a CommandHandlerInterface implementation
     */
    public function addCommand(string $name, callable|string $handler): CommandRunner
    {
        $command = new Command();
        $command->name = $name;
        $command->handler = $handler;

        $this->commands[] = $command;

        return $this;
    }

    public function findCommandByName(string $name): ?Command
    {
        foreach ($this->commands as $command) {
            if ($command->name === $name) {
                return $command;
            }
        }

        return null;
    }

    /**
     * @param array<string, string|true> $params
     * @throws InvalidConfigException
     */
    public function runCommand(Command $command, array $params = []): void
    {
        $handler = $this->commandHandlerFactory->fromCommand($command);

        $handler->handle($params);
    }
}
