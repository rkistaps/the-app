<?php

namespace TheApp\Interfaces;

use TheApp\Components\Router;

/**
 * Interface RouteConfiguratorInterface
 * @package TheApp\Interfaces
 */
interface RouteConfiguratorInterface
{
    /**
     * Map routes
     * @param Router $router
     * @return void
     */
    public function configureRoutes(Router $router);
}
