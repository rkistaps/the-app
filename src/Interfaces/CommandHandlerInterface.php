<?php

namespace TheApp\Interfaces;

interface CommandHandlerInterface
{
    /**
     * @param array<string, string|true> $params Command-line options by name. A flag without a value is true
     */
    public function handle(array $params = []): void;
}
