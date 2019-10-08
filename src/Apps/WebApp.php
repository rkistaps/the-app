<?php

namespace TheApp\Apps;

use AltoRouter;
use Psr\Container\ContainerInterface;
use TheApp\Interfaces\ConfigInterface;
use TheApp\Interfaces\RouteConfiguratorInterface;

/**
 * Class WebApp
 * @package TheApp\Apps
 */
class WebApp
{
    /** @var ContainerInterface */
    private $container;

    /** @var AltoRouter */
    private $router;

    /** @var ConfigInterface */
    private $config;

    /**
     * WebApp constructor.
     * @param AltoRouter $router
     * @param ContainerInterface $container
     * @param ConfigInterface $config
     */
    public function __construct(
        AltoRouter $router,
        ContainerInterface $container,
        ConfigInterface $config
    ) {
        $this->container = $container;
        $this->router = $router;
        $this->config = $config;
    }

    /**
     * Run application
     * @throws \Exception
     */
    public function run()
    {
        $routes = $this->config->get('routes') ?? [];
        foreach ($routes as $routeClass) {
            /** @var RouteConfiguratorInterface $route */
            $route = $this->container->get($routeClass);
            if (is_a($route, RouteConfiguratorInterface::class)) {
                $route->configureRoutes($this->router);
            }
        }

        $match = $this->router->match();

        if (!is_array($match)) {
            header("HTTP/1.0 404 Not Found");
            die;
        }

        $target = $match['target'] ?? [];
        $params = $match['params'] ?? [];

        $handler = null;
        if (is_string($target) && $this->container->has($target)) {
            $handler = $this->container->get($target);
        } elseif (is_callable($target)) {
            $handler = $target;
        }

        if ($handler) {
            $response = $this->container->call($handler, $params);
            if ($response) {
                echo $response;
            }
        }
    }
}
