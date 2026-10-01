<?php

declare(strict_types=1);

namespace TheApp\Interfaces;

/**
 * Console output. Commands get it from the container: as a constructor parameter of a command class,
 * or as a parameter of a callable command.
 */
interface OutputInterface
{
    /**
     * Write text to standard output as it is
     */
    public function write(string $text): void;

    /**
     * Write a line to standard output
     */
    public function writeln(string $line = ''): void;

    /**
     * Write a line to standard error, for errors and diagnostics, so they don't mix with the command's
     * output when it's piped or redirected to a file
     */
    public function error(string $line): void;
}
