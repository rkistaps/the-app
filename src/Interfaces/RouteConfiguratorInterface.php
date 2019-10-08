<?php

namespace TheApp\Interfaces;

use AltoRouter;

/**
 * Interface RouteConfiguratorInterface
 * @package TheApp\Interfaces
 */
interface RouteConfiguratorInterface
{
    /**
     * Map routes
     * @param AltoRouter $router
     * @return void
     */
    public function configureRoutes(AltoRouter $router);
}
