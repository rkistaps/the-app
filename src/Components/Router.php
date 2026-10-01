<?php

declare(strict_types=1);

namespace TheApp\Components;

use InvalidArgumentException;
use Psr\Http\Message\ServerRequestInterface;
use TheApp\Components\Repositories\RouteRepository;
use TheApp\Factories\MiddlewareStackFactory;
use TheApp\Factories\RequestHandlerFactory;
use TheApp\Exceptions\InvalidConfigException;
use TheApp\Exceptions\MethodNotAllowedException;
use TheApp\Exceptions\NoRouteMatchException;
use TheApp\Interfaces\RouteHandlerInterface;
use TheApp\Structures\Route;
use TheApp\Structures\RouteGroup;
use TheApp\Structures\RouteMatchResult;

/**
 * Registers routes, and builds the paths of named routes. Router configurators get one, and handlers
 * get the app's router as the Router::class request attribute.
 */
class Router
{
    private string $basePath = '';

    /** The group that routes registered through this router belong to */
    private ?RouteGroup $group = null;

    /**
     * @internal Each app builds its own router from its configurators
     */
    public function __construct(
        private RouteRepository $repository,
        private RequestHandlerFactory $requestHandlerFactory,
        private MiddlewareStackFactory $stackFactory
    ) {
    }

    public function withBasePath(string $basePath): Router
    {
        $router = clone $this;
        $router->basePath = $basePath;

        return $router;
    }

    /**
     * Register routes under a common path prefix, and return the group so middleware can be added to all of
     * them at once. The callback gets a router that adds the prefix and puts its routes in the group. Groups
     * can be nested: prefixes add up, and an outer group's middleware runs before an inner group's.
     *
     *   $router->group('/admin', function (Router $admin) {
     *       $admin->get('/users', UserListHandler::class);  // matches /admin/users
     *   })->addMiddleware(AuthMiddleware::class);
     *
     * @param callable(Router): void $routes
     */
    public function group(string $prefix, callable $routes): RouteGroup
    {
        $group = new RouteGroup($this->basePath . $prefix, $this->group);

        $router = $this->withBasePath($group->getPrefix());
        $router->group = $group;
        $routes($router);

        return $group;
    }

    public function getBasePath(): string
    {
        return $this->basePath;
    }

    /**
     * @internal Used by WebApp to match the request
     * @throws MethodNotAllowedException When a route matches the path but not the method
     * @throws NoRouteMatchException|InvalidConfigException
     */
    public function getRouteHandler(ServerRequestInterface $request): RouteHandlerInterface
    {
        $matchResult = $this->repository->matchRoute($request);
        if (!$matchResult) {
            $allowedMethods = $this->repository->findAllowedMethods($request);
            if ($allowedMethods) {
                throw new MethodNotAllowedException($allowedMethods);
            }

            throw new NoRouteMatchException('No route match');
        }

        return $this->initializeRoute($matchResult);
    }

    /**
     * Build the path of a named route, such as url('user', ['id' => 5]) for "/users/[i:id]"
     *
     * @param array<string, string|int> $parameters
     * @throws InvalidArgumentException When no route has the name, or the parameters don't fit the route
     */
    public function url(string $name, array $parameters = []): string
    {
        $route = $this->repository->findRouteByName($name);
        if (!$route) {
            throw new InvalidArgumentException(sprintf('No route named "%s"', $name));
        }

        return $this->repository->buildPath($route, $parameters);
    }

    private function initializeRoute(RouteMatchResult $matchResult): RouteHandlerInterface
    {
        $route = $matchResult->getRoute();

        $requestHandler = $this->requestHandlerFactory->fromRoute($route);

        $handler = new RouteHandler($requestHandler);
        $handler->addMiddlewares(...array_map($this->stackFactory->resolve(...), $route->getMiddlewares()));

        foreach ($matchResult->getParameters() as $name => $value) {
            $handler->addAttribute($name, $value);
        }

        return $handler;
    }

    /**
     * Add route for GET requests. HEAD requests to the path are matched too.
     */
    public function get(string $path, callable|string $handler, ?string $name = null): Route
    {
        return $this->map([Route::METHOD_GET], $path, $handler, $name);
    }

    /**
     * Add route for POST requests
     */
    public function post(string $path, callable|string $handler, ?string $name = null): Route
    {
        return $this->map([Route::METHOD_POST], $path, $handler, $name);
    }

    /**
     * Add route for PUT requests
     */
    public function put(string $path, callable|string $handler, ?string $name = null): Route
    {
        return $this->map([Route::METHOD_PUT], $path, $handler, $name);
    }

    /**
     * Add route for PATCH requests
     */
    public function patch(string $path, callable|string $handler, ?string $name = null): Route
    {
        return $this->map([Route::METHOD_PATCH], $path, $handler, $name);
    }

    /**
     * Add route for DELETE requests
     */
    public function delete(string $path, callable|string $handler, ?string $name = null): Route
    {
        return $this->map([Route::METHOD_DELETE], $path, $handler, $name);
    }

    /**
     * Add route for OPTIONS requests
     */
    public function options(string $path, callable|string $handler, ?string $name = null): Route
    {
        return $this->map([Route::METHOD_OPTIONS], $path, $handler, $name);
    }

    /**
     * Add route for any type of request
     */
    public function any(string $path, callable|string $handler, ?string $name = null): Route
    {
        return $this->map([Route::METHOD_ANY], $path, $handler, $name);
    }

    /**
     * Add route for several HTTP methods, such as ['GET', 'POST']
     * @param string[] $methods
     */
    public function map(array $methods, string $path, callable|string $handler, ?string $name = null): Route
    {
        $route = $this->buildRoute($methods, $this->withBasePathPrefix($path), $handler, $name);

        $this->repository->addRoute($route);

        return $route;
    }

    /**
     * Prefix a route path with the base path. The "*" (any path) and "@" (custom regex) paths
     * are special forms that a prefix would break, so they are left as they are.
     */
    private function withBasePathPrefix(string $path): string
    {
        if ($path === '*' || str_starts_with($path, '@')) {
            return $path;
        }

        return $this->basePath . $path;
    }

    /**
     * @param string[] $methods
     */
    private function buildRoute(array $methods, string $path, callable|string $handler, ?string $name = null): Route
    {
        return new Route($methods, $path, $handler, $name, $this->group);
    }
}
