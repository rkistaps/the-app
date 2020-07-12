<?php

namespace TheApp\Interfaces;

use TheApp\Components\Router;

/**
 * Interface RouterConfiguratorInterface
 * @package TheApp\Interfaces
 */
interface RouterConfiguratorInterface
{
    /**
     * Map routes
     * @param Router $router
     * @return void
     */
    public function configureRouter(Router $router);
}
