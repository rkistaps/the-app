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
use TheApp\Components\Output\BufferedOutput;
use TheApp\Components\Output\StreamOutput;
use TheApp\Exceptions\InvalidConfigException;
use TheApp\Factories\CommandHandlerFactory;
use TheApp\Interfaces\CommandConfiguratorInterface;
use TheApp\Interfaces\CommandHandlerInterface;
use TheApp\Interfaces\ConsoleErrorHandlerInterface;
use TheApp\Interfaces\OutputInterface;
use TheApp\Tests\Fixtures\OutputConstructorCommand;

class ConsoleAppTest extends MockeryTestCase
{
    private Container $container;
    private ConsoleApp $app;
    private BufferedOutput $output;

    protected function setUp(): void
    {
        $this->container = new Container();
        $this->output = new BufferedOutput();
        $commandRunner = new CommandRunner(new CommandHandlerFactory($this->container));

        $this->app = (new ConsoleApp($commandRunner, new ConsoleInputParser(), $this->container))->withOutput($this->output);
    }

    public function testRunWithoutCommand()
    {
        $this->assertSame(1, $this->app->run(['console.php']));
        $this->assertSame('Command not found' . PHP_EOL, $this->output->getErrors());
        $this->assertSame('', $this->output->getOutput());
    }

    public function testRunWithValuelessCommandOption()
    {
        $this->assertSame(1, $this->app->run(['console.php', '--command']));
        $this->assertSame('Command not found' . PHP_EOL, $this->output->getErrors());
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

    public function testInvalidInputWritesErrorAndFails()
    {
        $app = $this->app->withCommandConfigurators([$this->configurator('hello')]);

        $this->assertSame(1, $app->run(['console.php', 'hello']));
        $this->assertSame('Missing required option --name' . PHP_EOL, $this->output->getErrors());
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

        $this->expectOutputString('hello World' . PHP_EOL);
        $this->app->run(['console.php', 'hello', '--name=World']);
        $app->run(['console.php', 'hello', '--name=World']);
        $this->assertSame('Command not found' . PHP_EOL, $this->output->getErrors());
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

        $this->assertSame(1, $app->run(['console.php', 'hello']));
        $this->assertSame('Missing required option --name' . PHP_EOL, $this->output->getErrors());
    }

    public function testCallableCommandGetsOutputAsParameter()
    {
        $app = $this->app->withCommandConfigurators([$this->commands(function (CommandRunner $commands) {
            $commands->addCommand('greet', function (string $name, OutputInterface $output) {
                $output->writeln('Hello, ' . $name);
                $output->error('warning');
            });
        })]);

        $this->assertSame(0, $app->run(['console.php', 'greet', '--name=World']));
        $this->assertSame('Hello, World' . PHP_EOL, $this->output->getOutput());
        $this->assertSame('warning' . PHP_EOL, $this->output->getErrors());
    }

    public function testCommandClassGetsOutputInConstructor()
    {
        $app = $this->app->withCommandConfigurators([$this->commands(function (CommandRunner $commands) {
            $commands->addCommand('report', OutputConstructorCommand::class);
        })]);

        $this->assertSame(0, $app->run(['console.php', 'report', '--title=Daily']));
        $this->assertSame('Daily' . PHP_EOL, $this->output->getOutput());
    }

    public function testContainerOutputIsUsedWithoutWithOutput()
    {
        $containerOutput = new BufferedOutput();
        $this->container->set(OutputInterface::class, $containerOutput);
        $app = new ConsoleApp(new CommandRunner(new CommandHandlerFactory($this->container)), new ConsoleInputParser(), $this->container);

        $app->run(['console.php', 'missing']);

        $this->assertSame('Command not found' . PHP_EOL, $containerOutput->getErrors());
    }

    public function testStreamOutputIsTheDefault()
    {
        $app = new ConsoleApp(new CommandRunner(new CommandHandlerFactory($this->container)), new ConsoleInputParser(), $this->container);
        $app = $app->withCommandConfigurators([$this->commands(function (CommandRunner $commands) {
            $commands->addCommand('hello', fn(OutputInterface $output) => $output->writeln('Hello'));
        })]);

        $this->expectOutputString('Hello' . PHP_EOL);
        $app->run(['console.php', 'hello']);
        $this->assertInstanceOf(StreamOutput::class, $this->container->get(OutputInterface::class));
    }

    public function testInvalidOutputThrows()
    {
        $this->container->set('notAnOutput', new stdClass());

        $this->expectException(InvalidConfigException::class);
        $this->app->withOutput('notAnOutput')->run(['console.php']);
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
