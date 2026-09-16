<?php

namespace TheApp\Components;

use DI\Container;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * @internal Internal to TheApp, not covered by the backwards-compatibility promise.
 */
class CallableMiddleware implements \Psr\Http\Server\MiddlewareInterface
{
    /**
     * @var callable
     */
    private $callable;
    private Container $container;

    public function __construct(callable $callable, Container $container)
    {
        $this->callable = $callable;
        $this->container = $container;
    }

    /**
     * @inheritDoc
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        return $this->container->call($this->callable, [$request, $handler]);
    }
}