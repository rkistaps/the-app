<?php

namespace rkistaps\Routes;

use TheApp\Components\Router;
use TheApp\Interfaces\RouteConfiguratorInterface;

/**
 * Class DefaultRoutes
 * @package rkistaps\Routes
 */
class DefaultRoutes implements RouteConfiguratorInterface
{
    /**
     * Map routes
     * @param Router $router
     * @return void
     * @throws \Exception
     */
    public function configureRoutes(Router $router)
    {
        $router->map('get', '/', function () {
            return 'Hello darkness my old friend';
        });
    }
}
