<?php

namespace rkistaps\Routes;

use rkistaps\Handlers\Demo\DemoHandler;
use TheApp\Components\Router;
use TheApp\Components\WebRequest;
use TheApp\Interfaces\RouteConfiguratorInterface;
use TheApp\Responses\JsonResponse;

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

        $router->map('get', '/news/[a:slug]', function ($slug, WebRequest $request) {
            return 'News slug: ' . $slug . ' Test: ' . $request->get('test');
        });

        $router->map('get', '/demo', DemoHandler::class);

        $router->get('/my-route', function () {
            $class = new \stdClass();
            $class->test = 1;

            return new JsonResponse($class);
        });
    }
}