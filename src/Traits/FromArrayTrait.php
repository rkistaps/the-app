<?php

namespace TheApp\Traits;

trait FromArrayTrait
{
    /**
     * Create object from array
     *
     * @param array $array
     * @return static
     */
    public static function fromArray(array $array = [])
    {
        $classname = static::class;
        $class = new $classname;

        foreach ($array as $key => $value) {
            if (property_exists($class, $key)) {
                $class->$key = $value;
            }
        }

        return $class;
    }
}
