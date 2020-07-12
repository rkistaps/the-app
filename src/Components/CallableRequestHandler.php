<?php

namespace TheApp\Components;

use DI\Container;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class CallableRequestHandler implements RequestHandlerInterface
{
    /** @var callable */
    private $callable;
    private Container $container;

    public function __construct(callable $callable, Container $container)
    {
        $this->callable = $callable;
        $this->container = $container;
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $params = [$request];

        return $this->container->call($this->callable, $params);
    }
}