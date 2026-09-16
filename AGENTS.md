# AGENTS.md

TheApp (`rkistaps/the-app`) is a small PHP micro-framework library. It routes PSR-7 requests through a PSR-15 middleware stack to request handlers, and dispatches console commands. It's consumed as a Composer package, so there is no application entry point (`public/index.php`) in this repo.

## Stack

- PHP `^8.3`
- PHP-DI 6 for the container and autowiring (`DI\Container`, `$container->call()`)
- PSR-7 / PSR-15 / PSR-17 interfaces only. The repo ships no concrete request/response implementation, so consumers bind one. For example, `ResponseBuilder` needs a `Psr\Http\Message\ResponseFactoryInterface` in the container.
- No other runtime dependencies. Console arguments are parsed by the package's own `ConsoleInputParser`, and the framework registers no global error handlers (`filp/whoops` is only suggested for development)
- Tests: PHPUnit 12 + Mockery
- Static analysis: PHPStan 2 at level 8

## Commands

PHP isn't installed on the host. Everything runs in the `theapp_workspace` Docker container (PHP 8.3 CLI, Composer, pcov), with the repo mounted at `/var/www/html`. Run the scripts from Git Bash.

```bash
./docker start | stop | restart | build | ssh
./docker-run composer install          # any command, run inside the container
./docker-test                          # ./vendor/bin/phpunit tests/
./docker-test --filter RouterTest tests/
./docker-coverage                      # text summary + coverage/html/index.html
./docker-run ./vendor/bin/phpstan analyse
```

CI (`.github/workflows/ci.yml`) runs PHPUnit on PHP 8.3, 8.4 and 8.5 with both the lowest and highest dependency versions allowed by `composer.json`, plus PHPStan. When you change a version constraint, make sure its lowest version actually works.

There's no `phpunit.xml` or linter. Docker and other dev-only files are excluded from the Composer package through `export-ignore` in `.gitattributes`. Add any new dev-only root file there too.

## PHPStan rules

See `phpstan.neon`. The rules match the-trader.

- Level 8, not max. Don't add `assert()` or `@var` narrowing just to use `mixed` config or container values.
- Plain `array` is fine. Add `array<...>` docblocks only where they help.
- Defensive `?? default` fallbacks are allowed.
- When PHPStan calls a check "always true/false" because of a docblock, verify the docblock against runtime behaviour before deleting the check.
- `stubs/PhpDi.stub` narrows `DI\Container::get()` and `make()` to the requested class.
- Known errors live in `phpstan-baseline.neon`. Don't hide new errors with `@phpstan-ignore` or by adding them to the baseline. After fixing a baselined error, regenerate the baseline (the command is at the top of `phpstan.neon`), because PHPStan fails on stale entries.

## Layout

Namespace `TheApp\` maps to `src/` and `TheApp\Tests\` maps to `tests/` (PSR-4).

| Directory | Contents |
| --- | --- |
| `src/Apps` | `App` (base: container, `resolve()` helper), `WebApp`, `ConsoleApp` |
| `src/Components` | Runtime pieces: `Router`, `RouteRepository`, `MiddlewareStack`, `RouteHandler`, `CommandRunner`, `HttpResponseEmitter`, and `Callable*` adapters. `ArrayConfig` is a standalone helper that the framework doesn't read |
| `src/Factories` | Build components from the container |
| `src/Interfaces` | Extension points (`RouterConfiguratorInterface`, `CommandConfiguratorInterface`, `ErrorHandlerInterface`, and others) |
| `src/Structures` | Plain data objects with public properties (`Route`, `Command`, `RouteMatchResult`) |
| `src/Exceptions` | `InvalidConfigException`, `NoRouteMatchException`, `MethodNotAllowedException` (extends `NoRouteMatchException`) |
| `tests` | Mirrors `src/` (for example `tests/Components/RouterTest.php`) |

## How it works

**App setup is code, not config.** The framework never reads a config file. Apps are set up with immutable `with*` methods: `WebApp::withRouterConfigurators(array)`, `WebApp::withErrorHandler()` and `ConsoleApp::withCommandConfigurators(array)`. Each accepts class names or instances. `App::resolve()` resolves class names from the container when the app runs, and throws `InvalidConfigException` for the wrong type. Each app builds its own `Router` (with a fresh `RouteRepository`) or clones its `CommandRunner` on first use, and `__clone` resets that cache, so apps returned by `with*` never share routes or commands.

**Web request flow.** `AppFactory::webAppFromContainer()` returns a `WebApp`. `WebApp::run($request, ?$router)` then does the following:

1. It uses the router passed in, or builds one from the configurators. `Router::getRouteHandler()` asks `RouteRepository::matchRoute()` for a match. If nothing matches, it throws `NoRouteMatchException`, or `MethodNotAllowedException` when only the method differs (`RouteRepository::findAllowedMethods()`).
2. The matched route's handler and middlewares are resolved. A callable is wrapped in `CallableRequestHandler` or `CallableMiddleware`. A class name is fetched from the container.
3. `MiddlewareStackFactory` builds a `MiddlewareStack`. Route parameters are added as request attributes, and the stack handles the request.
4. Any `Throwable` goes to the handler from `withErrorHandler()`. Without one, the exception is rethrown.

`HttpResponseEmitter` sends the resulting response.

**Route paths** (the matching logic is adapted from AltoRouter):
- `*` matches any path.
- A path starting with `@` is a raw regex, with the `@` stripped.
- `[type:name]` defines a parameter, and a trailing `?` makes it optional. The types are `i` (int), `a` (alphanumeric), `h` (hex), `*`, `**`, and empty (a single segment).

**Console.** `ConsoleApp::run(array $argv): int` parses `$argv` with `ConsoleInputParser` (index 0 is the script name; the command is the first positional argument or `--command=`; options are `--name=value` or `--flag`). It looks the command up in `CommandRunner` and returns an exit code. `CallableCommandHandler` converts option values to the callable's scalar parameter types and throws `InvalidCommandInputException` for missing or invalid options, which `ConsoleApp` prints. Class handlers get the raw options in `handle(array $params)`.

## Conventions

- Resolve dependencies through the container or constructor injection. Don't use `new` for services. Handlers and middlewares may be a class name or a callable, so support both when adding new extension points.
- Don't make the framework read config keys. New setup options are `with*` methods on the app that accept a class name or an instance and resolve it with `App::resolve()`, which checks the type.
- `Router::withBasePath()` is immutable and returns a clone. Keep "with" methods immutable.
- Style is PSR-12. Newer code uses typed properties and constructor property promotion (see `Router`), while older code declares properties explicitly. Match the file you're editing, and prefer typed signatures in new code.
- Tests extend `Mockery\Adapter\Phpunit\MockeryTestCase`, mock collaborators with `Mockery::mock()`, and use `testMethodName` naming.
- Keep the package framework-agnostic. Depend on PSR interfaces, not on a specific PSR-7 implementation.
