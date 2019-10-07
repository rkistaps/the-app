<?php

use TheApp\Factories\ConfigFactory;
use TheApp\Interfaces\ConfigInterface;

return [
    ConfigInterface::class => function () {
        return ConfigFactory::fromArray([]);
    },
];