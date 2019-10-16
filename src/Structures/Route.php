<?php

namespace TheApp\Structures;

use Psr\Http\Server\MiddlewareInterface;

/**
 * Class Route
 * @package TheApp\Structures
 */
class Route
{
    /**
     * Array of HTTP methods - ['GET', 'POST', ...]
     * @var string[]
     */
    public $methods = [];

    /** @var string */
    public $path;

    /** @var mixed */
    public $target;

    /** @var string|null */
    public $name;

    /** @var MiddlewareInterface[] */
    public $middlewares = [];

    /**
     * Add middleware
     * @param MiddlewareInterface $middleware
     * @return $this
     */
    public function with(MiddlewareInterface $middleware)
    {
        $this->middlewares[] = $middleware;

        return $this;
    }
}
