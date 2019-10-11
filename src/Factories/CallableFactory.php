<?php

namespace TheApp\Factories;

use Psr\Container\ContainerInterface;

/**
 * Class CallableFactory
 * @package TheApp\Factories
 */
class CallableFactory
{
    /** @var ContainerInterface */
    private $container;

    /**
     * CallableFactory constructor.
     * @param ContainerInterface $container
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    /**
     * @param mixed $source
     * @return callable|null
     */
    public function getCallable($source)
    {
        if (is_callable($source)) {
            return $source;
        }

        if (is_string($source) && $this->container->has($source)) {
            $containerSource = $this->container->get($source);
            if (is_callable($containerSource)) {
                return $containerSource;
            }
        }

        return null;
    }
}
