<?php

namespace TheApp\Structures;

use TheApp\Traits\FromArrayTrait;

/**
 * Result of router match
 */
class RouterMatchResult
{
    use FromArrayTrait;

    /**
     * The target of the route, given during mapping the route.
     * @var mixed
     */
    public $target;

    /**
     * The name of the route.
     * @var string|null
     */
    public $name;


    /**
     * Named parameters found in the request url.
     * @var array
     */
    public $params = [];
}
