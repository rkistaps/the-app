<?php

namespace TheApp\Apps;

use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TheApp\Factories\ErrorHandlerFactory;
use TheApp\Factories\MiddlewareStackFactory;
use TheApp\Interfaces\ConfigInterface;
use TheApp\Interfaces\RouterInterface;
use Throwable;
use Whoops\Handler\PrettyPageHandler;
use Whoops\Run;

/**
 * Class WebApp
 * @package TheApp\Apps
 */
class WebApp extends App
{
    private ContainerInterface $container;
    private ?RouterInterface $router = null;
    private MiddlewareStackFactory $stackFactory;
    private ErrorHandlerFactory $errorHandlerFactory;
    private ConfigInterface $config;

    public function __construct(
        ContainerInterface $container,
        MiddlewareStackFactory $stackFactory,
        ErrorHandlerFactory $errorHandlerFactory,
        ConfigInterface $config
    ) {
        parent::__construct($container);

        $this->container = $container;
        $this->stackFactory = $stackFactory;
        $this->errorHandlerFactory = $errorHandlerFactory;
        $this->config = $config;
    }

    public function addRouter(RouterInterface $router): self
    {
        $this->router = $router;

        return $this;
    }

    /**
     * Run application
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     * @throws Throwable
     */
    public function run(ServerRequestInterface $request): ResponseInterface
    {
        try {
            $this->bootstrapApp();

            $handler = $this->router->getRouteHandler($request);
            $stack = $this->stackFactory->buildFromRouteHandler($handler);

            foreach ($handler->getAttributes() as $name => $value) {
                $request = $request->withAttribute($name, $value);
            }

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
