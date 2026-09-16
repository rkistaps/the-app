<?php

namespace TheApp\Tests\Apps;

use DI\Container;
use Mockery\Adapter\Phpunit\MockeryTestCase;
use stdClass;
use TheApp\Apps\ConsoleApp;
use TheApp\Components\CommandRunner;
use TheApp\Components\ConsoleInputParser;
use TheApp\Exceptions\InvalidConfigException;
use TheApp\Factories\CommandHandlerFactory;
use TheApp\Interfaces\CommandConfiguratorInterface;

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

    public function testInvalidConfiguratorThrows()
    {
        $this->container->set('notAConfigurator', new stdClass());
        $app = $this->app->withCommandConfigurators(['notAConfigurator']);

        $this->expectException(InvalidConfigException::class);
        $app->run(['console.php', 'hello']);
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
