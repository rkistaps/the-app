<?php

namespace TheApp\Apps;

use AltoRouter;
use Psr\Container\ContainerInterface;
use TheApp\Components\WebRequest;

/**
 * Class WebApp
 * @package TheApp\Apps
 */
class WebApp
{
    /** @var ContainerInterface */
    private $container;

    /** @var AltoRouter */
    private $router;

    /** @var WebRequest */
    private $request;

    /**
     * WebApp constructor.
     * @param AltoRouter $router
     * @param ContainerInterface $container
     * @param WebRequest $request
     */
    public function __construct(
        AltoRouter $router,
        ContainerInterface $container,
        WebRequest $request
    ) {
        $this->container = $container;
        $this->router = $router;
        $this->request = $request;
    }

    /**
     * Run application
     * @throws \Exception
     */
    public function run()
    {
        $match = $this->router->match(
            $this->request->getUri(),
            $this->request->method
        );

        if (!is_array($match)) {
            header("HTTP/1.0 404 Not Found");
            die;
        }

        $target = $match['target'] ?? [];
        $params = $match['params'] ?? [];

        $handler = null;
        if (is_string($target) && $this->container->has($target)) {
            $handler = $this->container->get($target);
        } elseif (is_callable($target)) {
            $handler = $target;
        }

        if ($handler) {
            $response = $this->container->call($handler, $params);
            if ($response) {
                echo $response;
            }
        }
    }
}
