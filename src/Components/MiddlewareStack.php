<?php

namespace TheApp\Components;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

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
        $middleware = array_shift($this->middlewares);

        return $middleware
            ? $middleware->process(
                $request,
                new MiddlewareStack(
                    $this->requestHandler,
                    ...$this->middlewares
                ))
            : $this->requestHandler->handle($request);
    }
}
