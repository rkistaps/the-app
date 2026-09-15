# TheApp

TheApp is a small PHP micro-framework for two jobs:

- **Web:** it routes PSR-7 requests through PSR-15 middleware to your request handlers.
- **Console:** it maps command-line arguments to command handlers.

It's built on [PHP-DI](https://php-di.org/), so handlers, middleware and commands are resolved from the container with their dependencies autowired. It doesn't include a PSR-7 implementation, so you can use any one. The examples below use [nyholm/psr7](https://github.com/Nyholm/psr7).

- [Requirements](#requirements)
- [Installation](#installation)
- [Web application](#web-application)
  - [Container and config](#1-container-and-config)
  - [Routes](#2-routes)
  - [Front controller](#3-front-controller)
  - [Route paths](#route-paths)
  - [Middleware](#middleware)
  - [Error handling](#error-handling)
  - [Building responses](#building-responses)
- [Console application](#console-application)
- [Configuration reference](#configuration-reference)
- [Development](#development)

## Requirements

- PHP 8.3 or later
- For web applications, a PSR-7 and PSR-17 implementation, such as `nyholm/psr7`

## Installation

```bash
composer require rkistaps/the-app
```

For web applications, also install a PSR-7 implementation and a way to build the request from PHP globals:

```bash
composer require nyholm/psr7 nyholm/psr7-server
```

## Web application

A minimal project looks like this:

```
config/config.php         # configuration array
config/container.php      # builds the PHP-DI container
public/index.php          # front controller
src/Routes/AppRoutes.php  # route definitions
```

### 1. Container and config

Routes are registered by *router configurators*, which are classes you list in the config:

```php
// config/config.php
return [
    'router' => [
        'configurators' => [
            App\Routes\AppRoutes::class,
        ],
    ],
];
```

The container needs three definitions: the config, a PSR-17 response factory, and the router built from the config.

```php
// config/container.php
use DI\ContainerBuilder;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ResponseFactoryInterface;
use TheApp\Factories\ConfigFactory;
use TheApp\Factories\RouterFactory;
use TheApp\Interfaces\ConfigInterface;
use TheApp\Interfaces\RouterInterface;

$builder = new ContainerBuilder();
$builder->addDefinitions([
    ConfigInterface::class => fn(ConfigFactory $factory) => $factory->fromArray(require __DIR__ . '/config.php'),
    ResponseFactoryInterface::class => fn() => new Psr17Factory(),
    RouterInterface::class => fn(RouterFactory $factory, ConfigInterface $config) => $factory->buildFromConfig($config),
]);

return $builder->build();
```

### 2. Routes

A route handler is either a class name or a callable:

- **Class name:** the class must implement PSR-15 `RequestHandlerInterface`. It's fetched from the container, so its constructor dependencies are injected.
- **Callable:** it receives the request as its first argument. Any other type-hinted parameters are resolved from the container.

```php
// src/Routes/AppRoutes.php
namespace App\Routes;

use App\Handlers\HomeHandler;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ServerRequestInterface;
use TheApp\Components\Router;
use TheApp\Interfaces\RouterConfiguratorInterface;

final class AppRoutes implements RouterConfiguratorInterface
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
namespace App\Handlers;

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

The router provides `get()`, `post()` and `any()`, where `any()` matches every HTTP method. Routes are checked in the order they were registered, and the first match wins.

### 3. Front controller

```php
// public/index.php
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7Server\ServerRequestCreator;
use TheApp\Components\HttpResponseEmitter;
use TheApp\Factories\AppFactory;
use TheApp\Interfaces\RouterInterface;

require __DIR__ . '/../vendor/autoload.php';

$container = require __DIR__ . '/../config/container.php';

$psr17 = new Psr17Factory();
$request = (new ServerRequestCreator($psr17, $psr17, $psr17, $psr17))->fromGlobals();

$app = AppFactory::webAppFromContainer($container);
$response = $app->run($request, $container->get(RouterInterface::class));

(new HttpResponseEmitter())->emit($response);
```

Set your web server's document root to `public/` and send every request that isn't a real file to `index.php`. To try it locally, run `php -S localhost:8080 -t public`.

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

To put a group of routes under a common prefix, set `router.basePath` in the config, or call `$router->withBasePath('/api')`, which returns a new router. The prefix is added to normal paths but not to `*` or `@` paths.

### Middleware

Add middleware to a route with `withMiddleware()`. Like handlers, middleware can be a class name or a callable:

- **Class name:** the class must implement PSR-15 `MiddlewareInterface` and is resolved from the container.
- **Callable:** it receives the request and the next handler.

Middleware runs in the order it was added.

```php
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

$router->get('/admin', AdminHandler::class)
    ->withMiddleware(AuthMiddleware::class)
    ->withMiddleware(function (ServerRequestInterface $request, RequestHandlerInterface $next) {
        return $next->handle($request)->withHeader('X-Frame-Options', 'DENY');
    });
```

### Error handling

`WebApp::run()` catches every exception, including `TheApp\Exceptions\NoRouteMatchException` when no route matches. It passes the exception to the class named in the `error_handler` config key. If that key isn't set, the exception is rethrown and [Whoops](https://github.com/filp/whoops) shows its debug page. That's useful in development, but configure an error handler for production.

```php
// config/config.php
return [
    'error_handler' => App\Errors\ErrorHandler::class,
    // ...
];
```

```php
namespace App\Errors;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use TheApp\Exceptions\NoRouteMatchException;
use TheApp\Interfaces\ErrorHandlerInterface;
use Throwable;

final class ErrorHandler implements ErrorHandlerInterface
{
    public function __construct(private ResponseFactoryInterface $responses)
    {
    }

    public function handle(Throwable $throwable): ResponseInterface
    {
        $status = $throwable instanceof NoRouteMatchException ? 404 : 500;

        $response = $this->responses->createResponse($status);
        $response->getBody()->write($status === 404 ? 'Not found' : 'Something went wrong');

        return $response;
    }
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

Commands are registered by *command configurators*, which are listed in the config:

```php
// config/config.php
return [
    'command' => [
        'configurators' => [
            App\Console\UserCommands::class,
        ],
    ],
];
```

The container needs the config and a `CommandRunner` built from it:

```php
// config/container.php
use DI\ContainerBuilder;
use TheApp\Components\CommandRunner;
use TheApp\Factories\CommandRunnerFactory;
use TheApp\Factories\ConfigFactory;
use TheApp\Interfaces\ConfigInterface;

$builder = new ContainerBuilder();
$builder->addDefinitions([
    ConfigInterface::class => fn(ConfigFactory $factory) => $factory->fromArray(require __DIR__ . '/config.php'),
    CommandRunner::class => fn(CommandRunnerFactory $factory, ConfigInterface $config) => $factory->fromConfig($config),
]);

return $builder->build();
```

A command handler is either a callable or a class name:

- **Callable:** command-line options are matched to its parameters by name. Parameters not passed on the command line use their default value, or are resolved from the container by type.
- **Class name:** the class must implement `CommandHandlerInterface`, and its `handle()` method receives all options as an array.

```php
namespace App\Console;

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
namespace App\Console;

use TheApp\Interfaces\CommandHandlerInterface;

final class ImportUsersCommand implements CommandHandlerInterface
{
    public function handle(array $params = []): void
    {
        $file = $params['file'] ?? 'users.csv';
        echo "Importing users from {$file}" . PHP_EOL;
    }
}
```

The entry point passes `$argv` to the console app:

```php
// console.php
use TheApp\Factories\AppFactory;

require __DIR__ . '/vendor/autoload.php';

$container = require __DIR__ . '/config/container.php';

AppFactory::consoleAppFromContainer($container)->run($argv);
```

Choose the command with `--command`, and pass the other options as `--name=value`:

```bash
php console.php --command=user/greet --name=World --times=2
php console.php --command=user/import --file=export.csv
```

If the command name is missing or unknown, the app prints `Command not found`.

Option values arrive as strings, so avoid `bool` parameters: `--force=false` is converted to `true`. Take a string and compare it instead.

## Configuration reference

`ArrayConfig` reads nested keys with dot notation, such as `$config->get('router.basePath', '')`. You can inject `ConfigInterface` into your own classes to read your own settings the same way.

| Key | Used by | Description |
| --- | --- | --- |
| `router.configurators` | Web | Classes implementing `RouterConfiguratorInterface` |
| `router.basePath` | Web | Prefix added to every route path |
| `error_handler` | Web | Class implementing `ErrorHandlerInterface` |
| `command.configurators` | Console | Classes implementing `CommandConfiguratorInterface` |

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
