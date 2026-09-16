<?php

namespace TheApp\Interfaces;

use TheApp\Components\Router;

interface RouterConfiguratorInterface
{
    /**
     * Register routes on the router
     */
    public function configureRouter(Router $router): void;
}
