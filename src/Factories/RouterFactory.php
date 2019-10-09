<?php

namespace TheApp\Factories;

use AltoRouter;
use Psr\Container\ContainerInterface;
use TheApp\Interfaces\ConfigInterface;
use TheApp\Interfaces\RouteConfiguratorInterface;

/**
 * Class RouterFactory
 * @package TheApp\Factories
 */
class RouterFactory
{
    /**
     * @param ContainerInterface $container
     * @param ConfigInterface $config
     * @return AltoRouter
     */
    public function fromContainer(ContainerInterface $container, ConfigInterface $config)
    {
        $router = new AltoRouter;

        $basePath = $config->get('router.basePath');
        if ($basePath) {
            $router->setBasePath($basePath);
        }

        foreach ($config->get('router.routes', []) as $routeClass) {
            /** @var RouteConfiguratorInterface $route */
            $route = $container->get($routeClass);
            if (is_a($route, RouteConfiguratorInterface::class)) {
                $route->configureRoutes($router);
            }
        }

        return $router;
    }
}
