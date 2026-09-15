<?php

declare(strict_types=1);

namespace TheApp\Apps;

use Psr\Container\ContainerInterface;
use TheApp\Exceptions\InvalidConfigException;

abstract class App
{
    private static ContainerInterface $staticContainer;

    public function __construct(protected ContainerInterface $container)
    {
        self::$staticContainer = $container;
    }

    public static function getContainer(): ContainerInterface
    {
        return self::$staticContainer;
    }

    /**
     * Resolve a class name from the container, or use an instance as is, and check its type
     *
     * @template T of object
     * @param object|string $service
     * @param class-string<T> $interface
     * @return T
     * @throws InvalidConfigException
     */
    protected function resolve(object|string $service, string $interface): object
    {
        $instance = is_string($service) ? $this->container->get($service) : $service;

        if (!$instance instanceof $interface) {
            throw new InvalidConfigException(get_debug_type($instance) . ' does not implement ' . $interface);
        }

        return $instance;
    }
}
