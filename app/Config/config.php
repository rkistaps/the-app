<?php

use rkistaps\Routes\DefaultRoutes;

return [
    'errorHandler' => function (Throwable $throwable) {
        return $throwable->getTraceAsString();
    },
    'router' => [
        'basePath' => '',
        'routes' => [
            DefaultRoutes::class,
        ],
    ],
];