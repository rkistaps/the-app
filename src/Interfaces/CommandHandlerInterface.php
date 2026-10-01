<?php

declare(strict_types=1);

namespace TheApp\Interfaces;

interface CommandHandlerInterface
{
    /**
     * @param array<string, string|true> $params Command-line options by name. A flag without a value is true
     * @return int Exit code: 0 on success, anything else on failure
     */
    public function handle(array $params = []): int;
}
