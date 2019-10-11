<?php

use League\Plates\Engine;
use rkistaps\Factories\TemplateEngineFactory;
use TheApp\Components\Router;
use TheApp\Components\WebRequest;
use TheApp\Factories\ConfigFactory;
use TheApp\Factories\RequestFactory;
use TheApp\Factories\RouterFactory;
use TheApp\Interfaces\ConfigInterface;

return [
    ConfigInterface::class => function (ConfigFactory $configFactory) {
        return $configFactory->fromArray(require APP_ROOT . '/app/Config/config.php');
    },
    WebRequest::class => function (RequestFactory $requestFactory) {
        return $requestFactory->fromGlobals();
    },
    Router::class => function (RouterFactory $routerFactory, ConfigInterface $config) {
        return $routerFactory->fromConfig($config);
    },
    Engine::class => function (ConfigInterface $config, TemplateEngineFactory $factory) {
        return $factory->fromConfig($config);
    },
];