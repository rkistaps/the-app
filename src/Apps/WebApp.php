<?php

declare(strict_types=1);

namespace TheApp\Apps;

use DI\Container;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use TheApp\Components\CallableRequestHandler;
use TheApp\Components\Repositories\RouteRepository;
use TheApp\Components\Router;
use TheApp\Exceptions\InvalidConfigException;
use TheApp\Factories\MiddlewareStackFactory;
use TheApp\Factories\RequestHandlerFactory;
use TheApp\Interfaces\ErrorHandlerInterface;
use TheApp\Interfaces\RouterConfiguratorInterface;
use Throwable;

/**
 * Routes PSR-7 requests through the app's middleware and the matched route's middleware to its handler
 */
class WebApp extends App
{
    private MiddlewareStackFactory $stackFactory;
    private RequestHandlerFactory $requestHandlerFactory;

    /** @var array<RouterConfiguratorInterface|string> */
    private array $routerConfigurators = [];

    /** @var array<MiddlewareInterface|callable|string> */
    private array $middlewares = [];
    private ErrorHandlerInterface|string|null $errorHandler = null;

    /** Router with the configurators applied, built on first use */
    private ?Router $configuredRouter = null;

    public function __construct(
        Container $container,
        MiddlewareStackFactory $stackFactory,
        RequestHandlerFactory $requestHandlerFactory
    ) {
        parent::__construct($container);

        $this->stackFactory = $stackFactory;
        $this->requestHandlerFactory = $requestHandlerFactory;
    }

    public function __clone()
    {
        $this->configuredRouter = null;
    }

    /**
     * Return a new app that also registers routes from the given configurators.
     * Class names are resolved from the container when the app runs.
     *
     * @param array<RouterConfiguratorInterface|string> $configurators
     */
    public function withRouterConfigurators(array $configurators): static
    {
        $app = clone $this;
        $app->routerConfigurators = [...$this->routerConfigurators, ...array_values($configurators)];

        return $app;
    }

    /**
     * Return a new app that also runs the given middleware on every request, before routing, so also for
     * requests that no route matches. Each is a MiddlewareInterface instance, a class name resolved from
     * the container, or a callable that gets the request and the next handler. They run in the given order,
     * around the route's own middleware.
     *
     * @param array<MiddlewareInterface|callable|string> $middlewares
     */
    public function withMiddleware(array $middlewares): static
    {
        $app = clone $this;
        $app->middlewares = [...$this->middlewares, ...array_values($middlewares)];

        return $app;
    }

    /**
     * Return a new app that turns uncaught exceptions into responses with the given handler.
     * Without one, exceptions are rethrown. A class name is resolved from the container on the first error.
     */
    public function withErrorHandler(ErrorHandlerInterface|string $errorHandler): static
    {
        $app = clone $this;
        $app->errorHandler = $errorHandler;

        return $app;
    }

    /**
     * Route the request through the app's middleware and its route's middleware to its handler
     *
     * @throws Throwable When no error handler is set
     */
    public function run(ServerRequestInterface $request): ResponseInterface
    {
        try {
            $dispatcher = new CallableRequestHandler(fn(ServerRequestInterface $request) => $this->dispatch($request), $this->container);

            return $this->stackFactory->build($dispatcher, $this->middlewares)->handle($request);
        } catch (Throwable $throwable) {
            // An exception from the app's own middleware, which runs outside dispatch()
            return $this->handleErrors($throwable, $request);
        }
    }

    /**
     * Match the route and run its middleware and handler. Exceptions become the error handler's response
     * here, inside the app's middleware, so that middleware also sees and can change error responses,
     * such as by adding CORS or security headers to a 404.
     *
     * @throws Throwable When no error handler is set
     */
    private function dispatch(ServerRequestInterface $request): ResponseInterface
    {
        try {
            $router = $this->getRouter();
            $handler = $router->getRouteHandler($request);
            $stack = $this->stackFactory->buildFromRouteHandler($handler);

            // Lets handlers build URLs with $request->getAttribute(Router::class)->url()
            $request = $request->withAttribute(Router::class, $router);

            foreach ($handler->getAttributes() as $name => $value) {
                $request = $request->withAttribute($name, $value);
            }

            return $stack->handle($request);
        } catch (Throwable $throwable) {
            return $this->handleErrors($throwable, $request);
        }
    }

    /**
     * @throws InvalidConfigException
     */
    private function getRouter(): Router
    {
        if ($this->configuredRouter === null) {
            // A new repository, so apps returned by withRouterConfigurators() don't share routes
            $router = new Router(new RouteRepository(), $this->requestHandlerFactory, $this->stackFactory);
            foreach ($this->routerConfigurators as $configurator) {
                $this->resolve($configurator, RouterConfiguratorInterface::class)->configureRouter($router);
            }

            $this->configuredRouter = $router;
        }

        return $this->configuredRouter;
    }

    /**
     * @throws Throwable When no error handler is set
     */
    private function handleErrors(Throwable $throwable, ServerRequestInterface $request): ResponseInterface
    {
        if ($this->errorHandler === null) {
            throw $throwable;
        }

        return $this->resolve($this->errorHandler, ErrorHandlerInterface::class)->handle($throwable, $request);
    }
}
