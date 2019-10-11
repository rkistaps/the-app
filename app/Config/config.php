<?php

use rkistaps\Handlers\ErrorHandler;
use rkistaps\Routes\DefaultRoutes;

return [
    'errorHandler' => ErrorHandler::class,
    'templatePath' => APP_ROOT . '/app/Templates',
    'router' => [
        'basePath' => '',
        'routes' => [
            DefaultRoutes::class,
        ],
    ],
];