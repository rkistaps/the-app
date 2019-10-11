<?php

namespace TheApp\Components;

use AltoRouter;
use Exception;
use TheApp\Structures\RouterMatchResult;

/**
 * Class Router
 * @package TheApp\Components
 */
class Router extends AltoRouter
{
    /**
     * @param string $route
     * @param mixed $target
     * @param string|null $name
     * @throws Exception
     */
    public function get($route, $target, $name = null)
    {
        $this->map('GET', $route, $target, $name);
    }

    /**
     * @param string $route
     * @param mixed $target
     * @param null $name
     * @throws Exception
     */
    public function post($route, $target, $name = null)
    {
        $this->map('POST', $route, $target, $name);
    }

    /**
     * @param string $route
     * @param mixed $target
     * @param null $name
     * @throws Exception
     */
    public function any($route, $target, $name = null)
    {
        $this->map('GET|POST|PATCH|PUT|DELETE', $route, $target, $name);
    }

    /**
     * @param string|null $requestUrl
     * @param string|null $requestMethod
     * @return RouterMatchResult|null
     */
    public function match($requestUrl = null, $requestMethod = null)
    {
        $result = parent::match($requestUrl, $requestMethod);

        if (!$result) {
            return null;
        }

        return RouterMatchResult::fromArray($result);
    }
}
