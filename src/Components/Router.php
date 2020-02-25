<?php

namespace TheApp\Components;

use Exception;
use Psr\Http\Message\RequestInterface;
use TheApp\Structures\Route;
use TheApp\Structures\RouterMatchResult;

/**
 * Class Router
 * @package TheApp\Components
 */
class Router
{
    /** @var Route[] */
    protected $routes = [];

    /** @var string */
    protected $basePath;

    /**
     * @var array Array of default match types (regex helpers)
     */
    protected $matchTypes = [
        'i' => '[0-9]++',
        'a' => '[0-9A-Za-z]++',
        'h' => '[0-9A-Fa-f]++',
        '*' => '.+?',
        '**' => '.++',
        '' => '[^/\.]++',
    ];

    /**
     * Create router in one call from config.
     *
     * @param string $basePath
     * @param array $matchTypes
     */
    public function __construct($basePath = '', $matchTypes = [])
    {
        $this->setBasePath($basePath);
        $this->addMatchTypes($matchTypes);
    }

    /**
     * Add named match types. It uses array_merge so keys can be overwritten.
     *
     * @param array $matchTypes The key is the name and the value is the regex.
     */
    public function addMatchTypes($matchTypes)
    {
        $this->matchTypes = array_merge($this->matchTypes, $matchTypes);
    }

    /**
     * @param string $basePath
     * @return Router
     */
    public function setBasePath($basePath)
    {
        $this->basePath = $basePath;

        return $this;
    }

    /**
     * @param string $method
     * @param string $path
     * @param mixed $target
     * @param string|null $name
     * @return Route
     */
    public function map($method, $path, $target, $name = null)
    {
        $route = new Route();
        $route->methods = explode('|', $method);
        $route->path = $path;
        $route->target = $target;
        $route->name = $name;

        $this->routes[] = $route;

        return $route;
    }

    /**
     * Map GET request
     * @param string $path
     * @param mixed $target
     * @param string|null $name
     * @return string|Route
     * @throws Exception
     */
    public function get($path, $target, $name = null)
    {
        return $this->map('GET', $path, $target, $name);
    }

    /**
     * Map POST request
     * @param string $path
     * @param mixed $target
     * @param string|null $name
     * @return Route
     * @throws Exception
     */
    public function post($path, $target, $name = null)
    {
        return $this->map('POST', $path, $target, $name);
    }

    /**
     * Map PATCH request
     * @param string $path
     * @param mixed $target
     * @param string|null $name
     * @return Route
     * @throws Exception
     */
    public function patch($path, $target, $name = null)
    {
        return $this->map('PATCH', $path, $target, $name);
    }

    /**
     * Map PUT request
     * @param string $path
     * @param mixed $target
     * @param string|null $name
     * @return Route
     * @throws Exception
     */
    public function put($path, $target, $name = null)
    {
        return $this->map('PUT', $path, $target, $name);
    }

    /**
     * Map DELETE request
     * @param string|$path
     * @param mixed|$target
     * @param string|null $name
     * @return Route
     * @throws Exception
     */
    public function delete($path, $target, $name = null)
    {
        return $this->map('DELETE', $path, $target, $name);
    }

    /**
     * Map any request
     * @param string $path
     * @param mixed $target
     * @param string|null $name
     * @return Route
     * @throws Exception
     */
    public function any($path, $target, $name = null)
    {
        return $this->map('GET|POST|PATCH|PUT|DELETE', $path, $target, $name);
    }

    /**
     * @param RequestInterface $request
     * @return RouterMatchResult
     */
    public function match(RequestInterface $request)
    {
        $requestUrl = $request->getUri()->getPath();
        $requestMethod = $request->getMethod();

        // strip base path from request url
        $requestUrl = substr($requestUrl, strlen($this->basePath));

        foreach ($this->routes as $route) {
            if (!in_array($requestMethod, $route->methods)) {
                continue;
            }

            if ($route->path === '*') {
                $match = true;
            } elseif (isset($route->path[0]) && $route->path[0] === '@') {
                $pattern = '`' . substr($route->path, 1) . '`u';
                $match = preg_match($pattern, $requestUrl, $params);
            } else {
                $r = null;
                $regex = false;
                $j = 0;
                $n = isset($route->path[0]) ? $route->path[0] : null;
                $i = 0;

                // Find the longest non-regex substring and match it against the URI
                while (true) {
                    if (!isset($route->path[$i])) {
                        break;
                    } elseif (false === $regex) {
                        $c = $n;
                        $regex = $c === '[' || $c === '(' || $c === '.';
                        if (false === $regex && false !== isset($route->path[$i + 1])) {
                            $n = $route->path[$i + 1];
                            $regex = $n === '?' || $n === '+' || $n === '*' || $n === '{';
                        }
                        if (false === $regex && $c !== '/' && (!isset($requestUrl[$j]) || $c !== $requestUrl[$j])) {
                            continue 2;
                        }
                        $j++;
                    }
                    $r .= $route->path[$i++];
                }

                $regex = $this->compileRoute($r);
                $match = preg_match($regex, $requestUrl, $params);
            }

            if (($match == true || $match > 0)) {

                if (isset($params) && $params) {
                    foreach ($params as $key => $value) {
                        if (is_numeric($key)) {
                            unset($params[$key]);
                        }
                    }
                }

                return (new RouterMatchResult())
                    ->setRoute($route)
                    ->setParams($params);
            }
        }

        return null;
    }

    /**
     * Compile the regex for a given route (EXPENSIVE)
     * @param $route
     * @return string
     */
    private function compileRoute($route)
    {
        if (preg_match_all('`(/|\.|)\[([^:\]]*+)(?::([^:\]]*+))?\](\?|)`', $route, $matches, PREG_SET_ORDER)) {
            $matchTypes = $this->matchTypes;
            foreach ($matches as $match) {
                list($block, $pre, $type, $param, $optional) = $match;

                if (isset($matchTypes[$type])) {
                    $type = $matchTypes[$type];
                }
                if ($pre === '.') {
                    $pre = '\.';
                }

                //Older versions of PCRE require the 'P' in (?P<named>)
                $pattern = '(?:'
                    . ($pre !== '' ? $pre : null)
                    . '('
                    . ($param !== '' ? "?P<$param>" : null)
                    . $type
                    . '))'
                    . ($optional !== '' ? '?' : null);

                $route = str_replace($block, $pattern, $route);
            }

        }

        return "`^$route$`u";
    }
}
