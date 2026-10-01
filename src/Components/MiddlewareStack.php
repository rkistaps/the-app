<?php

namespace TheApp\Components;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * @internal Internal to TheApp, not covered by the backwards-compatibility promise.
 */
class MiddlewareStack implements RequestHandlerInterface
{
    /** @var MiddlewareInterface[] */
    private array $middlewares;
    private RequestHandlerInterface $requestHandler;

    public function __construct(
        RequestHandlerInterface $requestHandler,
        MiddlewareInterface ...$middlewares
    ) {
        $this->middlewares = $middlewares;
        $this->requestHandler = $requestHandler;
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $middleware = $this->middlewares[0] ?? null;
        if ($middleware === null) {
            return $this->requestHandler->handle($request);
        }

        // The stack itself never changes, so a middleware can call the next handler more than once,
        // as a retry does, and every middleware after it still runs each time
        return $middleware->process(
            $request,
            new MiddlewareStack($this->requestHandler, ...array_slice($this->middlewares, 1))
        );
    }
}
