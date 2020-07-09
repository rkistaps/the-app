<?php

namespace TheApp\Apps;

use Phly\Http\ServerRequestFactory;
use Psr\Container\ContainerInterface;
use TheApp\Components\Router;
use TheApp\Exceptions\NoRouteMatchException;
use TheApp\Factories\CallableFactory;
use TheApp\Factories\RequestHandlerFactory;
use TheApp\Interfaces\ConfigInterface;
use TheApp\Responses\SimpleResponse;
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
    private ConfigInterface $config;
    private CallableFactory $callableFactory;
    private RequestHandlerFactory $handlerFactory;

    public function __construct(
        Router $router,
        ContainerInterface $container,
        ConfigInterface $config,
        CallableFactory $callableFactory,
        RequestHandlerFactory $handlerFactory
    ) {
        $this->container = $container;
        $this->router = $router;
        $this->config = $config;
        $this->callableFactory = $callableFactory;
        $this->handlerFactory = $handlerFactory;
    }

    /**
     * Run application
     * @throws Throwable
     */
    public function run()
    {
        try {
            $whoops = new Run();
            $whoops->prependHandler(new PrettyPageHandler());
            $whoops->register();

            // create request
            $request = ServerRequestFactory::fromGlobals();

            $handlerClass = $this->router->process($request);
            if (!$handlerClass) {
                throw new NoRouteMatchException('No route match');
            }

            if (is_string($handlerClass)) {
                $handler = $this->handlerFactory->getHandlerInstance($handlerClass);

                $response = $handler->handle($request);
            }

            $response->respond();
        } catch (Throwable $throwable) {
            $this->handleErrors($throwable);
        }
    }

    /**
     * @param Throwable $throwable
     * @throws Throwable
     */
    protected function handleErrors(Throwable $throwable)
    {
        $handler = $this->config->get('errorHandler');
        $handler = $handler ? $this->callableFactory->getCallable($handler) : null;
        if (!$handler || !is_callable($handler)) { // no handler
            throw  $throwable;
        }

        $response = $this->container->call($handler, ['throwable' => $throwable]);
        if (is_string($response)) {
            $response = new SimpleResponse($response);
        }

        $response->respond();
    }
}
