<?php

namespace TheApp\Tests\Apps;

use DI\Container;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryTestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;
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

        $this->app = new WebApp($this->container, new MiddlewareStackFactory(), new RequestHandlerFactory($this->container));
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
        $handler = Mockery::mock(ErrorHandlerInterface::class);
        $handler->shouldReceive('handle')
            ->once()
            ->with(Mockery::type(NoRouteMatchException::class))
            ->andReturn($this->response);
        $this->container->set('errorHandler', $handler);

        $app = $this->app->withErrorHandler('errorHandler');

        $this->assertSame($this->response, $app->run($this->request('/missing')));
    }

    public function testInvalidErrorHandlerThrows()
    {
        $this->container->set('notAnErrorHandler', new stdClass());
        $app = $this->app->withErrorHandler('notAnErrorHandler');

        $this->expectException(InvalidConfigException::class);
        $app->run($this->request('/missing'));
    }

    private function configurator(string $path, ResponseInterface $response): RouterConfiguratorInterface
    {
        return new class ($path, $response) implements RouterConfiguratorInterface {
            public function __construct(private string $path, private ResponseInterface $response)
            {
            }

            public function configureRouter(Router $router): void
            {
                $router->get($this->path, fn() => $this->response);
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

    private function request(string $path): ServerRequestInterface
    {
        $uri = Mockery::mock(UriInterface::class);
        $uri->shouldReceive('getPath')->andReturn($path);

        $request = Mockery::mock(ServerRequestInterface::class);
        $request->shouldReceive('getUri')->andReturn($uri);
        $request->shouldReceive('getMethod')->andReturn('GET');

        return $request;
    }
}
