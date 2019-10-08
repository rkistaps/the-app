<?php

define("APP_ROOT", realpath(__DIR__ . '/..'));

// vendor autoloader
use DI\ContainerBuilder;
use TheApp\Factories\AppFactory;

require APP_ROOT . '/vendor/autoload.php';

$container = (new ContainerBuilder())
    ->addDefinitions(require APP_ROOT . '/app/Config/dependencies.php')
    ->build();

$app = AppFactory::fromContainer($container);

$app->run();