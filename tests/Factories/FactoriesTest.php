<?php

namespace TheApp\Tests\Factories;

use DI\Container;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryTestCase;
use Psr\Http\Server\RequestHandlerInterface;
use stdClass;
use TheApp\Apps\ConsoleApp;
use TheApp\Apps\WebApp;
use TheApp\Components\ArrayConfig;
use TheApp\Components\CallableCommandHandler;
use TheApp\Components\CallableRequestHandler;
use TheApp\Exceptions\InvalidConfigException;
use TheApp\Factories\AppFactory;
use TheApp\Factories\CallableFactory;
use TheApp\Factories\CommandHandlerFactory;
use TheApp\Factories\ConfigFactory;
use TheApp\Factories\RequestHandlerFactory;
use TheApp\Interfaces\CommandHandlerInterface;
use TheApp\Structures\Command;
use TheApp\Structures\Route;

class FactoriesTest extends MockeryTestCase
{
    private Container $container;

    protected function setUp(): void
    {
        $this->container = new Container();
    }

    public function testAppFactoryBuildsAppsFromContainer()
    {
        $this->assertInstanceOf(WebApp::class, AppFactory::webAppFromContainer($this->container));
        $this->assertInstanceOf(ConsoleApp::class, AppFactory::consoleAppFromContainer($this->container));
    }

    public function testConfigFactoryBuildsArrayConfig()
    {
        $config = (new ConfigFactory())->fromArray(['app' => ['name' => 'demo']]);

        $this->assertInstanceOf(ArrayConfig::class, $config);
        $this->assertSame('demo', $config->get('app.name'));
    }

    public function testCallableFactory()
    {
        $closure = fn() => 'result';
        $this->container->set('callableService', fn() => new class {
            public function __invoke(): string
            {
                return 'invoked';
            }
        });
        $this->container->set('plainService', new stdClass());

        $factory = new CallableFactory($this->container);

        $this->assertSame($closure, $factory->getCallable($closure));
        $this->assertSame('invoked', ($factory->getCallable('callableService'))());
        $this->assertNull($factory->getCallable('plainService'));
        $this->assertNull($factory->getCallable('missingService'));
        $this->assertNull($factory->getCallable(42));
    }

    public function testRequestHandlerFactoryWrapsCallables()
    {
        $factory = new RequestHandlerFactory($this->container);

        $this->assertInstanceOf(CallableRequestHandler::class, $factory->fromRoute($this->route(fn() => null)));
        $this->assertInstanceOf(CallableRequestHandler::class, $factory->getCallableRequestHandler(fn() => null));
    }

    public function testRequestHandlerFactoryResolvesClassNames()
    {
        $handler = Mockery::mock(RequestHandlerInterface::class);
        $this->container->set('homeHandler', $handler);

        $factory = new RequestHandlerFactory($this->container);

        $this->assertSame($handler, $factory->fromRoute($this->route('homeHandler')));
        $this->assertSame($handler, $factory->getHandlerInstance('homeHandler'));
    }

    public function testRequestHandlerFactoryRejectsWrongClass()
    {
        $this->container->set('notAHandler', new stdClass());

        $this->expectException(InvalidConfigException::class);
        $this->expectExceptionMessage('stdClass does not implement ' . RequestHandlerInterface::class);

        (new RequestHandlerFactory($this->container))->fromRoute($this->route('notAHandler'));
    }

    public function testCommandHandlerFactory()
    {
        $handler = Mockery::mock(CommandHandlerInterface::class);
        $this->container->set('importCommand', $handler);
        $factory = new CommandHandlerFactory($this->container);

        $this->assertInstanceOf(CallableCommandHandler::class, $factory->fromCommand($this->command(fn() => null)));
        $this->assertSame($handler, $factory->fromCommand($this->command('importCommand')));
    }

    public function testCommandHandlerFactoryRejectsWrongClass()
    {
        $this->container->set('notACommand', 'a string');

        $this->expectException(InvalidConfigException::class);
        $this->expectExceptionMessage('string does not implement ' . CommandHandlerInterface::class);

        (new CommandHandlerFactory($this->container))->fromCommand($this->command('notACommand'));
    }

    private function route(callable|string $handler): Route
    {
        $route = new Route();
        $route->path = '/';
        $route->handler = $handler;

        return $route;
    }

    private function command(callable|string $handler): Command
    {
        $command = new Command();
        $command->name = 'test';
        $command->handler = $handler;

        return $command;
    }
}
