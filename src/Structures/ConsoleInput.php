<?php

namespace TheApp\Structures;

/**
 * Parsed command-line input
 *
 * @internal Internal to TheApp, not covered by the backwards-compatibility promise.
 */
class ConsoleInput
{
    /** Command name, from the first positional argument or the --command option */
    public ?string $command = null;

    /** @var array<string, string|true> Options by name. A flag without a value, such as --verbose, is true */
    public array $options = [];
}
