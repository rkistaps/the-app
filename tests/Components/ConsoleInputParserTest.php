<?php

namespace TheApp\Tests\Components;

use Mockery\Adapter\Phpunit\MockeryTestCase;
use TheApp\Components\ConsoleInputParser;
use TheApp\Exceptions\InvalidCommandInputException;

class ConsoleInputParserTest extends MockeryTestCase
{
    private ConsoleInputParser $parser;

    protected function setUp(): void
    {
        $this->parser = new ConsoleInputParser();
    }

    public function testFirstPositionalArgumentIsCommand()
    {
        $input = $this->parser->parse(['console.php', 'user/greet', '--name=World']);

        $this->assertSame('user/greet', $input->command);
        $this->assertSame(['name' => 'World'], $input->options);
    }

    public function testScriptNameIsIgnored()
    {
        $input = $this->parser->parse(['console.php']);

        $this->assertNull($input->command);
        $this->assertSame([], $input->options);
    }

    public function testOptionForms()
    {
        $input = $this->parser->parse([
            'console.php',
            '--verbose',
            '--empty=',
            '--offset=-5',
            '--query=a=b c',
            '-f',
        ]);

        $this->assertSame([
            'verbose' => true,
            'empty' => '',
            'offset' => '-5',
            'query' => 'a=b c',
            'f' => true,
        ], $input->options);
    }

    public function testCommandOption()
    {
        $input = $this->parser->parse(['console.php', '--command=user/greet', '--name=World']);

        $this->assertSame('user/greet', $input->command);
        $this->assertSame(['name' => 'World'], $input->options);
    }

    public function testValuelessCommandOptionSelectsNoCommand()
    {
        $this->assertNull($this->parser->parse(['console.php', '--command'])->command);
        $this->assertNull($this->parser->parse(['console.php', '--command='])->command);
    }

    public function testSecondPositionalArgumentThrows()
    {
        $this->expectException(InvalidCommandInputException::class);
        $this->expectExceptionMessage('Unexpected argument "World"');

        $this->parser->parse(['console.php', 'user/greet', 'World']);
    }

    public function testCommandArgumentAndOptionTogetherThrow()
    {
        $this->expectException(InvalidCommandInputException::class);

        $this->parser->parse(['console.php', 'user/greet', '--command=user/import']);
    }
}
