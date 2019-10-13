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
     * Map GET request
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
     * Map POST request
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
     * Map PATCH request
     * @param $route
     * @param $target
     * @param null $name
     * @throws Exception
     */
    public function patch($route, $target, $name = null)
    {
        $this->map('PATCH', $route, $target, $name);
    }

    /**
     * Map PUT request
     * @param $route
     * @param $target
     * @param null $name
     * @throws Exception
     */
    public function put($route, $target, $name = null)
    {
        $this->map('PUT', $route, $target, $name);
    }

    /**
     * Map DELETE request
     * @param $route
     * @param $target
     * @param null $name
     * @throws Exception
     */
    public function delete($route, $target, $name = null)
    {
        $this->map('delete', $route, $target, $name);
    }

    /**
     * Map any request
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
     * @return RouterMatchResult
     */
    public function match($requestUrl = null, $requestMethod = null)
    {
        $result = parent::match($requestUrl, $requestMethod);

        return RouterMatchResult::fromArray($result ?: []);
    }
}
