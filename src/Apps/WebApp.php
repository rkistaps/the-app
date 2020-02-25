<?php

namespace TheApp\Apps;

use Idealo\Middleware\Stack;
use Phly\Http\Response;
use Phly\Http\ServerRequestFactory;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TheApp\Components\ResponseEmitter;
use TheApp\Components\Router;
use TheApp\Exceptions\MissingRequestHandlerException;
use TheApp\Exceptions\NoRouteMatchException;
use TheApp\Factories\CallableFactory;
use TheApp\Interfaces\ConfigInterface;
use TheApp\Responses\SimpleResponse;
use TheApp\Structures\RouterMatchResult;
use Throwable;
use Whoops\Handler\PrettyPageHandler;
use Whoops\RunInterface;

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

    /** @var ResponseEmitter */
    private $responseEmitter;

    /** @var RunInterface */
    private $run;

    /**
     * WebApp constructor.
     * @param Router $router
     * @param ContainerInterface $container
     * @param ConfigInterface $config
     * @param CallableFactory $callableFactory
     * @param ResponseEmitter $responseEmitter
     * @param RunInterface $run
     */
    public function __construct(
        Router $router,
        ContainerInterface $container,
        ConfigInterface $config,
        CallableFactory $callableFactory,
        ResponseEmitter $responseEmitter,
        RunInterface $run
    ) {
        $this->container = $container;
        $this->router = $router;
        $this->config = $config;
        $this->callableFactory = $callableFactory;
        $this->responseEmitter = $responseEmitter;
        $this->run = $run;
    }

    /**
     * Run application
     * @throws Throwable
     */
    public function run()
    {
        try {
            $this->run->pushHandler(new PrettyPageHandler());
            $this->run->register();

            // create request
            $request = ServerRequestFactory::fromGlobals();

            $routeMatchResult = $this->router->match($request);

            if (!$routeMatchResult) {
                throw new NoRouteMatchException('No route match');
            }

            $response = $this->processMatchedRoute($request, $routeMatchResult);

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
            throw $throwable;
        }

        $response = $this->container->call($handler, ['throwable' => $throwable]);
        if (is_string($response)) {
            $response = new SimpleResponse($response);
        }

        $response->respond();
    }

    /**
     * @param ServerRequestInterface $request
     * @param RouterMatchResult $result
     * @return ResponseInterface
     * @throws MissingRequestHandlerException
     */
    protected function processMatchedRoute(ServerRequestInterface $request, RouterMatchResult $result): ResponseInterface
    {
        $response = new Response();

        $handler = $this->getMatchResultHandler($result);
        if (!$handler) {
            throw new MissingRequestHandlerException('No handler found');
        }

        $response = $handle->


        $stack = new Stack($response, ...$result->route->middlewares);
        $response = $stack->handle($request);

        return $response;
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
