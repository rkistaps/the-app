<?php

namespace TheApp\Tests\Components;

use DI\Container;
use Mockery\Adapter\Phpunit\MockeryTestCase;
use stdClass;
use TheApp\Components\CallableCommandHandler;
use TheApp\Exceptions\InvalidCommandInputException;

class CallableCommandHandlerTest extends MockeryTestCase
{
    private Container $container;

    protected function setUp(): void
    {
        $this->container = new Container();
    }

    public function testBooleanOptions()
    {
        $received = [];
        $handler = $this->handler(function (bool $save = false) use (&$received) {
            $received[] = $save;
        });

        foreach (['false', '0', 'no', 'off', 'FALSE'] as $value) {
            $handler->handle(['save' => $value]);
        }
        foreach (['true', '1', 'yes', 'on', true] as $value) {
            $handler->handle(['save' => $value]);
        }
        $handler->handle([]);

        $this->assertSame([false, false, false, false, false, true, true, true, true, true, false], $received);
    }

    public function testNumericOptions()
    {
        $received = null;
        $handler = $this->handler(function (int $budget, float $fee) use (&$received) {
            $received = [$budget, $fee];
        });

        $handler->handle(['budget' => '-10000', 'fee' => '0.002']);

        $this->assertSame([-10000, 0.002], $received);
    }

    public function testClassTypedParametersAreResolvedFromContainer()
    {
        $service = new stdClass();
        $this->container->set(stdClass::class, $service);

        $received = null;
        $handler = $this->handler(function (stdClass $service, string $name) use (&$received) {
            $received = [$service, $name];
        });

        $handler->handle(['name' => 'World']);

        $this->assertSame([$service, 'World'], $received);
    }

    public function testUnknownOptionsAreIgnored()
    {
        $called = false;
        $handler = $this->handler(function () use (&$called) {
            $called = true;
        });

        $handler->handle(['unused' => 'value']);

        $this->assertTrue($called);
    }

    /**
     * @return iterable<string, array{callable, array<string, string|true>, string}>
     */
    public static function invalidInput(): iterable
    {
        yield 'missing required option' => [fn(string $name) => null, [], 'Missing required option --name'];
        yield 'invalid boolean' => [fn(bool $save) => null, ['save' => 'maybe'], 'Option --save expects true or false, got "maybe"'];
        yield 'invalid integer' => [fn(int $budget) => null, ['budget' => '10.5'], 'Option --budget expects an integer'];
        yield 'integer flag' => [fn(int $budget) => null, ['budget' => true], 'Option --budget expects an integer'];
        yield 'invalid float' => [fn(float $fee) => null, ['fee' => 'abc'], 'Option --fee expects a number'];
        yield 'string flag' => [fn(string $name) => null, ['name' => true], 'Option --name needs a value, as in --name=value'];
    }

    /**
     * @param array<string, string|true> $params
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('invalidInput')]
    public function testInvalidInputThrows(callable $callable, array $params, string $message)
    {
        $this->expectException(InvalidCommandInputException::class);
        $this->expectExceptionMessage($message);

        $this->handler($callable)->handle($params);
    }

    private function handler(callable $callable): CallableCommandHandler
    {
        return new CallableCommandHandler($callable, $this->container);
    }
}
