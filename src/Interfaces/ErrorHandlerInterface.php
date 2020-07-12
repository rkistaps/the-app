<?php

namespace TheApp\Interfaces;

use Psr\Http\Message\ResponseInterface;

interface ErrorHandlerInterface
{
    public function handle(\Throwable $throwable): ResponseInterface;
}
