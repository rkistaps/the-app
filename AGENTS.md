# AGENTS.md

TheApp (`rkistaps/the-app`) is a small PHP micro-framework library. It routes PSR-7 requests through a PSR-15 middleware stack to request handlers, and dispatches console commands. It's consumed as a Composer package, so there is no application entry point (`public/index.php`) in this repo.

## Stack

- PHP `^8.3`
- PHP-DI 6 for the container and autowiring (`DI\Container`, `$container->call()`)
- PSR-7 / PSR-15 interfaces only. The repo ships no concrete request/response implementation.
- `filp/whoops` for error pages, `rappasoft/laravel-helpers` for `array_get()` and similar helpers, `samejack/php-argv` for CLI argument parsing
- Tests: PHPUnit 11 + Mockery

## Commands

PHP isn't installed on the host. Everything runs in the `theapp_workspace` Docker container (PHP 8.3 CLI, Composer, pcov), with the repo mounted at `/var/www/html`. Run the scripts from Git Bash.

```bash
./docker start | stop | restart | build | ssh
./docker-run composer install          # any command, run inside the container
./docker-test                          # ./vendor/bin/phpunit tests/
./docker-test --filter RouterTest tests/
./docker-coverage                      # text summary + coverage/html/index.html
```

There's no `phpunit.xml`, linter, or static analysis config. `package.json` is a leftover stub and isn't used. Docker and other dev-only files are excluded from the Composer package through `export-ignore` in `.gitattributes`. Add any new dev-only root file there too.

## Layout

Namespace `TheApp\` maps to `src/` and `TheApp\Tests\` maps to `tests/` (PSR-4).

| Directory | Contents |
| --- | --- |
| `src/Apps` | `App` (base, holds a static container), `WebApp`, `ConsoleApp` |
| `src/Components` | Runtime pieces: `Router`, `RouteRepository`, `MiddlewareStack`, `RouteHandler`, `CommandRunner`, `HttpResponseEmitter`, `ArrayConfig`, and `Callable*` adapters |
| `src/Factories` | Build components from the container and `ConfigInterface` |
| `src/Interfaces` | Extension points (`RouterConfiguratorInterface`, `CommandConfiguratorInterface`, `ErrorHandlerInterface`, and others) |
| `src/Structures` | Plain data objects with public properties (`Route`, `Command`, `RouteMatchResult`) |
| `src/Exceptions` | `InvalidConfigException`, `NoRouteMatchException` |
| `tests` | Mirrors `src/` (for example `tests/Components/RouterTest.php`) |

## How it works

**Web request flow.** `AppFactory::webAppFromContainer()` returns a `WebApp`. `WebApp::run($request, $router)` then does the following:

1. It registers Whoops.
2. `Router::getRouteHandler()` asks `RouteRepository::matchRoute()` for a match. If nothing matches, it throws `NoRouteMatchException`.
3. The matched route's handler and middlewares are resolved. A callable is wrapped in `CallableRequestHandler` or `CallableMiddleware`. A class name is fetched from the container.
4. `MiddlewareStackFactory` builds a `MiddlewareStack`. Route parameters are added as request attributes, and the stack handles the request.
5. Any `Throwable` goes to the error handler class named in the `error_handler` config key. If that key isn't set, the exception is rethrown.

`HttpResponseEmitter` sends the resulting response.

**Route paths** (the matching logic is adapted from AltoRouter):
- `*` matches any path.
- A path starting with `@` is a raw regex, with the `@` stripped.
- `[type:name]` defines a parameter, and a trailing `?` makes it optional. The types are `i` (int), `a` (alphanumeric), `h` (hex), `*`, `**`, and empty (a single segment).

**Config-driven wiring** uses `ConfigInterface` with dot-notation keys:
- `router.basePath`
- `router.configurators`: class names implementing `RouterConfiguratorInterface`
- `command.configurators`: class names implementing `CommandConfiguratorInterface`
- `error_handler`: a class implementing `ErrorHandlerInterface`

**Console.** `ConsoleApp::run($argv)` reads the `command` argument, looks it up in `CommandRunner`, and passes the remaining arguments to the handler's `handle(array $params)`.

## Conventions

- Resolve dependencies through the container or constructor injection. Don't use `new` for services. Handlers and middlewares may be a class name or a callable, so support both when adding new extension points.
- Wherever a factory resolves a configured class, check it against the expected interface and throw `InvalidConfigException` on a mismatch.
- `Router::withBasePath()` is immutable and returns a clone. Keep "with" methods immutable.
- Style is PSR-12. Newer code uses typed properties and constructor property promotion (see `Router`), while older code declares properties explicitly. Match the file you're editing, and prefer typed signatures in new code.
- Tests extend `Mockery\Adapter\Phpunit\MockeryTestCase`, mock collaborators with `Mockery::mock()`, and use `testMethodName` naming.
- Keep the package framework-agnostic. Depend on PSR interfaces, not on a specific PSR-7 implementation.
