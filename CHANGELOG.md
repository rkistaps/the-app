# Changelog

All notable changes to this project are documented here. The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and the project uses [semantic versioning](https://semver.org/).

## [Unreleased]

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

[Unreleased]: https://github.com/rkistaps/the-app/compare/v0.4.1...HEAD
[0.4.1]: https://github.com/rkistaps/the-app/releases/tag/v0.4.1
