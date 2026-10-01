<?php

declare(strict_types=1);

namespace TheApp\Factories;

use DI\Container;
use DI\ContainerBuilder;
use TheApp\Apps\ConsoleApp;
use TheApp\Apps\WebApp;

/**
 * Entry points for creating apps.
 *
 * Without a container, each method builds a default PHP-DI container, so a small project needs no container setup.
 * Pass your own container when handlers need definitions, such as a PSR-17 response factory.
 */
class AppFactory
{
    public static function web(?Container $container = null): WebApp
    {
        return self::container($container)->get(WebApp::class);
    }

    public static function console(?Container $container = null): ConsoleApp
    {
        return self::container($container)->get(ConsoleApp::class);
    }

    private static function container(?Container $container): Container
    {
        return $container ?? (new ContainerBuilder())->build();
    }
}
