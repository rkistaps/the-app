<?php

namespace rkistaps\Routes;

use AltoRouter;
use TheApp\Handlers\TestHandler;
use TheApp\Interfaces\RouteConfiguratorInterface;

/**
 * Class DefaultRoutes
 * @package rkistaps\Routes
 */
class DefaultRoutes implements RouteConfiguratorInterface
{
    /**
     * Map routes
     * @param AltoRouter $router
     * @return void
     * @throws \Exception
     */
    public function configureRoutes(AltoRouter $router)
    {
        $router->map('get', '/', function () {
            return 'Hello darkness my old friend';
        });

        $router->map('get', '/test/[i:id]', TestHandler::class);
    }
}