<?php

namespace TheApp\Interfaces;

use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * @internal Internal to TheApp, not covered by the backwards-compatibility promise.
 */
interface RouteHandlerInterface
{
    public function getHandler(): RequestHandlerInterface;

    /**
     * @return MiddlewareInterface[]
     */
    public function getMiddlewares(): array;

    /**
     * Retrieve attributes derived from the request
     *
     * @return array
     */
    public function getAttributes(): array;
}
