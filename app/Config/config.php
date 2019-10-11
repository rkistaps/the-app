<?php

use rkistaps\Routes\DefaultRoutes;

return [
    'templatePath' => APP_ROOT . '/app/Templates',
    'router' => [
        'basePath' => '',
        'routes' => [
            DefaultRoutes::class,
        ],
    ],
];