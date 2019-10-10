<?php

namespace TheApp\Components;

use AltoRouter;
use Exception;

/**
 * Class Router
 * @package TheApp\Components
 */
class Router extends AltoRouter
{
    /**
     * @param string $route
     * @param string|null $name
     * @throws Exception
     */
    public function get($route, $name = null)
    {
        $this->map('get', $route, $name);
    }

    /**
     * @param $route
     * @param null $name
     * @throws Exception
     */
    public function post($route, $name = null)
    {
        $this->map('post', $route, $name = null);
    }
}
