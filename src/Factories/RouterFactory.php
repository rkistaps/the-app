<?php

namespace TheApp\Factories;

use Psr\Container\ContainerInterface;
use TheApp\Components\Router;
use TheApp\Interfaces\ConfigInterface;
use TheApp\Interfaces\RouterConfiguratorInterface;

class RouterFactory
{
    private ContainerInterface $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function buildFromConfig(ConfigInterface $config): Router
    {
        $router = new Router(
            $this->container->get(RouteFactory::class)
        );

        $basePath = $config->get('router.basePath');
        if ($basePath) {
            $router->withBasePath($basePath);
        }

        $routerConfigurators = $config->get('router.configurators', []);
        foreach ($routerConfigurators as $configuratorClassname) {
            $configurator = $this->container->get($configuratorClassname);

            if (is_a($configurator, RouterConfiguratorInterface::class)) {
                $configurator->configureRouter($router);
            }
        }

        return $router;
    }
}