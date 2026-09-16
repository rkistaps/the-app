<?php

namespace TheApp\Structures;

class Route
{
    public const METHOD_GET = 'GET';
    public const METHOD_HEAD = 'HEAD';
    public const METHOD_POST = 'POST';
    public const METHOD_PUT = 'PUT';
    public const METHOD_PATCH = 'PATCH';
    public const METHOD_DELETE = 'DELETE';
    public const METHOD_OPTIONS = 'OPTIONS';
    public const METHOD_ANY = 'ANY';

    public string $path;
    public ?string $name = null;

    /** @var string[] Upper-case HTTP methods. METHOD_ANY matches every method */
    public array $methods = [self::METHOD_ANY];

    /** @var string|callable */
    public $handler;

    /** @var array<callable|string> */
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
        return in_array(self::METHOD_ANY, $this->methods, true);
    }

    /**
     * Whether the route accepts the HTTP method. HEAD requests are accepted by GET routes.
     */
    public function allowsMethod(string $method): bool
    {
        $method = strtoupper($method);

        return $this->isAnyMethod()
            || in_array($method, $this->methods, true)
            || ($method === self::METHOD_HEAD && in_array(self::METHOD_GET, $this->methods, true));
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
