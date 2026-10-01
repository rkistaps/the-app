<?php

declare(strict_types=1);

namespace TheApp\Interfaces;

/**
 * Settings for your own code to read. TheApp doesn't read any itself.
 */
interface ConfigInterface
{
    /**
     * @param mixed $default Returned when the key is missing
     */
    public function get(string $key, mixed $default = null): mixed;
}
