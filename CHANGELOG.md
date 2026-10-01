# Changelog

All notable changes to this project are documented here. The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and the project uses [semantic versioning](https://semver.org/).

## [Unreleased]

## [0.9.0] - 2026-10-01

This release adds what production apps need on top of the 0.8 API: middleware for the whole app, route groups, and console output. All of it is additive; the one behaviour change is that the console app's own messages go to standard error.

### Added

- `WebApp::withMiddleware(array)` adds middleware that runs on every request, before routing, so also for requests no route matches. Use it for CORS, sessions, security headers or request logging. With an error handler set, error responses, including 404 and 405, pass back through this middleware, so it can add headers to them too.
- `Router::group($prefix, $callback)` registers routes under a common prefix and returns a `RouteGroup`, whose `addMiddleware()` adds middleware to every route in the group. Groups can be nested; prefixes add up, and an outer group's middleware runs first.
- `Route::addMiddleware()` and `RouteGroup::addMiddleware()` accept `MiddlewareInterface` instances, besides class names and callables.
- Console output: `OutputInterface` with `write()`, `writeln()` and `error()` for standard error. Commands get it from the container, in a command class's constructor or as a callable's parameter. `ConsoleApp::withOutput()` sets it; the default `StreamOutput` writes to standard output and standard error, and `BufferedOutput` keeps output and errors in memory for tests. `CommandHandlerInterface` is unchanged, and `echo` keeps working.

### Changed

- **Behaviour change:** `ConsoleApp`'s own messages, "Command not found" and invalid-option messages such as `Missing required option --name`, go to standard error instead of standard output, so they don't end up in a command's redirected output. Tests that checked them with `expectOutputString()` should pass a `BufferedOutput` to `withOutput()` and check `getErrors()`.
- A route middleware class name that resolves to something other than a `MiddlewareInterface` throws `InvalidConfigException`, like configurators and error handlers do. Before, it failed with a `TypeError`.

## [0.8.0] - 2026-10-01

This release settles the public API for 1.0 and makes several breaking changes. Each one below says what to change in your project.

### Added

- `ConsoleApp::withErrorHandler()` takes a `ConsoleErrorHandlerInterface`, which reports exceptions from commands and returns the exit code, like `WebApp::withErrorHandler()` does for web requests. Without one, exceptions are rethrown, as before.

### Changed

- **Breaking:** `CommandHandlerInterface::handle()` returns the command's exit code as an `int` instead of `void`, and `ConsoleApp::run()` returns it. Add `: int` to your command classes and return `0` on success. Callable commands can return an `int` too; any other return value, or none, still counts as `0`.
- **Breaking:** `ErrorHandlerInterface::handle()` also gets the request: `handle(Throwable $throwable, ServerRequestInterface $request)`. An error handler can now answer depending on the request, such as JSON for API paths, without a second app. Add the parameter to your error handlers.
- **Breaking:** `Route::withMiddleware()` is renamed to `addMiddleware()`, because it changes the route, while `with*` methods elsewhere return a copy. Rename the calls in your route configurators.
- **Breaking:** `Route`'s properties are private. Read them with `getMethods()`, `getPath()`, `getHandler()`, `getName()` and `getMiddlewares()`. Routes are created by the router; the new constructor is internal.
- **Breaking:** `ConfigInterface::get()` has native types: `get(string $key, mixed $default = null): mixed`. If you implement `ConfigInterface` yourself, add the types to your `get()`. Code that only calls `get()`, or uses `ArrayConfig`, is unaffected.
- **Breaking:** registering a second route with the same name, or a second command with the same name, throws `InvalidConfigException`. Before, the second one was silently ignored: `url()` built the first route's path, and the second command never ran. Rename one of them. Routes without a name are unaffected.
- **Breaking:** `ConsoleApp` and the `App` base class take a `DI\Container` instead of any PSR-11 `ContainerInterface`, like `WebApp` already did. Apps from `AppFactory` or the container are unaffected; only code that constructs `ConsoleApp` itself with another container needs a PHP-DI one.
- **Breaking:** `WebApp::run()` no longer takes a router as its second argument, and `RouterInterface` is removed. The router came from internal classes and returned an internal type, so it couldn't be built or implemented through the public API. Register routes with `withRouterConfigurators()` instead. `Router::getRouteHandler()` and the `Router` constructor are now internal.
- **Breaking:** methods that are implementation details are private instead of protected, so subclasses can no longer override them: `WebApp::getRouter()` and `handleErrors()`, `ConsoleApp::getCommandRunner()`, `Router::initializeRoute()` and `buildRoute()`, and the `ResponseBuilder::$response` property. Classes stay extensible, and `App::resolve()` stays protected for subclasses.
- The `TheApp\Tests\` namespace moved from `autoload` to `autoload-dev`, so it's no longer added to your project's autoloader.

## [0.7.0] - 2026-10-01

### Fixed

- A middleware that calls the next handler more than once, such as a retry, no longer skips middleware on the later calls. Before, each call removed one middleware from the stack, so the second call left out the middleware after it.
- **Breaking:** route parameters are now URL-decoded, the reverse of `Router::url()`. A request to `/pages/J%C4%81nis` used to give `name` the value `J%C4%81nis`; it now gives `Jānis`. If a handler decodes parameters itself, remove that, or values containing `%` are decoded twice.

## [0.6.0] - 2026-10-01

### Changed

- **Breaking:** `AppFactory::webAppFromContainer()` and `consoleAppFromContainer()` are replaced by `AppFactory::web()` and `AppFactory::console()`. The container argument is optional; without one, a default PHP-DI container is built. It's typed as `DI\Container`, because TheApp requires PHP-DI. To upgrade, rename the calls, as in `AppFactory::web($container)`.

## [0.5.0] - 2026-09-16

This release makes many breaking changes to prepare the API for 1.0. Read "Upgrading from 0.4" first.

### Upgrading from 0.4

1. **Update dependencies.** TheApp now requires PHP-DI 7 (`^7.0.7`). If your project requires PHP-DI 6, upgrade it too.
2. **Set up apps in code instead of config.** TheApp no longer reads the `command.configurators`, `router.configurators`, `router.basePath` or `error_handler` config keys. Remove them from your config, and remove any container definitions that used `CommandRunnerFactory` or `RouterFactory`.

   ```php
   // Console
   AppFactory::consoleAppFromContainer($container)
       ->withCommandConfigurators([UserCommands::class])
       ->run($argv);

   // Web
   $app = AppFactory::webAppFromContainer($container)
       ->withRouterConfigurators([WebRoutes::class])
       ->withErrorHandler(ErrorHandler::class);
   $response = $app->run($request);
   ```

   To set a base path, call `$router->withBasePath('/api')` inside a router configurator.
3. **Add return types to your implementations.** `configureCommands()`, `configureRouter()` and `CommandHandlerInterface::handle()` now return `void`, so implementations must declare `: void`.
4. **Pass the command name as the first argument** (`php console.php user/greet --name=World`). `--command=user/greet` still works. `ConsoleApp::run()` now takes an array, and passing PHP's `$argv` as is works. Options must use `--name=value`; `--name value` is no longer read as an option with a value.
5. **Check boolean command options.** Values are now converted to the parameter type, so `--save=false` gives `false`. It used to give `true`.
6. **Register Whoops yourself if you want its debug page.** `WebApp` no longer registers it. See the README's "Error handling" section.
7. **Give `ResponseBuilder` a response factory.** It now needs a PSR-17 `ResponseFactoryInterface` in the container.
8. **Replace `App::getContainer()`** by injecting the container where you need it.

### Added

- `WebApp::withRouterConfigurators()`, `WebApp::withErrorHandler()` and `ConsoleApp::withCommandConfigurators()`. Each accepts class names or instances and returns a new app.
- `Router::put()`, `patch()`, `delete()`, `options()` and `map()` for several methods. `GET` routes also match `HEAD` requests.
- `MethodNotAllowedException`, thrown when a route matches the path but not the HTTP method. It carries the allowed methods for a 405 response, and extends `NoRouteMatchException`, so existing error handlers still return 404.
- `Router::url()` builds paths from named routes. Handlers get the router as the `Router::class` request attribute.
- Console option values are converted to the callable's parameter types (`bool`, `int`, `float`, `string`), and missing or invalid options are reported by name.
- `ConsoleApp::run()` returns an exit code.
- Support for `psr/http-message` 2.0.
- MIT license, security policy (`SECURITY.md`) and a documented backwards-compatibility promise. Classes marked `@internal` aren't covered by it.

### Changed

- **Breaking:** PHP-DI 7 is required.
- **Breaking:** the framework no longer reads config keys; see "Upgrading from 0.4".
- **Breaking:** `ResponseBuilder` requires a `ResponseFactoryInterface`.
- **Breaking:** `configureCommands()`, `configureRouter()` and `CommandHandlerInterface::handle()` return `void`.
- **Breaking:** `ConsoleApp::run()` takes an array. `ConsoleApp`'s constructor takes a `ConsoleInputParser`, and `CommandRunner`'s constructor no longer takes a container.
- **Breaking:** `Route::$method` is replaced by `Route::$methods`, and `Router::buildRoute()` is protected.
- **Breaking:** `WebApp` no longer registers Whoops, and `filp/whoops` is only suggested.
- `HttpResponseEmitter` throws if headers were already sent, emits headers before the status line, streams bodies in chunks, and emits no body for 204 and 304 responses.
- A route handler class that doesn't implement `RequestHandlerInterface` now throws `InvalidConfigException` instead of a `TypeError`.

### Removed

- **Breaking:** `CommandRunnerFactory`, `RouterFactory` and `ErrorHandlerFactory`.
- **Breaking:** `App::getContainer()`.
- **Breaking:** dependencies on `samejack/php-argv` and `rappasoft/laravel-helpers`. If your code uses the global helper functions from `rappasoft/laravel-helpers`, such as `array_get()`, require that package yourself.
- `.htaccess`, which was shipped in the package.

### Fixed

- `ResponseBuilder` crashed because it used the removed `jasny/http-message` package.
- `router.basePath` had no effect, and `Router::any()` ignored the base path.
- The console app threw a `TypeError` when `--command` was missing or had no value.
- Boolean console options such as `--save=false` were read as `true`.
- A PHP warning inside a handler turned into a 500 response, because Whoops converted it to an exception.

## [0.4.1] - 2025-01-15

### Changed

- Replaced the HTTP response emitter from `jasny/http-message` with the package's own `HttpResponseEmitter`.

[Unreleased]: https://github.com/rkistaps/the-app/compare/v0.9.0...HEAD
[0.9.0]: https://github.com/rkistaps/the-app/compare/v0.8.0...v0.9.0
[0.8.0]: https://github.com/rkistaps/the-app/compare/v0.7.0...v0.8.0
[0.7.0]: https://github.com/rkistaps/the-app/compare/v0.6.0...v0.7.0
[0.6.0]: https://github.com/rkistaps/the-app/compare/v0.5.0...v0.6.0
[0.5.0]: https://github.com/rkistaps/the-app/compare/v0.4.1...v0.5.0
[0.4.1]: https://github.com/rkistaps/the-app/releases/tag/v0.4.1
