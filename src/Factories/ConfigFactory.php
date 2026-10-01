<?php

declare(strict_types=1);

namespace TheApp\Factories;

use TheApp\Components\ArrayConfig;

/**
 * Class ConfigFactory
 * @package TheApp\Factories
 */
class ConfigFactory
{
    /**
     * Build array config from array
     * @param array $array
     * @return ArrayConfig
     */
    public function fromArray(array $array = []): ArrayConfig
    {
        return new ArrayConfig($array);
    }
}