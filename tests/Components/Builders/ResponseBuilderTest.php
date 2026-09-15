<?php

namespace TheApp\Tests\Components\Builders;

use Mockery;
use Mockery\Adapter\Phpunit\MockeryTestCase;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use TheApp\Components\Builders\ResponseBuilder;

class ResponseBuilderTest extends MockeryTestCase
{
    public function testWithRedirect()
    {
        $initial = Mockery::mock(ResponseInterface::class);
        $withStatus = Mockery::mock(ResponseInterface::class);
        $withHeader = Mockery::mock(ResponseInterface::class);

        $initial->shouldReceive('withStatus')->with(302)->andReturn($withStatus);
        $withStatus->shouldReceive('withHeader')->with('location', '/login')->andReturn($withHeader);

        $factory = Mockery::mock(ResponseFactoryInterface::class);
        $factory->shouldReceive('createResponse')->once()->andReturn($initial);

        $response = (new ResponseBuilder($factory))->withRedirect('/login', 302)->build();

        $this->assertSame($withHeader, $response);
    }
}
