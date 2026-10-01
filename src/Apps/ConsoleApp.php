<?php

declare(strict_types=1);

namespace TheApp\Apps;

use DI\Container;
use TheApp\Components\CommandRunner;
use TheApp\Components\ConsoleInputParser;
use TheApp\Components\Output\StreamOutput;
use TheApp\Exceptions\InvalidCommandInputException;
use TheApp\Exceptions\InvalidConfigException;
use TheApp\Interfaces\CommandConfiguratorInterface;
use TheApp\Interfaces\ConsoleErrorHandlerInterface;
use TheApp\Interfaces\OutputInterface;
use Throwable;

/**
 * Runs the command named by the command-line arguments, and writes its own messages to the output's standard error
 */
class ConsoleApp extends App
{
    private CommandRunner $commandRunner;
    private ConsoleInputParser $inputParser;

    /** @var array<CommandConfiguratorInterface|string> */
    private array $commandConfigurators = [];
    private ConsoleErrorHandlerInterface|string|null $errorHandler = null;
    private OutputInterface|string|null $output = null;

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
     * Return a new app that writes to the given output, such as a BufferedOutput in tests. Without one, it uses
     * the container's OutputInterface if it has one, or a StreamOutput to standard output and standard error.
     * A class name is resolved from the container when the app runs.
     */
    public function withOutput(OutputInterface|string $output): static
    {
        $app = clone $this;
        $app->output = $output;

        return $app;
    }

    /**
     * Run the command named by the arguments, such as ['console.php', 'user/greet', '--name=World']
     *
     * @param string[] $argv Arguments as in PHP's $argv, where the first element is the script name
     * @return int Exit code: the command's own, 1 when the command isn't found or its input is invalid,
     *     or the error handler's when the command throws
     * @throws InvalidConfigException When the output given to withOutput() isn't an OutputInterface
     * @throws Throwable When no error handler is set
     */
    public function run(array $argv): int
    {
        $output = $this->getOutput();

        try {
            $commandRunner = $this->getCommandRunner();
            $input = $this->inputParser->parse($argv);
            $command = $input->command !== null ? $commandRunner->findCommandByName($input->command) : null;
            if (!$command) {
                $output->error('Command not found');
                return 1;
            }

            return $commandRunner->runCommand($command, $input->options);
        } catch (InvalidCommandInputException $exception) {
            // A mistake by the person running the command, so the message is all they need
            $output->error($exception->getMessage());
            return 1;
        } catch (Throwable $throwable) {
            return $this->handleErrors($throwable, $argv);
        }
    }

    /**
     * The output for this run. It's also set in the container as OutputInterface, so commands and the error
     * handler get the same one.
     *
     * @throws InvalidConfigException
     */
    private function getOutput(): OutputInterface
    {
        if ($this->output !== null) {
            $output = $this->resolve($this->output, OutputInterface::class);
        } elseif ($this->container->has(OutputInterface::class)) {
            $output = $this->resolve(OutputInterface::class, OutputInterface::class);
        } else {
            $output = $this->container->get(StreamOutput::class);
        }

        $this->container->set(OutputInterface::class, $output);

        return $output;
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
