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
     * @return mixed
     */
    public function get(string $key);
}
