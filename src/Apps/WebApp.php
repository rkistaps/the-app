<?php

namespace TheApp\Apps;

use DI\Container;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TheApp\Components\Repositories\RouteRepository;
use TheApp\Components\Router;
use TheApp\Exceptions\InvalidConfigException;
use TheApp\Factories\MiddlewareStackFactory;
use TheApp\Factories\RequestHandlerFactory;
use TheApp\Interfaces\ErrorHandlerInterface;
use TheApp\Interfaces\RouterConfiguratorInterface;
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
    private Container $diContainer;
    private MiddlewareStackFactory $stackFactory;
    private RequestHandlerFactory $requestHandlerFactory;

    /** @var array<RouterConfiguratorInterface|string> */
    private array $routerConfigurators = [];
    private ErrorHandlerInterface|string|null $errorHandler = null;

    /** Router with the configurators applied, built on first use */
    private ?Router $configuredRouter = null;

    public function __construct(
        Container $container,
        MiddlewareStackFactory $stackFactory,
        RequestHandlerFactory $requestHandlerFactory
    ) {
        parent::__construct($container);

        $this->diContainer = $container;
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
     * Run application
     * @param ServerRequestInterface $request
     * @param RouterInterface|null $router Router to use instead of the one built from withRouterConfigurators()
     * @return ResponseInterface
     * @throws Throwable
     */
    public function run(
        ServerRequestInterface $request,
        ?RouterInterface $router = null
    ): ResponseInterface {
        try {
            $this->bootstrapApp();

            $router ??= $this->getRouter();
            $handler = $router->getRouteHandler($request);
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
     * @throws InvalidConfigException
     */
    protected function getRouter(): Router
    {
        if ($this->configuredRouter === null) {
            // A new repository, so apps returned by withRouterConfigurators() don't share routes
            $router = new Router(new RouteRepository(), $this->requestHandlerFactory, $this->diContainer);
            foreach ($this->routerConfigurators as $configurator) {
                $this->resolve($configurator, RouterConfiguratorInterface::class)->configureRouter($router);
            }

            $this->configuredRouter = $router;
        }

        return $this->configuredRouter;
    }

    /**
     * @param Throwable $throwable
     * @return ResponseInterface
     * @throws Throwable
     */
    protected function handleErrors(Throwable $throwable): ResponseInterface
    {
        if ($this->errorHandler === null) {
            throw $throwable;
        }

        return $this->resolve($this->errorHandler, ErrorHandlerInterface::class)->handle($throwable);
    }
}
