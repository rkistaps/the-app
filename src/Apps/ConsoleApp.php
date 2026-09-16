<?php

namespace TheApp\Apps;

use Psr\Container\ContainerInterface;
use TheApp\Components\CommandRunner;
use TheApp\Components\ConsoleInputParser;
use TheApp\Exceptions\InvalidCommandInputException;
use TheApp\Exceptions\InvalidConfigException;
use TheApp\Interfaces\CommandConfiguratorInterface;

class ConsoleApp extends App
{
    private CommandRunner $commandRunner;
    private ConsoleInputParser $inputParser;

    /** @var array<CommandConfiguratorInterface|string> */
    private array $commandConfigurators = [];

    /** Command runner with the configurators applied, built on first use */
    private ?CommandRunner $configuredCommandRunner = null;

    public function __construct(
        CommandRunner $commandRunner,
        ConsoleInputParser $inputParser,
        ContainerInterface $container
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
     * Run the command named by the arguments, such as ['console.php', 'user/greet', '--name=World']
     *
     * @param string[] $argv Arguments as in PHP's $argv, where the first element is the script name
     * @return int Exit code: 0 on success, 1 when the command isn't found or its input is invalid
     * @throws InvalidConfigException
     */
    public function run(array $argv): int
    {
        $commandRunner = $this->getCommandRunner();

        try {
            $input = $this->inputParser->parse($argv);
            $command = $input->command !== null ? $commandRunner->findCommandByName($input->command) : null;
            if (!$command) {
                echo 'Command not found' . PHP_EOL;
                return 1;
            }

            $commandRunner->runCommand($command, $input->options);
        } catch (InvalidCommandInputException $exception) {
            echo $exception->getMessage() . PHP_EOL;
            return 1;
        }

        return 0;
    }

    /**
     * @throws InvalidConfigException
     */
    protected function getCommandRunner(): CommandRunner
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
