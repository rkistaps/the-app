<?php

use Psr\Container\ContainerInterface;
use TheApp\Components\WebRequest;
use TheApp\Factories\ConfigFactory;
use TheApp\Factories\RequestFactory;
use TheApp\Factories\RouterFactory;
use TheApp\Interfaces\ConfigInterface;

return [
    ConfigInterface::class => function () {
        return ConfigFactory::fromArray(require APP_ROOT . '/app/Config/config.php');
    },
    WebRequest::class => function (RequestFactory $requestFactory) {
        return $requestFactory->fromGlobals();
    },
    AltoRouter::class => function (RouterFactory $routerFactory, ConfigInterface $config) {
        return $routerFactory->fromConfig($config);
    },
];