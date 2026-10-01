# TheApp

TheApp is a small PHP micro-framework for two jobs:

- **Web:** it routes PSR-7 requests through PSR-15 middleware to your request handlers.
- **Console:** it maps command-line arguments to command handlers.

It's built on [PHP-DI](https://php-di.org/), so handlers, middleware and commands are resolved from the container with their dependencies autowired. It doesn't include a PSR-7 implementation, so you can use any one. The examples below use [nyholm/psr7](https://github.com/Nyholm/psr7), and `Acme\` stands for your own project's namespace. Classes in `TheApp\` come from this package.

- [Requirements](#requirements)
- [Installation](#installation)
- [Web application](#web-application)
  - [Routes](#1-routes)
  - [Front controller](#2-front-controller)
  - [Route paths](#route-paths)
  - [Generating URLs](#generating-urls)
  - [Middleware](#middleware)
  - [Error handling](#error-handling)
  - [Building responses](#building-responses)
- [Console application](#console-application)
- [Versioning and support](#versioning-and-support)
- [Development](#development)
- [License](#license)

## Requirements

- PHP 8.3 or later
- For web applications, a PSR-7 and PSR-17 implementation, such as `nyholm/psr7`. Both `psr/http-message` 1.x and 2.x are supported.

## Installation

```bash
composer require rkistaps/the-app
```

For web applications, also install a PSR-7 implementation and a way to build the request from PHP globals:

```bash
composer require nyholm/psr7 nyholm/psr7-server
```

## Web application

TheApp doesn't expect any particular files or directories. It needs a PHP-DI container, your route definitions, and an entry point that runs the app.

### 1. Routes

Routes are registered by *router configurators*, which are classes implementing `RouterConfiguratorInterface`. You pass them to the app in the front controller.

A route handler is either a class name or a callable:

- **Class name:** the class must implement PSR-15 `RequestHandlerInterface`. It's fetched from the container, so its constructor dependencies are injected.
- **Callable:** it receives the request as its first argument. Any other type-hinted parameters are resolved from the container.

```php
// src/Routes/WebRoutes.php
namespace Acme\Routes;

use Acme\Handlers\HomeHandler;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ServerRequestInterface;
use TheApp\Components\Router;
use TheApp\Interfaces\RouterConfiguratorInterface;

final class WebRoutes implements RouterConfiguratorInterface
{
    public function configureRouter(Router $router): void
    {
        $router->get('/', HomeHandler::class, 'home');

        $router->get('/users/[i:id]', function (ServerRequestInterface $request, ResponseFactoryInterface $responses) {
            $response = $responses->createResponse();
            $response->getBody()->write('User #' . $request->getAttribute('id'));

            return $response;
        });

        $router->post('/users', function (ServerRequestInterface $request, ResponseFactoryInterface $responses) {
            return $responses->createResponse(201);
        });
    }
}
```

```php
// src/Handlers/HomeHandler.php
namespace Acme\Handlers;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class HomeHandler implements RequestHandlerInterface
{
    public function __construct(private ResponseFactoryInterface $responses)
    {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $response = $this->responses->createResponse();
        $response->getBody()->write('Hello from TheApp');

        return $response;
    }
}
```

The router has a method for each HTTP method: `get()`, `post()`, `put()`, `patch()`, `delete()` and `options()`. `get()` routes also answer `HEAD` requests. `any()` matches every method, and `map()` takes a list, as in `$router->map(['GET', 'POST'], '/search', SearchHandler::class)`. Routes are checked in the order they were registered, and the first match wins.

### 2. Front controller

```php
// index.php
use Acme\Errors\ErrorHandler;
use Acme\Routes\WebRoutes;
use DI\ContainerBuilder;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7Server\ServerRequestCreator;
use Psr\Http\Message\ResponseFactoryInterface;
use TheApp\Components\HttpResponseEmitter;
use TheApp\Factories\AppFactory;

require __DIR__ . '/vendor/autoload.php';

$psr17 = new Psr17Factory();
$request = (new ServerRequestCreator($psr17, $psr17, $psr17, $psr17))->fromGlobals();

$container = (new ContainerBuilder())
    ->addDefinitions([
        // Used by the handlers in these examples. TheApp itself needs no definitions.
        ResponseFactoryInterface::class => $psr17,
    ])
    ->build();

$app = AppFactory::web($container)
    ->withRouterConfigurators([
        WebRoutes::class,
    ])
    ->withErrorHandler(ErrorHandler::class);

(new HttpResponseEmitter())->emit($app->run($request));
```

`AppFactory::web()` and `AppFactory::console()` take an optional PHP-DI container. Without one, they build a default container, which is enough when your handlers need no container definitions. TheApp uses PHP-DI to autowire handlers and to call callables with their dependencies, so the container must be a `DI\Container`.

How the setup methods behave:
- **Arguments:** `withRouterConfigurators()`, `withMiddleware()` and `withErrorHandler()` accept class names, which are resolved from the container, or ready-made instances. `withMiddleware()` also accepts callables.
- **Immutable:** each returns a new app and leaves the original unchanged.
- **Type checks:** a class that doesn't implement the expected interface throws `TheApp\Exceptions\InvalidConfigException`.
- **Lists in a file:** a long list of configurators can live in a file of your choice that returns an array of class names, such as `->withRouterConfigurators(require __DIR__ . '/routes.php')`.

Build the container however and wherever suits your project. For bigger apps that usually means a separate file of definitions.

Point your web server at the front controller for every request that isn't a real file. To try it locally, run `php -S localhost:8080 index.php`.

### Route paths

| Path | Matches |
| --- | --- |
| `/about` | The exact path |
| `/users/[i:id]` | An integer segment, available as `$request->getAttribute('id')` |
| `/posts/[a:slug]` | An alphanumeric segment |
| `/colors/[h:hex]` | A hexadecimal segment |
| `/pages/[:name]` | Any single segment, up to the next `/` or `.` |
| `/files/[*:path]` | Anything, including `/` (lazy match) |
| `/files/[**:path]` | Anything, including `/` (greedy match) |
| `/archive/[i:year]/[i:month]?` | An optional parameter, marked with a trailing `?` |
| `@^/legacy/(?<id>\d+)$` | A raw regex, marked with a leading `@`. Named groups become request attributes |
| `*` | Every path. Useful as a catch-all registered last |

Paths are matched while still URL-encoded, so an encoded `/` (`%2F`) never ends a segment. Parameter values reach the handler decoded: `/pages/J%C4%81nis` gives `name` the value `Jānis`.

To put routes under a common prefix, register them in a group. `group()` passes your callback a router that adds the prefix, and returns the group, so you can add middleware to all of its routes at once (see [Middleware](#middleware)). Groups can be nested: prefixes add up. The prefix is added to normal paths but not to `*` or `@` paths.

```php
public function configureRouter(Router $router): void
{
    $router->group('/admin', function (Router $admin) {
        $admin->get('/users', UserListHandler::class);    // matches /admin/users
        $admin->group('/reports', function (Router $reports) {
            $reports->get('/daily', DailyReportHandler::class); // matches /admin/reports/daily
        });
    });
}
```

For a prefix without a group, `withBasePath()` returns a router that adds the prefix and registers into the same route list, as in `$router->withBasePath('/api')->get('/users', UserListHandler::class)`.

### Generating URLs

Give a route a name as the last argument, then build its path with `Router::url()`. Names are unique: registering a second route with the same name throws `InvalidConfigException`. The router is available to handlers and middleware as the `Router::class` request attribute.

```php
$router->get('/users/[i:id]/[:tab]?', UserHandler::class, 'user');

// In a handler
$router = $request->getAttribute(Router::class);
$router->url('user', ['id' => 42]);                   // /users/42
$router->url('user', ['id' => 42, 'tab' => 'posts']); // /users/42/posts
```

Values are URL-encoded, and optional parameters you leave out are dropped. `url()` throws `InvalidArgumentException` in these cases:
- no route has that name
- a required parameter is missing
- a value doesn't fit its parameter type (for example, `abc` for `[i:id]`)
- the route's path is `*` or an `@` regex

### Middleware

Middleware can be added at three levels:

- **App:** `WebApp::withMiddleware([...])` runs on every request, before routing, so also for requests no route matches. Use it for CORS, sessions, security headers or request logging.
- **Group:** `addMiddleware()` on the group that `Router::group()` returns runs for every route in the group, including routes registered before the call.
- **Route:** `addMiddleware()` on a route runs for that route only.

A request goes through them from the outside in: the app's middleware, then the groups' from the outermost in, then the route's, each in the order it was added. Like handlers, a middleware can be given in three ways:

- **Class name:** the class must implement PSR-15 `MiddlewareInterface` and is resolved from the container. Any other class throws `InvalidConfigException`.
- **Instance:** a ready-made `MiddlewareInterface` object.
- **Callable:** it receives the request and the next handler.

```php
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

// In the front controller: every request
$app = AppFactory::web($container)
    ->withMiddleware([
        CorsMiddleware::class,
        function (ServerRequestInterface $request, RequestHandlerInterface $next) {
            return $next->handle($request)->withHeader('X-Frame-Options', 'DENY');
        },
    ])
    ->withRouterConfigurators([WebRoutes::class]);

// In a route configurator: a group and a single route
$router->group('/admin', function (Router $admin) {
    $admin->get('/users', UserListHandler::class);
    $admin->post('/users', CreateUserHandler::class)->addMiddleware(CsrfMiddleware::class);
})->addMiddleware(AuthMiddleware::class);
```

When an error handler is set, a route's exception, or a 404 or 405, becomes the error handler's response inside the app's middleware. So app middleware gets that response and can add headers to it too, such as CORS headers on an error. An exception from the app's middleware itself goes to the error handler as well. Without an error handler, exceptions pass through the app's middleware and are rethrown from `run()`.

### Error handling

`WebApp::run()` catches every exception, including `TheApp\Exceptions\NoRouteMatchException` when no route matches. It passes the exception and the request to the handler set with `withErrorHandler()`, as shown in the [front controller](#2-front-controller). Without one, the exception is rethrown from `run()`. TheApp doesn't register any global error or exception handlers.

The request lets the error response depend on it, for example JSON for API paths or for clients that send `Accept: application/json`. Once a route has matched, the request also has the route parameters.

```php
namespace Acme\Errors;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TheApp\Exceptions\MethodNotAllowedException;
use TheApp\Exceptions\NoRouteMatchException;
use TheApp\Interfaces\ErrorHandlerInterface;
use Throwable;

final class ErrorHandler implements ErrorHandlerInterface
{
    public function __construct(private ResponseFactoryInterface $responses)
    {
    }

    public function handle(Throwable $throwable, ServerRequestInterface $request): ResponseInterface
    {
        if ($throwable instanceof MethodNotAllowedException) {
            return $this->responses->createResponse(405)
                ->withHeader('Allow', implode(', ', $throwable->getAllowedMethods()));
        }

        $status = $throwable instanceof NoRouteMatchException ? 404 : 500;
        $message = $status === 404 ? 'Not found' : 'Something went wrong';

        $response = $this->responses->createResponse($status);
        if (str_contains($request->getHeaderLine('Accept'), 'application/json')) {
            $response->getBody()->write(json_encode(['error' => $message]));

            return $response->withHeader('Content-Type', 'application/json');
        }

        $response->getBody()->write($message);

        return $response;
    }
}
```

When a route matches the path but not the HTTP method, the router throws `MethodNotAllowedException`. It extends `NoRouteMatchException`, so check for it first, as above. Error handlers that don't check for it keep returning 404.

For a debug page during development, install [Whoops](https://github.com/filp/whoops) with `composer require --dev filp/whoops`. Then register it in the front controller only in development, and skip `withErrorHandler()` so exceptions reach it:

```php
if ($isDevelopment) {
    $whoops = new \Whoops\Run();
    $whoops->pushHandler(new \Whoops\Handler\PrettyPageHandler());
    $whoops->register();
}
```

### Building responses

`ResponseBuilder` is a fluent wrapper around a PSR-7 response. It needs a `ResponseFactoryInterface` in the container. Create it with `make()` so each response starts from a fresh builder. The builder is mutable, and `get()` would return a shared instance.

```php
use DI\Container;
use Psr\Http\Message\ServerRequestInterface;
use TheApp\Components\Builders\ResponseBuilder;

$router->get('/api/status', function (ServerRequestInterface $request, Container $container) {
    return $container->make(ResponseBuilder::class)
        ->withStatus(200)
        ->withHeader('Content-Type', 'application/json')
        ->withContent(json_encode(['status' => 'ok']))
        ->build();
});

$router->get('/old-page', function (ServerRequestInterface $request, Container $container) {
    return $container->make(ResponseBuilder::class)->withRedirect('/new-page')->build(); // 301 by default
});
```

## Console application

Commands are registered by *command configurators*, which are classes implementing `CommandConfiguratorInterface`. As on the web side, TheApp needs no container definitions of its own.

A command handler is either a callable or a class name:

- **Callable:** command-line options are matched to its parameters by name and converted to the parameter's type. Parameters not passed on the command line use their default value, or are resolved from the container if they have a class type. It can return an `int` exit code; any other return value, or none, counts as `0`.
- **Class name:** the class must implement `CommandHandlerInterface`, and its `handle()` method receives all options as an array of strings and returns the exit code. A flag without a value is `true`.

```php
namespace Acme\Console;

use TheApp\Components\CommandRunner;
use TheApp\Interfaces\CommandConfiguratorInterface;

final class UserCommands implements CommandConfiguratorInterface
{
    public function configureCommands(CommandRunner $commandRunner): void
    {
        $commandRunner->addCommand('user/greet', function (string $name, int $times = 1) {
            for ($i = 0; $i < $times; $i++) {
                echo "Hello, {$name}!" . PHP_EOL;
            }
        });

        $commandRunner->addCommand('user/import', ImportUsersCommand::class);
    }
}
```

```php
namespace Acme\Console;

use TheApp\Interfaces\CommandHandlerInterface;

final class ImportUsersCommand implements CommandHandlerInterface
{
    public function handle(array $params = []): int
    {
        $file = $params['file'] ?? 'users.csv';
        if (!is_readable($file)) {
            echo "Cannot read {$file}" . PHP_EOL;
            return 1;
        }

        echo "Importing users from {$file}" . PHP_EOL;

        return 0;
    }
}
```

The entry point passes the configurators and `$argv` to the console app:

```php
// console.php
use Acme\Console\UserCommands;
use TheApp\Factories\AppFactory;

require __DIR__ . '/vendor/autoload.php';

$exitCode = AppFactory::console()
    ->withCommandConfigurators([
        UserCommands::class,
    ])
    ->run($argv);

exit($exitCode);
```

`withCommandConfigurators()` works like `withRouterConfigurators()`. It takes class names or instances, returns a new app, and accepts an array loaded from a file, such as `require __DIR__ . '/commands.php'`. Command names are unique: adding a second command with the same name throws `InvalidConfigException`.

Pass the command name first, then options as `--name=value`, or `--name` alone for a flag:

```bash
php console.php user/greet --name=World --times=2
php console.php user/import --file=export.csv
```

`--command=user/greet` also works in place of the first argument.

For callable commands, option values are converted to the parameter's type:

| Parameter type | Accepted values |
| --- | --- |
| `bool` | `true`, `false`, `1`, `0`, `yes`, `no`, `on`, `off`, or the flag alone (`--save`) for `true` |
| `int` | Whole numbers, such as `--budget=-10` |
| `float` | Numbers, such as `--fee=0.002` |
| `string` | Any value. A flag without a value is rejected |

`run()` returns the command's exit code. It returns `1` when the command isn't found or its input is invalid, and in the second case prints a message such as `Missing required option --name` or `Option --times expects an integer`.

Any other exception from a command goes to the handler set with `withErrorHandler()`, which reports it and returns the exit code. Without one, the exception is rethrown from `run()`, and PHP prints it and exits with code 255.

```php
namespace Acme\Console;

use Psr\Log\LoggerInterface;
use TheApp\Interfaces\ConsoleErrorHandlerInterface;
use Throwable;

final class ConsoleErrorHandler implements ConsoleErrorHandlerInterface
{
    public function __construct(private LoggerInterface $logger)
    {
    }

    public function handle(Throwable $throwable, array $argv): int
    {
        $this->logger->error('Command failed: ' . implode(' ', $argv), ['exception' => $throwable]);
        fwrite(STDERR, $throwable->getMessage() . PHP_EOL);

        return 1;
    }
}
```

```php
$exitCode = AppFactory::console($container)
    ->withCommandConfigurators([UserCommands::class])
    ->withErrorHandler(ConsoleErrorHandler::class)
    ->run($argv);
```

## Versioning and support

TheApp follows [semantic versioning](https://semver.org/). From 1.0, minor and patch releases don't break the public API. The public API is every class, interface and method in `TheApp\` except those marked `@internal`. Internal classes, such as `RouteRepository`, `MiddlewareStack` and the `Callable*` adapters, can change in any release.

Before 1.0, minor releases (0.x) may contain breaking changes, which are listed in the changelog.

Supported versions:
- **PHP:** 8.3, 8.4 and 8.5. Support for a PHP version is dropped only in a major release, and only once that PHP version no longer receives security fixes.
- **TheApp:** the latest release receives bug and security fixes.

To report a security vulnerability, see [SECURITY.md](SECURITY.md).

## Development

PHP runs in Docker, so you only need Docker and a Bash shell (such as Git Bash on Windows).

```bash
./docker start                             # start the PHP 8.3 container
./docker-run composer install
./docker-test                              # PHPUnit
./docker-test --filter RouterTest tests/
./docker-coverage                          # coverage report in coverage/html/
./docker-run ./vendor/bin/phpstan analyse  # static analysis (level 8)
./docker ssh                               # shell inside the container
```

## License

TheApp is released under the [MIT License](LICENSE). You can use it for any purpose, including commercial projects, as long as you keep the copyright notice.
