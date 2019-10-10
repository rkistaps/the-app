<?php

namespace rkistaps\Routes;

use AltoRouter;
use rkistaps\Handlers\Demo\DemoHandler;
use TheApp\Components\WebRequest;
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

        $router->map('get', '/news/[a:slug]', function ($slug, WebRequest $request) {
            return 'News slug: ' . $slug . ' Test: ' . $request->get('test');
        });

        $router->map('get', '/demo', DemoHandler::class);
    }
}