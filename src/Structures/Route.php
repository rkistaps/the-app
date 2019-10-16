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

    /** @var mixed[] */
    public $middlewares = [];

    /**
     * Add middleware
     * @param mixed $middleware
     * @return $this
     */
    public function with($middleware)
    {
        $this->middlewares[] = $middleware;

        return $this;
    }
}
