<?php

declare(strict_types=1);

namespace TheApp\Structures;

/**
 * A registered route. The router's get(), post() and other methods return it, so middleware can be added to it.
 */
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

    /** @var string[] Upper-case HTTP methods. METHOD_ANY matches every method */
    private array $methods;

    /** @var string|callable */
    private $handler;

    /** @var array<callable|string> */
    private array $middlewares = [];

    /**
     * @internal Routes are created by the Router
     * @param string[] $methods HTTP methods, such as ['GET']. METHOD_ANY matches every method
     */
    public function __construct(
        array $methods,
        private string $path,
        callable|string $handler,
        private ?string $name = null
    ) {
        $this->methods = array_values(array_unique(array_map('strtoupper', $methods)));
        $this->handler = $handler;
    }

    /**
     * Add a middleware, a class name or a callable. Middleware runs in the order it was added.
     */
    public function addMiddleware(callable|string $middleware): static
    {
        $this->middlewares[] = $middleware;

        return $this;
    }

    /**
     * @return string[] Upper-case HTTP methods. METHOD_ANY matches every method
     */
    public function getMethods(): array
    {
        return $this->methods;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getHandler(): callable|string
    {
        return $this->handler;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    /**
     * @return array<callable|string>
     */
    public function getMiddlewares(): array
    {
        return $this->middlewares;
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
        return str_contains($this->path, '[');
    }
}
