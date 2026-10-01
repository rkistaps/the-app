<?php

declare(strict_types=1);

namespace TheApp\Structures;

use Psr\Http\Server\MiddlewareInterface;

/**
 * Routes registered together by Router::group(). Middleware added to the group runs for each of its
 * routes, around the route's own middleware, and after the middleware of the groups it's nested in.
 */
class RouteGroup
{
    /** @var array<MiddlewareInterface|callable|string> */
    private array $middlewares = [];

    /**
     * @internal Groups are created by Router::group()
     */
    public function __construct(private string $prefix, private ?RouteGroup $parent = null)
    {
    }

    /**
     * Add a middleware: a MiddlewareInterface instance, a class name or a callable.
     * It applies to the group's routes whether they were registered before or after this call.
     */
    public function addMiddleware(MiddlewareInterface|callable|string $middleware): static
    {
        $this->middlewares[] = $middleware;

        return $this;
    }

    /**
     * The full path prefix, including the prefixes of the groups this one is nested in
     */
    public function getPrefix(): string
    {
        return $this->prefix;
    }

    /**
     * @return array<MiddlewareInterface|callable|string> The middleware of the groups this one is nested in, then its own
     */
    public function getMiddlewares(): array
    {
        return [...($this->parent?->getMiddlewares() ?? []), ...$this->middlewares];
    }
}
