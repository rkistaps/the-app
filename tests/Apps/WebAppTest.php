<?php

declare(strict_types=1);

namespace TheApp\Tests\Apps;

use DI\Container;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryTestCase;
use Mockery\MockInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RuntimeException;
use stdClass;
use TheApp\Apps\WebApp;
use TheApp\Components\Router;
use TheApp\Exceptions\InvalidConfigException;
use TheApp\Exceptions\NoRouteMatchException;
use TheApp\Factories\MiddlewareStackFactory;
use TheApp\Factories\RequestHandlerFactory;
use TheApp\Interfaces\ErrorHandlerInterface;
use TheApp\Interfaces\RouterConfiguratorInterface;

class WebAppTest extends MockeryTestCase
{
    private Container $container;
    private WebApp $app;
    private ResponseInterface $response;

    protected function setUp(): void
    {
        $this->container = new Container();
        $this->response = Mockery::mock(ResponseInterface::class);

        $this->app = new WebApp($this->container, new MiddlewareStackFactory($this->container), new RequestHandlerFactory($this->container));
    }

    public function testRunRegistersNoGlobalErrorHandlers()
    {
        $app = $this->app->withRouterConfigurators([$this->configurator('/hello', $this->response)]);

        $errorHandler = $this->currentErrorHandler();
        $exceptionHandler = $this->currentExceptionHandler();

        $app->run($this->request('/hello'));

        $this->assertSame($errorHandler, $this->currentErrorHandler());
        $this->assertSame($exceptionHandler, $this->currentExceptionHandler());
    }

    public function testRouterIsPassedToHandlersAsRequestAttribute()
    {
        $app = $this->app->withRouterConfigurators([$this->configurator('/hello', $this->response)]);

        $request = $this->request('/hello');
        $request->shouldReceive('withAttribute')->once()->with(Router::class, Mockery::type(Router::class))->andReturnSelf();

        $this->assertSame($this->response, $app->run($request));
    }

    public function testRouteMiddlewareFromContainerAndCallables()
    {
        $calls = [];
        $classMiddleware = Mockery::mock(MiddlewareInterface::class);
        $classMiddleware->shouldReceive('process')->once()->andReturnUsing(
            function (ServerRequestInterface $request, RequestHandlerInterface $next) use (&$calls) {
                $calls[] = 'class';
                return $next->handle($request);
            }
        );
        $this->container->set('authMiddleware', $classMiddleware);

        $app = $this->app->withRouterConfigurators([$this->routes(function (Router $router) use (&$calls) {
            $router->get('/admin', fn() => $this->response)
                ->addMiddleware('authMiddleware')
                ->addMiddleware(function (ServerRequestInterface $request, RequestHandlerInterface $next) use (&$calls) {
                    $calls[] = 'callable';
                    return $next->handle($request);
                });
        })]);

        $this->assertSame($this->response, $app->run($this->request('/admin')));
        $this->assertSame(['class', 'callable'], $calls);
    }

    public function testWithRouterConfiguratorsAcceptsInstancesAndClassNames()
    {
        $other = Mockery::mock(ResponseInterface::class);
        $this->container->set('otherRoutes', $this->configurator('/other', $other));

        $app = $this->app->withRouterConfigurators([
            $this->configurator('/hello', $this->response),
            'otherRoutes',
        ]);

        $this->assertSame($this->response, $app->run($this->request('/hello')));
        $this->assertSame($other, $app->run($this->request('/other')));
    }

    public function testWithRouterConfiguratorsReturnsNewApp()
    {
        $app = $this->app->withRouterConfigurators([$this->configurator('/hello', $this->response)]);

        $this->assertNotSame($this->app, $app);
        $this->assertSame($this->response, $app->run($this->request('/hello')));

        $this->expectException(NoRouteMatchException::class);
        $this->app->run($this->request('/hello'));
    }

    public function testErrorHandlerHandlesExceptions()
    {
        $request = $this->request('/missing');
        $handler = Mockery::mock(ErrorHandlerInterface::class);
        $handler->shouldReceive('handle')
            ->once()
            ->with(Mockery::type(NoRouteMatchException::class), $request)
            ->andReturn($this->response);
        $this->container->set('errorHandler', $handler);

        $app = $this->app->withErrorHandler('errorHandler');

        $this->assertSame($this->response, $app->run($request));
    }

    public function testErrorHandlerGetsRequestWithRouteAttributes()
    {
        $exception = new RuntimeException('Handler failed');
        $request = $this->request('/users/7');
        $withId = Mockery::mock(ServerRequestInterface::class);
        $request->shouldReceive('withAttribute')->with('id', '7')->andReturn($withId);

        $handler = Mockery::mock(ErrorHandlerInterface::class);
        $handler->shouldReceive('handle')->once()->with($exception, $withId)->andReturn($this->response);

        $app = $this->app
            ->withRouterConfigurators([$this->routes(fn(Router $router) => $router->get('/users/[i:id]', function () use ($exception) {
                throw $exception;
            }))])
            ->withErrorHandler($handler);

        $this->assertSame($this->response, $app->run($request));
    }

    public function testInvalidErrorHandlerThrows()
    {
        $this->container->set('notAnErrorHandler', new stdClass());
        $app = $this->app->withErrorHandler('notAnErrorHandler');

        $this->expectException(InvalidConfigException::class);
        $app->run($this->request('/missing'));
    }

