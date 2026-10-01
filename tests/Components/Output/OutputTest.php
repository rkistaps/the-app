<?php

declare(strict_types=1);

namespace TheApp\Tests\Components\Output;

use Mockery\Adapter\Phpunit\MockeryTestCase;
use TheApp\Components\Output\BufferedOutput;
use TheApp\Components\Output\StreamOutput;

class OutputTest extends MockeryTestCase
{
    public function testStreamOutputWritesToItsStreams()
    {
        $stdout = fopen('php://memory', 'w+');
        $stderr = fopen('php://memory', 'w+');
        $output = new StreamOutput($stdout, $stderr);

        $output->write('a');
        $output->writeln('b');
        $output->writeln();
        $output->error('failed');

        $this->assertSame('ab' . PHP_EOL . PHP_EOL, stream_get_contents($stdout, -1, 0));
        $this->assertSame('failed' . PHP_EOL, stream_get_contents($stderr, -1, 0));
    }

    public function testStreamOutputWritesToTheSameChannelAsEchoByDefault()
    {
        $output = new StreamOutput();

        $this->expectOutputString('one' . PHP_EOL . 'two' . PHP_EOL . 'three' . PHP_EOL);
        $output->writeln('one');
        echo 'two' . PHP_EOL;
        $output->writeln('three');
    }

    public function testBufferedOutputKeepsOutputAndErrorsApart()
    {
        $output = new BufferedOutput();

        $output->write('a');
        $output->writeln('b');
        $output->error('failed');

        $this->assertSame('ab' . PHP_EOL, $output->getOutput());
        $this->assertSame('failed' . PHP_EOL, $output->getErrors());
    }
}
