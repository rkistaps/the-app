<?php

declare(strict_types=1);

namespace TheApp\Interfaces;

use Throwable;

interface ConsoleErrorHandlerInterface
{
    /**
     * Report an exception from ConsoleApp::run(), such as by printing or logging it, and choose the exit code
     *
     * @param string[] $argv The arguments run() was given, as in PHP's $argv
     * @return int Exit code. Use a non-zero code, since the command failed
     */
    public function handle(Throwable $throwable, array $argv): int;
}
