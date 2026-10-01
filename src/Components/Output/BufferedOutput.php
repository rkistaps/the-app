<?php

declare(strict_types=1);

namespace TheApp\Components\Output;

use TheApp\Interfaces\OutputInterface;

/**
 * Keeps what's written in memory, standard output and standard error apart. Useful in tests:
 * pass it to ConsoleApp::withOutput() and check what a command wrote.
 */
class BufferedOutput implements OutputInterface
{
    private string $output = '';
    private string $errors = '';

    public function write(string $text): void
    {
        $this->output .= $text;
    }

    public function writeln(string $line = ''): void
    {
        $this->output .= $line . PHP_EOL;
    }

    public function error(string $line): void
    {
        $this->errors .= $line . PHP_EOL;
    }

    /**
     * Everything written to standard output so far
     */
    public function getOutput(): string
    {
        return $this->output;
    }

    /**
     * Everything written to standard error so far
     */
    public function getErrors(): string
    {
        return $this->errors;
    }
}
