<?php


namespace TheApp\Components;


use Psr\Container\ContainerInterface;
use TheApp\Factories\CommandHandlerFactory;
use TheApp\Interfaces\CommandHandlerInterface;
use TheApp\Structures\Command;

class CommandRunner
{
    private ContainerInterface $container;
    private CommandHandlerFactory $commandHandlerFactory;

    public function __construct(
        ContainerInterface $container,
        CommandHandlerFactory $commandHandlerFactory
    ) {
        $this->container = $container;
        $this->commandHandlerFactory = $commandHandlerFactory;
    }

    /** @var Command[] */
    private array $commands = [];

    public function addCommand(string $name, $handler): CommandRunner
    {
        $command = new Command();
        $command->name = $name;
        $command->handler = $handler;

        $this->commands[] = $command;

        return $this;
    }

    public function findCommandByName(string $name): ?Command
    {
        return collect($this->commands)->first(function (Command $command) use ($name) {
            return $name === $command->name;
        });
    }

    public function runCommand(Command $command, array $params = [])
    {
        $handler = $this->commandHandlerFactory->fromCommand($command);

        $handler->handle($params);
    }
}
