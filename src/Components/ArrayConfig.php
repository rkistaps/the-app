<?php

namespace TheApp\Components;

use Closure;
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
     * Get config value by key. Nested values are read with dot notation, such as "database.host".
     * A Closure default is called only when the key is missing.
     * @param string $key
     * @param mixed|null $default
     * @return mixed
     */
    public function get(string $key, $default = null)
    {
        if (isset($this->data[$key])) {
            return $this->data[$key];
        }

        $value = $this->data;
        foreach (explode('.', $key) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default instanceof Closure ? $default() : $default;
            }

            $value = $value[$segment];
        }

        return $value;
    }
}
