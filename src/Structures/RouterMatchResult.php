<?php

namespace TheApp\Structures;

use TheApp\Traits\FromArrayTrait;

/**
 * Result of router match
 */
class RouterMatchResult
{
    /** @var Route */
    public $route;

    /** @var array */
    public $params = [];

    /**
     * @param Route $route
     * @return $this
     */
    public function setRoute(Route $route)
    {
        $this->route = $route;

        return $this;
    }

    /**
     * @param array $params
     * @return RouterMatchResult
     */
    public function setParams($params)
    {
        $this->params = $params;

        return $this;
    }
}
