<?php

namespace TheApp\Interfaces;

use Psr\Http\Message\ServerRequestInterface;

interface RouterInterface
{
    public function getRouteHandler(ServerRequestInterface $request): RouteHandlerInterface;
}
