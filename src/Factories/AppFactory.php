<?php

namespace TheApp\Factories;

use Psr\Container\ContainerInterface;
use TheApp\Apps\WebApp;

/**
 * Class AppFactory
 * @package TheApp\Factories
 */
class AppFactory
{
    /**
     * Build App from container
     * @param ContainerInterface $container
     * @return WebApp
     */
    public static function fromContainer(ContainerInterface $container)
    {
        return $container->get(WebApp::class);
    }
}
