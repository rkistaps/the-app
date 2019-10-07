<?php

namespace TheApp\Components;

use TheApp\Interfaces\ConfigInterface;

/**
 * Class ArrayConfig
 * @package TheApp\Components
 */
class ArrayConfig implements ConfigInterface
{
    /**
     * Config data
     * @var array
     */
    private $data = [];

    /**
     * ArrayConfig constructor.
     * @param array $data
     */
    public function __construct(array $data = [])
    {
        $this->data = $data;
    }

    /**
     * Get config value by key
     * @param string $key
     * @param mixed|null $default
     * @return mixed
     */
    public function get(string $key, $default = null)
    {
        return array_get($this->data, $key, $default);
    }
}
