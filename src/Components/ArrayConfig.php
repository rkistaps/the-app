<?php

declare(strict_types=1);

namespace TheApp\Components;

use Closure;
use TheApp\Interfaces\ConfigInterface;

/**
 * Config read from an array. Nested values are read with dot notation, such as "database.host".
 */
class ArrayConfig implements ConfigInterface
{
    public function __construct(private array $data = [])
    {
    }

    /**
     * @param mixed $default Returned when the key is missing. A Closure is called only then, and its result returned.
     */
    public function get(string $key, mixed $default = null): mixed
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
