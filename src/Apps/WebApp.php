<?php

namespace TheApp\Apps;

use Idealo\Middleware\Stack;
use Phly\Http\Response;
use Phly\Http\ServerRequestFactory;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use TheApp\Components\ResponseEmitter;
use TheApp\Components\Router;
use TheApp\Exceptions\BadHandlerResponseException;
use TheApp\Exceptions\MissingRequestHandlerException;
use TheApp\Exceptions\NoRouteMatchException;
use TheApp\Factories\CallableFactory;
use TheApp\Interfaces\ConfigInterface;
use TheApp\Responses\SimpleResponse;
use TheApp\Structures\Route;
use TheApp\Structures\RouterMatchResult;
use Throwable;
use Whoops\Handler\PrettyPageHandler;
use Whoops\Run;

/**
 * Class WebApp
 * @package TheApp\Apps
 */
class WebApp
{
    /** @var ContainerInterface */
    private $container;

    /** @var Router */
    private $router;

    /** @var ConfigInterface */
    private $config;

    /** @var CallableFactory */
    private $callableFactory;

    private $responseEmitter;

    /**
     * WebApp constructor.
     * @param Router $router
     * @param ContainerInterface $container
     * @param ConfigInterface $config
     * @param CallableFactory $callableFactory
     * @param ResponseEmitter $responseEmitter
     */
    public function __construct(
        Router $router,
        ContainerInterface $container,
        ConfigInterface $config,
        CallableFactory $callableFactory,
        ResponseEmitter $responseEmitter
    ) {
        $this->container = $container;
        $this->router = $router;
        $this->config = $config;
        $this->callableFactory = $callableFactory;
        $this->responseEmitter = $responseEmitter;
    }

    /**
     * Run application
     * @throws Throwable
     */
    public function run()
    {
        try {
            $whoops = new Run;
            $whoops->prependHandler(new PrettyPageHandler);
            $whoops->register();

            // create request
            $request = ServerRequestFactory::fromGlobals();

            $routeMatchResult = $this->router->match($request);

            if (!$routeMatchResult) {
                throw new NoRouteMatchException('No route match');
            }

            $response = new Response();

            $response = $this->processMatchedRoute($response, $routeMatchResult);

            $this->responseEmitter->emit($response);
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

    /**
     * @param ResponseInterface $response
     * @param RouterMatchResult $result
     * @return ResponseInterface
     * @throws BadHandlerResponseException
     * @throws MissingRequestHandlerException
     */
    protected function processMatchedRoute(ResponseInterface $response, RouterMatchResult $result)
    {
        $handler = $this->getMatchResultHandler($result);
        if (!$handler) {
            throw new MissingRequestHandlerException('No handler found');
        }

        $stack = new Stack($response);
        $response = $stack->handle(...[$response] + $result->route->middlewares);

        if (!is_a($response, ResponseInterface::class)) {
            throw new BadHandlerResponseException('Response does not implement ' . ResponseInterface::class);
        }

        return $response;
    }

    protected function buildMiddlewareStack(ResponseInterface $response, Route $route)
    {
        $middlewares = array_map(function ($className) {
            return $this->container->get($className);
        }, $route->middlewares);

        return new Stack($response, $middlewares);
    }

    /**
     * @param RouterMatchResult $matchResult
     * @return callable|null
     */
    protected function getMatchResultHandler(RouterMatchResult $matchResult)
    {
        if (!$matchResult->route->target) {
            return null;
        }

        if (is_callable($matchResult->route->target)) {
            return $matchResult->route->target;
        }

        if (is_string($matchResult->route->target) && $this->container->has($matchResult->route->target)) {
            return $this->container->get($matchResult->route->target);
        }

        return null;
    }
}
