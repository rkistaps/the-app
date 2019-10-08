<?php

namespace TheApp\Apps;

use AltoRouter;
use Psr\Container\ContainerInterface;
use TheApp\Handlers\TestHandler;

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

    /**
     * WebApp constructor.
     * @param AltoRouter $router
     * @param ContainerInterface $container
     */
    public function __construct(
        AltoRouter $router,
        ContainerInterface $container
    ) {
        $this->container = $container;
        $this->router = $router;
    }

    /**
     * Run application
     * @throws \Exception
     */
    public function run()
    {
        // TODO move route mapping outside
        $this->router->map('get', '/[i:id]', TestHandler::class);
        $this->router->map('get', '/', function () {
            return 'Hello world';
        });

        $match = $this->router->match();

        if (!is_array($match)) {
            header("HTTP/1.0 404 Not Found");
            die;
        }

        $target = $match['target'] ?? [];
        $params = $match['params'] ?? [];

        $handler = is_callable($target) ? $target : $this->container->get($target);
        $response = $this->container->call($handler, $params);

        if ($response) {
            echo $response;
        }
    }
}
