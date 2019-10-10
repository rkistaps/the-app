<?php

namespace TheApp\Factories;

use Psr\Container\ContainerInterface;
use TheApp\Components\Router;
use TheApp\Interfaces\ConfigInterface;
use TheApp\Interfaces\RouteConfiguratorInterface;

/**
 * Class RouterFactory
 * @package TheApp\Factories
 */
class RouterFactory
{
    /** @var ContainerInterface */
    private $container;

    /**
     * RouterFactory constructor.
     * @param ContainerInterface $container
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    /**
     * @param ConfigInterface $config
     * @return Router
     */
    public function fromConfig(ConfigInterface $config)
    {
        $router = new Router;

        $basePath = $config->get('router.basePath');
        if ($basePath) {
            $router->setBasePath($basePath);
        }

        collect($config->get('router.routes', []))
            ->each(function ($className) use ($router) {
                /** @var RouteConfiguratorInterface $configurator */
                $configurator = $this->container->get($className);

                if (is_a($configurator, RouteConfiguratorInterface::class)) {
                    $configurator->configureRoutes($router);
                }
            });

        return $router;
    }
}
