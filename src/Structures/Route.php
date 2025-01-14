<?php

namespace TheApp\Structures;

class Route
{
    public const METHOD_GET = 'GET';
    public const METHOD_POST = 'POST';
    public const METHOD_ANY = 'ANY';

    public string $path;
    public ?string $name = null;
    public string $method = self::METHOD_ANY;

    /** @var string|callable */
    public $handler;

    /** @var string[] */
    public array $middlewares = [];

    /**
     * @param callable|string $middleware
     * @return $this
     */
    public function withMiddleware($middleware): Route
    {
        $this->middlewares[] = $middleware;

        return $this;
    }

    public function isAnyMethod(): bool
    {
        return $this->method === self::METHOD_ANY;
    }

    public function isForAnyPath(): bool
    {
        return $this->path === '*';
    }

    public function isCustomPath(): bool
    {
        return ($this->path[0] ?? null) === '@';
    }

    public function hasParameters(): bool
    {
        return strpos($this->path, '[') !== false;
    }
}
