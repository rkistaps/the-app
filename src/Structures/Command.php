<?php

namespace TheApp\Structures;

class Command
{
    public string $name;
    /** @var string|callable */
    public $handler;
}
