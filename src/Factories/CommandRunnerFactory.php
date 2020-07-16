<?php

namespace TheApp\Factories;

use Psr\Container\ContainerInterface;
use TheApp\Components\CommandRunner;
use TheApp\Interfaces\CommandConfiguratorInterface;
use TheApp\Interfaces\ConfigInterface;

class CommandRunnerFactory
{
    private ContainerInterface $container;

    public function __construct(
        ContainerInterface $container
    ) {
        $this->container = $container;
    }

    public function fromConfig(ConfigInterface $config): CommandRunner
    {
        $runner = new CommandRunner();

        $routerConfigurators = $config->get('command.configurators', []);
        foreach ($routerConfigurators as $configuratorClassname) {
            $configurator = $this->container->get($configuratorClassname);

            if (is_a($configurator, CommandConfiguratorInterface::class)) {
                $configurator->configureCommands($runner);
            }
        }

        return $runner;
    }
}