<?php

namespace TheApp\Components;

use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TheApp\Interfaces\RouteHandlerInterface;

/**
 * @internal Internal to TheApp, not covered by the backwards-compatibility promise.
 */
class RouteHandler implements RouteHandlerInterface
{
    private RequestHandlerInterface $handler;

    /** @var MiddlewareInterface[] */
    private array $middlewares = [];

    /**
     * Attributes derived from the request
     * @var array
     */
    private array $attributes = [];

    public function __construct(RequestHandlerInterface $handler)
    {
        $this->handler = $handler;
    }

    public function getHandler(): RequestHandlerInterface
    {
        return $this->handler;
    }

    public function getMiddlewares(): array
    {
        return $this->middlewares;
    }

    public function addMiddlewares(MiddlewareInterface ...$middlewares): void
    {
        $this->middlewares = $middlewares;
    }

    public function addAttribute(string $name, mixed $value): void
    {
        $this->attributes[$name] = $value;
    }

    public function getAttributes(): array
    {
        return $this->attributes;
    }
}