    public function testAppMiddlewareRunsAroundGroupAndRouteMiddleware()
    {
        $calls = [];
        $this->container->set('appClassMiddleware', $this->recordingMiddleware('app class', $calls));

        $app = $this->app
            ->withMiddleware([
                'appClassMiddleware',
                $this->recordingMiddleware('app instance', $calls),
                $this->recordingCallable('app callable', $calls),
            ])
            ->withRouterConfigurators([$this->routes(function (Router $router) use (&$calls) {
                $router->group('/admin', function (Router $admin) use (&$calls) {
                    $admin->get('/users', fn() => $this->response)->addMiddleware($this->recordingCallable('route', $calls));
                })->addMiddleware($this->recordingMiddleware('group', $calls));
            })]);

        $this->assertSame($this->response, $app->run($this->request('/admin/users')));
        $this->assertSame(['app class', 'app instance', 'app callable', 'group', 'route'], $calls);
    }

    public function testAppMiddlewareRunsForUnmatchedRequestsAndGetsErrorResponse()
    {
        $calls = [];
        $errorResponse = Mockery::mock(ResponseInterface::class);
        $withHeader = Mockery::mock(ResponseInterface::class);
        $errorResponse->shouldReceive('withHeader')->once()->with('X-Frame-Options', 'DENY')->andReturn($withHeader);

        $handler = Mockery::mock(ErrorHandlerInterface::class);
        $handler->shouldReceive('handle')->once()->with(Mockery::type(NoRouteMatchException::class), Mockery::any())->andReturn($errorResponse);

        $app = $this->app
            ->withMiddleware([function (ServerRequestInterface $request, RequestHandlerInterface $next) use (&$calls) {
                $calls[] = 'app';
                return $next->handle($request)->withHeader('X-Frame-Options', 'DENY');
            }])
            ->withErrorHandler($handler);

        $this->assertSame($withHeader, $app->run($this->request('/missing')));
        $this->assertSame(['app'], $calls);
    }

    public function testExceptionFromAppMiddlewareGoesToErrorHandler()
    {
        $exception = new RuntimeException('Session store unavailable');
        $request = $this->request('/hello');

        $handler = Mockery::mock(ErrorHandlerInterface::class);
        $handler->shouldReceive('handle')->once()->with($exception, $request)->andReturn($this->response);

        $app = $this->app
            ->withRouterConfigurators([$this->configurator('/hello', Mockery::mock(ResponseInterface::class))])
            ->withMiddleware([function () use ($exception) {
                throw $exception;
            }])
            ->withErrorHandler($handler);

        $this->assertSame($this->response, $app->run($request));
    }

    public function testExceptionPassesThroughAppMiddlewareWithoutErrorHandler()
    {
        $calls = [];
        $app = $this->app->withMiddleware([$this->recordingMiddleware('app', $calls)]);

        try {
            $app->run($this->request('/missing'));
            $this->fail('Expected NoRouteMatchException');
        } catch (NoRouteMatchException) {
            $this->assertSame(['app'], $calls);
        }
    }

    public function testAppMiddlewareClassMustImplementMiddlewareInterface()
    {
        $this->container->set('notMiddleware', new stdClass());
        $app = $this->app->withMiddleware(['notMiddleware']);

        $this->expectException(InvalidConfigException::class);
        $this->expectExceptionMessage('stdClass does not implement ' . MiddlewareInterface::class);
        $app->run($this->request('/hello'));
    }

    public function testWithMiddlewareReturnsNewAppAndAppends()
    {
        $calls = [];
        $base = $this->app->withRouterConfigurators([$this->configurator('/hello', $this->response)]);
        $app = $base
            ->withMiddleware([$this->recordingCallable('first', $calls)])
            ->withMiddleware([$this->recordingCallable('second', $calls)]);

        $this->assertNotSame($base, $app);
        $base->run($this->request('/hello'));
        $this->assertSame([], $calls);

        $app->run($this->request('/hello'));
        $this->assertSame(['first', 'second'], $calls);
    }

    /**
     * @param string[] $calls
     */
    private function recordingMiddleware(string $name, array &$calls): MiddlewareInterface
    {
        $middleware = Mockery::mock(MiddlewareInterface::class);
        $middleware->shouldReceive('process')->andReturnUsing($this->recordingCallable($name, $calls));

        return $middleware;
    }

    /**
     * @param string[] $calls
     */
    private function recordingCallable(string $name, array &$calls): callable
    {
        return function (ServerRequestInterface $request, RequestHandlerInterface $next) use ($name, &$calls) {
            $calls[] = $name;
            return $next->handle($request);
        };
    }

    private function configurator(string $path, ResponseInterface $response): RouterConfiguratorInterface
    {
        return $this->routes(fn(Router $router) => $router->get($path, fn() => $response));
    }

    private function routes(callable $configure): RouterConfiguratorInterface
    {
        return new class ($configure) implements RouterConfiguratorInterface {
            /** @var callable */
            private $configure;

            public function __construct(callable $configure)
            {
                $this->configure = $configure;
            }

            public function configureRouter(Router $router): void
            {
                ($this->configure)($router);
            }
        };
    }

    private function currentErrorHandler(): mixed
    {
        $handler = set_error_handler(fn() => false);
        restore_error_handler();

        return $handler;
    }

    private function currentExceptionHandler(): mixed
    {
        $handler = set_exception_handler(null);
        restore_exception_handler();

        return $handler;
    }

    private function request(string $path): ServerRequestInterface&MockInterface
    {
        $uri = Mockery::mock(UriInterface::class);
        $uri->shouldReceive('getPath')->andReturn($path);

        $request = Mockery::mock(ServerRequestInterface::class);
        $request->shouldReceive('getUri')->andReturn($uri);
        $request->shouldReceive('getMethod')->andReturn('GET');
        $request->shouldReceive('withAttribute')->andReturnSelf()->byDefault();

        return $request;
    }
}
