<?php

namespace TheApp\Structures;

class Route
{
    public const METHOD_GET = 'GET';
    public const METHOD_POST = 'POST';
    public const METHOD_ANY = 'ANY';

    public string $path;
    public string $method = self::METHOD_ANY;

    /** @var string|callable */
    public $handler;

    /** @var string[] */
    public array $middlewareClassnames = [];

    public function withMiddleware(string $middlewareClassname): Route
    {
        $this->middlewareClassnames[] = $middlewareClassname;

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
}
