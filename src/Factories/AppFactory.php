<?php

namespace TheApp\Factories;

use Psr\Container\ContainerInterface;
use TheApp\Apps\ConsoleApp;
use TheApp\Apps\WebApp;

/**
 * Class AppFactory
 * @package TheApp\Factories
 */
class AppFactory
{
    public static function webAppFromContainer(ContainerInterface $container): WebApp
    {
        return $container->get(WebApp::class);
    }

    public static function consoleAppFromContainer(ContainerInterface $container): ConsoleApp
    {
        return $container->get(ConsoleApp::class);
    }
}
