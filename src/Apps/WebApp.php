<?php

namespace TheApp\Apps;

use Jasny\HttpMessage\ServerRequest;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use TheApp\Components\Router;
use TheApp\Exceptions\NoRouteMatchException;
use TheApp\Factories\ErrorHandlerFactory;
use TheApp\Factories\MiddlewareStackFactory;
use TheApp\Interfaces\ConfigInterface;
use Throwable;
use Whoops\Handler\PrettyPageHandler;
use Whoops\Run;

/**
 * Class WebApp
 * @package TheApp\Apps
 */
class WebApp
{
    private ContainerInterface $container;
    private Router $router;
    private MiddlewareStackFactory $stackFactory;
    private ErrorHandlerFactory $errorHandlerFactory;
    private ConfigInterface $config;

    public function __construct(
        Router $router,
        ContainerInterface $container,
        MiddlewareStackFactory $stackFactory,
        ErrorHandlerFactory $errorHandlerFactory,
        ConfigInterface $config
    ) {
        $this->container = $container;
        $this->router = $router;
        $this->stackFactory = $stackFactory;
        $this->errorHandlerFactory = $errorHandlerFactory;
        $this->config = $config;
    }

    /**
     * Run application
     * @param ServerRequest $request
     * @return ResponseInterface
     * @throws Throwable
     */
    public function run(ServerRequest $request): ResponseInterface
    {
        try {
            $this->bootstrapApp();

            $route = $this->router->findRouteForRequest($request);
            if (!$route) {
                throw new NoRouteMatchException('No route match');
            }

            $stack = $this->stackFactory->buildFromRoute($route);
            $response = $stack->handle($request);
        } catch (Throwable $throwable) {
            $response = $this->handleErrors($throwable);
        }

        return $response;
    }

    protected function bootstrapApp()
    {
        $whoops = new Run();
        $whoops->prependHandler(new PrettyPageHandler());
        $whoops->register();
    }

    /**
     * @param Throwable $throwable
     * @return ResponseInterface
     * @throws Throwable
     */
    protected function handleErrors(Throwable $throwable): ResponseInterface
    {
        $handler = $this->errorHandlerFactory->buildFromConfig($this->config);
        if ($handler) {
            return $handler->handle($throwable);
        }

        throw $throwable;
    }
}
