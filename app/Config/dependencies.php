<?php

use TheApp\Components\WebRequest;
use TheApp\Factories\ConfigFactory;
use TheApp\Factories\RequestFactory;
use TheApp\Interfaces\ConfigInterface;

return [
    ConfigInterface::class => function () {
        return ConfigFactory::fromArray([]);
    },
    WebRequest::class => function (RequestFactory $requestFactory) {
        return $requestFactory->fromGlobals();
    },
];