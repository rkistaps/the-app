<?php

namespace TheApp\Tests\Components;

use Mockery\Adapter\Phpunit\MockeryTestCase;
use TheApp\Components\ArrayConfig;

class ArrayConfigTest extends MockeryTestCase
{
    public function testGetTopLevelKey()
    {
        $config = new ArrayConfig(['name' => 'app']);

        $this->assertSame('app', $config->get('name'));
    }

    public function testGetNestedKeyWithDotNotation()
    {
        $config = new ArrayConfig(['database' => ['connection' => ['host' => 'localhost']]]);

        $this->assertSame('localhost', $config->get('database.connection.host'));
        $this->assertSame(['host' => 'localhost'], $config->get('database.connection'));
    }

    public function testKeyContainingDotTakesPrecedence()
    {
        $config = new ArrayConfig(['cache.driver' => 'redis', 'cache' => ['driver' => 'file']]);

        $this->assertSame('redis', $config->get('cache.driver'));
    }

    public function testNullValueIsReturnedInsteadOfDefault()
    {
        $config = new ArrayConfig(['mail' => ['from' => null]]);

        $this->assertNull($config->get('mail.from', 'fallback'));
    }

    public function testMissingKeyReturnsDefault()
    {
        $config = new ArrayConfig(['database' => ['host' => 'localhost']]);

        $this->assertNull($config->get('missing'));
        $this->assertSame('fallback', $config->get('database.port', 'fallback'));
        $this->assertSame('fallback', $config->get('database.host.name', 'fallback'));
    }

    public function testClosureDefaultIsCalledOnlyWhenKeyIsMissing()
    {
        $config = new ArrayConfig(['name' => 'app']);

        $this->assertSame('computed', $config->get('missing', fn() => 'computed'));
        $this->assertSame('app', $config->get('name', fn() => $this->fail('Default should not be called')));
    }
}
