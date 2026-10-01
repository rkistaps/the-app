<?php

declare(strict_types=1);

namespace TheApp\Tests\Apps;

use DI\Container;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryTestCase;
use RuntimeException;
use stdClass;
use TheApp\Apps\ConsoleApp;
use TheApp\Components\CommandRunner;
use TheApp\Components\ConsoleInputParser;
use TheApp\Exceptions\InvalidConfigException;
use TheApp\Factories\CommandHandlerFactory;
use TheApp\Interfaces\CommandConfiguratorInterface;
use TheApp\Interfaces\CommandHandlerInterface;
use TheApp\Interfaces\ConsoleErrorHandlerInterface;

class ConsoleAppTest extends MockeryTestCase
{
    private Container $container;
    private ConsoleApp $app;

    protected function setUp(): void
    {
        $this->container = new Container();
        $commandRunner = new CommandRunner(new CommandHandlerFactory($this->container));

        $this->app = new ConsoleApp($commandRunner, new ConsoleInputParser(), $this->container);
    }

    public function testRunWithoutCommand()
    {
        $this->expectOutputString('Command not found' . PHP_EOL);
        $this->assertSame(1, $this->app->run(['console.php']));
    }

    public function testRunWithValuelessCommandOption()
    {
        $this->expectOutputString('Command not found' . PHP_EOL);
        $this->assertSame(1, $this->app->run(['console.php', '--command']));
    }

    public function testRunCommandFromFirstArgument()
    {
        $app = $this->app->withCommandConfigurators([$this->configurator('hello')]);

        $this->expectOutputString('hello World' . PHP_EOL);
        $this->assertSame(0, $app->run(['console.php', 'hello', '--name=World']));
    }

    public function testRunCommandFromCommandOption()
    {
        $app = $this->app->withCommandConfigurators([$this->configurator('hello')]);

        $this->expectOutputString('hello World' . PHP_EOL);
        $this->assertSame(0, $app->run(['console.php', '--command=hello', '--name=World']));
    }

    public function testInvalidInputPrintsMessageAndFails()
    {
        $app = $this->app->withCommandConfigurators([$this->configurator('hello')]);

        $this->expectOutputString('Missing required option --name' . PHP_EOL);
        $this->assertSame(1, $app->run(['console.php', 'hello']));
    }

    public function testWithCommandConfiguratorsAcceptsInstancesAndClassNames()
    {
        $this->container->set('greetCommands', $this->configurator('greet'));

        $app = $this->app->withCommandConfigurators([
            $this->configurator('hello'),
            'greetCommands',
        ]);

        $this->expectOutputString('hello World' . PHP_EOL . 'greet World' . PHP_EOL);
        $app->run(['console.php', 'hello', '--name=World']);
        $app->run(['console.php', 'greet', '--name=World']);
    }

    public function testWithCommandConfiguratorsReturnsNewApp()
    {
        $app = $this->app->withCommandConfigurators([$this->configurator('hello')]);

        $this->assertNotSame($this->app, $app);

        $this->expectOutputString('Command not found' . PHP_EOL . 'hello World' . PHP_EOL);
        $this->app->run(['console.php', 'hello', '--name=World']);
        $app->run(['console.php', 'hello', '--name=World']);
    }

    public function testRunReturnsCommandExitCode()
    {
        $this->container->set('failingCommand', new class implements CommandHandlerInterface {
            public function handle(array $params = []): int
            {
                return 3;
            }
        });
        $app = $this->app->withCommandConfigurators([$this->commands(function (CommandRunner $commands) {
            $commands->addCommand('class', 'failingCommand');
            $commands->addCommand('callable', fn() => 4);
        })]);

        $this->assertSame(3, $app->run(['console.php', 'class']));
        $this->assertSame(4, $app->run(['console.php', 'callable']));
    }

    public function testExceptionIsRethrownWithoutErrorHandler()
    {
        $app = $this->app->withCommandConfigurators([$this->commands(function (CommandRunner $commands) {
            $commands->addCommand('fail', function () {
                throw new RuntimeException('Import failed');
            });
        })]);

        $this->expectException(RuntimeException::class);
        $app->run(['console.php', 'fail']);
    }

    public function testErrorHandlerGetsExceptionAndArgumentsAndChoosesExitCode()
    {
        $exception = new RuntimeException('Import failed');
        $argv = ['console.php', 'fail', '--file=users.csv'];

        $handler = Mockery::mock(ConsoleErrorHandlerInterface::class);
        $handler->shouldReceive('handle')->once()->with($exception, $argv)->andReturn(2);
        $this->container->set('errorHandler', $handler);

        $app = $this->app
            ->withCommandConfigurators([$this->commands(function (CommandRunner $commands) use ($exception) {
                $commands->addCommand('fail', function () use ($exception) {
                    throw $exception;
                });
            })])
            ->withErrorHandler('errorHandler');

        $this->assertSame(2, $app->run($argv));
    }

    public function testInvalidInputIsNotSentToErrorHandler()
    {
        $handler = Mockery::mock(ConsoleErrorHandlerInterface::class);
        $handler->shouldNotReceive('handle');

        $app = $this->app->withCommandConfigurators([$this->configurator('hello')])->withErrorHandler($handler);

        $this->expectOutputString('Missing required option --name' . PHP_EOL);
        $this->assertSame(1, $app->run(['console.php', 'hello']));
    }

    public function testDuplicateCommandNameThrows()
    {
        $app = $this->app->withCommandConfigurators([$this->configurator('hello'), $this->configurator('hello')]);

        $this->expectException(InvalidConfigException::class);
        $this->expectExceptionMessage('A command named "hello" is already registered');
        $app->run(['console.php', 'hello', '--name=World']);
    }

    public function testInvalidConfiguratorThrows()
    {
        $this->container->set('notAConfigurator', new stdClass());
        $app = $this->app->withCommandConfigurators(['notAConfigurator']);

        $this->expectException(InvalidConfigException::class);
        $app->run(['console.php', 'hello']);
    }

    private function commands(callable $configure): CommandConfiguratorInterface
    {
        return new class ($configure) implements CommandConfiguratorInterface {
            /** @var callable */
            private $configure;

            public function __construct(callable $configure)
            {
                $this->configure = $configure;
            }

            public function configureCommands(CommandRunner $commandRunner): void
            {
                ($this->configure)($commandRunner);
            }
        };
    }

    private function configurator(string $commandName): CommandConfiguratorInterface
    {
        return new class ($commandName) implements CommandConfiguratorInterface {
            public function __construct(private string $commandName)
            {
            }

            public function configureCommands(CommandRunner $commandRunner): void
            {
                $commandName = $this->commandName;
                $commandRunner->addCommand($commandName, function (string $name) use ($commandName) {
                    echo $commandName . ' ' . $name . PHP_EOL;
                });
            }
        };
    }
}
