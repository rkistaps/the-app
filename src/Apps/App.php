<?php

declare(strict_types=1);

namespace TheApp\Apps;

use Psr\Container\ContainerInterface;

abstract class App
{
    private static ContainerInterface $staticContainer;

    public function __construct(ContainerInterface $container)
    {
        self::$staticContainer = $container;
    }

    public static function getContainer(): ContainerInterface
    {
        return self::$staticContainer;
    }
}
