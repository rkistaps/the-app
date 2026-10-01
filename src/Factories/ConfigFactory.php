<?php

declare(strict_types=1);

namespace TheApp\Factories;

use TheApp\Components\ArrayConfig;

class ConfigFactory
{
    public function fromArray(array $array = []): ArrayConfig
    {
        return new ArrayConfig($array);
    }
}
