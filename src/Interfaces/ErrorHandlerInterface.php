<?php

declare(strict_types=1);

namespace TheApp\Interfaces;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

interface ErrorHandlerInterface
{
    /**
     * Turn an exception from WebApp::run() into a response. The request lets the response depend on it,
     * such as JSON for an API path or for an Accept header. Once a route has matched, the request has
     * the route parameters and the Router::class attribute.
     */
    public function handle(Throwable $throwable, ServerRequestInterface $request): ResponseInterface;
}
