<?php

namespace TheApp\Apps;

use Psr\Container\ContainerInterface;
use samejack\PHP\ArgvParser;
use TheApp\Components\CommandRunner;
use TheApp\Exceptions\InvalidConfigException;
use TheApp\Interfaces\CommandConfiguratorInterface;

class ConsoleApp extends App
{
    private CommandRunner $commandRunner;
    private ArgvParser $argvParser;

    /** @var array<CommandConfiguratorInterface|string> */
    private array $commandConfigurators = [];

    /** Command runner with the configurators applied, built on first use */
    private ?CommandRunner $configuredCommandRunner = null;

    public function __construct(
        CommandRunner $commandRunner,
        ArgvParser $argvParser,
        ContainerInterface $container
    ) {
        parent::__construct($container);

        $this->commandRunner = $commandRunner;
        $this->argvParser = $argvParser;
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
     * @param array|string $argv
     * @throws InvalidConfigException
     */
    public function run($argv)
    {
        $commandRunner = $this->getCommandRunner();

        $params = $this->argvParser->parseConfigs($argv);
        $commandName = $params['command'] ?? null;
        // A missing or valueless --command argument is not a string
        $command = is_string($commandName) ? $commandRunner->findCommandByName($commandName) : null;
        if (!$command) {
            echo 'Command not found' . PHP_EOL;
            return;
        }

        unset($params['command']);

        $commandRunner->runCommand($command, $params);
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
