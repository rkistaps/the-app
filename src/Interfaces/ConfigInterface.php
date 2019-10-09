<?php

namespace TheApp\Interfaces;

/**
 * Class ConfigInterface
 * @package TheApp\Interfaces
 */
interface ConfigInterface
{
    /**
     * Get config value by key
     * @param string $key
     * @param mixed|null $default
     * @return mixed
     */
    public function get(string $key, $default = null);
}
