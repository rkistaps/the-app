<?php

// vendor autoloader
use DI\ContainerBuilder;
use TheApp\Factories\AppFactory;
use TheApp\Factories\ConfigFactory;

require __DIR__ . '/../vendor/autoload.php';

$container = (new ContainerBuilder())
    ->addDefinitions(require __DIR__ . '/../app/config/dependencies.php')
    ->build();

$app = AppFactory::fromContainer($container);

$app->run();