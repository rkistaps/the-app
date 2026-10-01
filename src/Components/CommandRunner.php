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
     * @throws InvalidConfigException When a command with the same name is already registered
     */
    public function addCommand(string $name, callable|string $handler): CommandRunner
    {
        // The second command could never run, since the name finds the first
        if ($this->findCommandByName($name) !== null) {
            throw new InvalidConfigException(sprintf('A command named "%s" is already registered', $name));
        }

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
