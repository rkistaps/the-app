<?php

declare(strict_types=1);

namespace TheApp\Interfaces;

use Psr\Http\Message\ServerRequestInterface;

interface RouterInterface
{
    public function getRouteHandler(ServerRequestInterface $request): RouteHandlerInterface;
}
