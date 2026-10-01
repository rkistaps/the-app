<?php

declare(strict_types=1);

namespace TheApp\Interfaces;

use Psr\Http\Message\ResponseInterface;

interface ErrorHandlerInterface
{
    public function handle(\Throwable $throwable): ResponseInterface;
}
