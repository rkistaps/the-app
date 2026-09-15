<?php

namespace TheApp\Tests\Apps;

use DI\Container;
use Mockery\Adapter\Phpunit\MockeryTestCase;
use samejack\PHP\ArgvParser;
use stdClass;
use TheApp\Apps\ConsoleApp;
use TheApp\Components\CommandRunner;
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
        $commandRunner = new CommandRunner($this->container, new CommandHandlerFactory($this->container));

        $this->app = new ConsoleApp($commandRunner, new ArgvParser(), $this->container);
    }

    public function testRunWithoutCommandArgument()
    {
        $this->expectOutputString('Command not found' . PHP_EOL);
        $this->app->run([]);
    }

    public function testRunWithValuelessCommandArgument()
    {
        $this->expectOutputString('Command not found' . PHP_EOL);
        $this->app->run(['--command']);
    }

    public function testWithCommandConfiguratorsAcceptsInstancesAndClassNames()
    {
        $this->container->set('greetCommands', $this->configurator('greet'));

        $app = $this->app->withCommandConfigurators([
            $this->configurator('hello'),
            'greetCommands',
        ]);

        $this->expectOutputString('hello World' . PHP_EOL . 'greet World' . PHP_EOL);
        $app->run(['--command=hello', '--name=World']);
        $app->run(['--command=greet', '--name=World']);
    }

    public function testWithCommandConfiguratorsReturnsNewApp()
    {
        $app = $this->app->withCommandConfigurators([$this->configurator('hello')]);

        $this->assertNotSame($this->app, $app);

        $this->expectOutputString('Command not found' . PHP_EOL . 'hello World' . PHP_EOL);
        $this->app->run(['--command=hello', '--name=World']);
        $app->run(['--command=hello', '--name=World']);
    }

    public function testInvalidConfiguratorThrows()
    {
        $this->container->set('notAConfigurator', new stdClass());
        $app = $this->app->withCommandConfigurators(['notAConfigurator']);

        $this->expectException(InvalidConfigException::class);
        $app->run(['--command=hello']);
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
