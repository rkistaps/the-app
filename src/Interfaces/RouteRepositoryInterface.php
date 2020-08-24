<?php

namespace TheApp\Interfaces;


use Psr\Http\Message\ServerRequestInterface;
use TheApp\Structures\Route;

interface RouteRepositoryInterface
{
    public function findRouteByRequest(ServerRequestInterface $request): ?Route;

    public function addRoute(Route $route);
}
