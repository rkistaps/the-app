<?php

namespace TheApp\Apps;

use Psr\Container\ContainerInterface;
use TheApp\Components\Router;
use TheApp\Components\WebRequest;
use TheApp\Exceptions\BadHandlerResponseException;
use TheApp\Exceptions\MissingRequestHandlerException;
use TheApp\Exceptions\NoRouteMatchException;
use TheApp\Factories\CallableFactory;
use TheApp\Interfaces\ConfigInterface;
use TheApp\Interfaces\ResponseInterface;
use TheApp\Responses\SimpleResponse;
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

    /** @var WebRequest */
    private $request;

    /** @var ConfigInterface */
    private $config;

    /** @var CallableFactory */
    private $callableFactory;

    /**
     * WebApp constructor.
     * @param Router $router
     * @param ContainerInterface $container
     * @param WebRequest $request
     * @param ConfigInterface $config
     * @param CallableFactory $callableFactory
     */
    public function __construct(
        Router $router,
        ContainerInterface $container,
        WebRequest $request,
        ConfigInterface $config,
        CallableFactory $callableFactory
    ) {
        $this->container = $container;
        $this->router = $router;
        $this->request = $request;
        $this->config = $config;
        $this->callableFactory = $callableFactory;
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

            $match = $this->router->match(
                $this->request->getUri(),
                $this->request->method
            );

            if (!$match->isMatch()) {
                throw new NoRouteMatchException('No route match');
            }

            $response = $this->processMatchResult($match);

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

    /**
     * @param RouterMatchResult $result
     * @return ResponseInterface
     * @throws BadHandlerResponseException
     * @throws MissingRequestHandlerException
     */
    protected function processMatchResult(RouterMatchResult $result)
    {
        $handler = $this->getMatchResultHandler($result);
        if (!$handler) {
            throw new MissingRequestHandlerException('No handler found');
        }

        $response = $this->container->call($handler, $result->params);
        if (is_string($response)) {
            $response = new SimpleResponse($response);
        }

        if (!is_a($response, ResponseInterface::class)) {
            throw new BadHandlerResponseException('Response does not implement ' . ResponseInterface::class);
        }

        return $response;
    }

    /**
     * @param RouterMatchResult $matchResult
     * @return callable|null
     */
    protected function getMatchResultHandler(RouterMatchResult $matchResult)
    {
        if (!$matchResult->target) {
            return null;
        }

        if (is_callable($matchResult->target)) {
            return $matchResult->target;
        }

        if (is_string($matchResult->target) && $this->container->has($matchResult->target)) {
            return $this->container->get($matchResult->target);
        }

        return null;
    }
}
