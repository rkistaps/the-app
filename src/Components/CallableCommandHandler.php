<?php

namespace TheApp\Components;

use DI\Container;
use TheApp\Interfaces\CommandHandlerInterface;

class CallableCommandHandler implements CommandHandlerInterface
{
    /** @var callable */
    private $callable;
    private Container $container;

    public function __construct(
        callable $callable,
        Container $container
    ) {
        $this->callable = $callable;
        $this->container = $container;
    }

    public function handle(array $params = [])
    {
        $this->container->call($this->callable, ['params' => $params]);
    }
}
