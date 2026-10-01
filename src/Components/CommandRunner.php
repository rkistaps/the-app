<?php

declare(strict_types=1);

namespace TheApp\Components;

use TheApp\Exceptions\InvalidConfigException;
use TheApp\Factories\CommandHandlerFactory;
use TheApp\Structures\Command;

/**
 * Registers commands. Command configurators get one.
 */
class CommandRunner
{
    private CommandHandlerFactory $commandHandlerFactory;

    /** @var Command[] */
    private array $commands = [];

    /**
     * @internal Each console app gets one from the container
     */
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

    /**
     * @internal Used by ConsoleApp
     */
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
     * @internal Used by ConsoleApp
     * @param array<string, string|true> $params
     * @return int The command's exit code
     * @throws InvalidConfigException
     */
    public function runCommand(Command $command, array $params = []): int
    {
        $handler = $this->commandHandlerFactory->fromCommand($command);

        return $handler->handle($params);
    }
}
