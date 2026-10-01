<?php

declare(strict_types=1);

namespace TheApp\Apps;

use DI\Container;
use TheApp\Components\CommandRunner;
use TheApp\Components\ConsoleInputParser;
use TheApp\Exceptions\InvalidCommandInputException;
use TheApp\Exceptions\InvalidConfigException;
use TheApp\Interfaces\CommandConfiguratorInterface;
use TheApp\Interfaces\ConsoleErrorHandlerInterface;
use Throwable;

class ConsoleApp extends App
{
    private CommandRunner $commandRunner;
    private ConsoleInputParser $inputParser;

    /** @var array<CommandConfiguratorInterface|string> */
    private array $commandConfigurators = [];
    private ConsoleErrorHandlerInterface|string|null $errorHandler = null;

    /** Command runner with the configurators applied, built on first use */
    private ?CommandRunner $configuredCommandRunner = null;

    public function __construct(
        CommandRunner $commandRunner,
        ConsoleInputParser $inputParser,
        Container $container
    ) {
        parent::__construct($container);

        $this->commandRunner = $commandRunner;
        $this->inputParser = $inputParser;
    }

    public function __clone()
    {
        $this->configuredCommandRunner = null;
    }

    /**
     * Return a new app that also registers commands from the given configurators.
     * Class names are resolved from the container when the app runs.
     *
     * @param array<CommandConfiguratorInterface|string> $configurators
     */
    public function withCommandConfigurators(array $configurators): static
    {
        $app = clone $this;
        $app->commandConfigurators = [...$this->commandConfigurators, ...array_values($configurators)];

        return $app;
    }

    /**
     * Return a new app that reports exceptions from commands with the given handler, which also chooses the
     * exit code. Without one, exceptions are rethrown. A class name is resolved from the container on the first error.
     */
    public function withErrorHandler(ConsoleErrorHandlerInterface|string $errorHandler): static
    {
        $app = clone $this;
        $app->errorHandler = $errorHandler;

        return $app;
    }

    /**
     * Run the command named by the arguments, such as ['console.php', 'user/greet', '--name=World']
     *
     * @param string[] $argv Arguments as in PHP's $argv, where the first element is the script name
     * @return int Exit code: the command's own, 1 when the command isn't found or its input is invalid,
     *     or the error handler's when the command throws
     * @throws Throwable When no error handler is set
     */
    public function run(array $argv): int
    {
        try {
            $commandRunner = $this->getCommandRunner();
            $input = $this->inputParser->parse($argv);
            $command = $input->command !== null ? $commandRunner->findCommandByName($input->command) : null;
            if (!$command) {
                echo 'Command not found' . PHP_EOL;
                return 1;
            }

            return $commandRunner->runCommand($command, $input->options);
        } catch (InvalidCommandInputException $exception) {
            // A mistake by the person running the command, so the message is all they need
            echo $exception->getMessage() . PHP_EOL;
            return 1;
        } catch (Throwable $throwable) {
            return $this->handleErrors($throwable, $argv);
        }
    }

    /**
     * @param string[] $argv
     * @throws Throwable When no error handler is set
     */
    private function handleErrors(Throwable $throwable, array $argv): int
    {
        if ($this->errorHandler === null) {
            throw $throwable;
        }

        return $this->resolve($this->errorHandler, ConsoleErrorHandlerInterface::class)->handle($throwable, $argv);
    }

    /**
     * @throws InvalidConfigException
     */
    private function getCommandRunner(): CommandRunner
    {
        if ($this->configuredCommandRunner === null) {
            // Configure a copy, so apps returned by withCommandConfigurators() don't share commands
            $commandRunner = clone $this->commandRunner;
            foreach ($this->commandConfigurators as $configurator) {
                $this->resolve($configurator, CommandConfiguratorInterface::class)->configureCommands($commandRunner);
            }

            $this->configuredCommandRunner = $commandRunner;
        }

        return $this->configuredCommandRunner;
    }
}
