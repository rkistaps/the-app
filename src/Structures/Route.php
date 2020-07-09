<?php

namespace TheApp\Structures;

class Route
{
    public const METHOD_GET = 'GET';
    public const METHOD_POST = 'POST';
    public const METHOD_DELETE = 'DELETE';
    public const METHOD_PATCH = 'PATCH';
    public const METHOD_PUT = 'PUT';
    public const METHOD_ANY = 'GET|POST|PATCH|PUT|DELETE';

    public string $path;
    public string $method;
    public string $name;
    /** @var string|callable */
    public $target;

    public array $middlewares = [];

    public function withMiddleware($middleware) {
        $this->middlewares[] = $middleware;

        return $this;
    }
}
