<?php

declare(strict_types=1);

namespace TheApp\Structures;

/**
 * @internal Internal to TheApp, not covered by the backwards-compatibility promise.
 */
class Command
{
    public string $name;
    /** @var string|callable */
    public $handler;
}
