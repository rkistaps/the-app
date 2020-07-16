<?php

namespace TheApp\Components;

class CallableCommandHandler
{
    /** @var callable */
    private $callable;

    public function __construct(
        callable $callable
    ) {
        $this->callable = $callable;
    }
}
