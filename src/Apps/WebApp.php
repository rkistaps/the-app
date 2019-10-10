<?php

namespace TheApp\Apps;

use AltoRouter;
use Psr\Container\ContainerInterface;
use TheApp\Components\WebRequest;
use TheApp\Exceptions\BadHandlerResponseException;
use TheApp\Exceptions\MissingRequestHandlerException;
use TheApp\Exceptions\NoRouteMatchException;
use TheApp\Interfaces\ResponseInterface;
use TheApp\Responses\SimpleResponse;
use TheApp\Structures\RouterMatchResult;

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

        if (!$match) {
            throw new NoRouteMatchException();
        }

        $matchResult = RouterMatchResult::fromArray($match);
        $response = $this->processMatchResult($matchResult);

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
            throw new MissingRequestHandlerException();
        }

        $response = $this->container->call($handler, $result->params);
        if (is_string($response)) {
            $response = new SimpleResponse($response);
        }

        if (!is_a($response, ResponseInterface::class)) {
            throw new BadHandlerResponseException();
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
