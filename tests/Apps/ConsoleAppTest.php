<?php

namespace TheApp\Tests\Apps;

use Mockery;
use Mockery\Adapter\Phpunit\MockeryTestCase;
use Psr\Container\ContainerInterface;
use samejack\PHP\ArgvParser;
use TheApp\Apps\ConsoleApp;
use TheApp\Components\CommandRunner;

class ConsoleAppTest extends MockeryTestCase
{
    public function testRunWithoutCommandArgument()
    {
        $runner = Mockery::mock(CommandRunner::class);
        $runner->shouldNotReceive('findCommandByName');

        $app = new ConsoleApp($runner, new ArgvParser(), Mockery::mock(ContainerInterface::class));

        $this->expectOutputString('Command not found' . PHP_EOL);
        $app->run([]);
    }

    public function testRunWithValuelessCommandArgument()
    {
        $runner = Mockery::mock(CommandRunner::class);
        $runner->shouldNotReceive('findCommandByName');

        $app = new ConsoleApp($runner, new ArgvParser(), Mockery::mock(ContainerInterface::class));

        $this->expectOutputString('Command not found' . PHP_EOL);
        $app->run(['--command']);
    }
}
