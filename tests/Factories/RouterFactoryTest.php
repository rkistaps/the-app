<?php

namespace TheApp\Tests\Factories;

use DI\Container;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryTestCase;
use Psr\Container\ContainerInterface;
use TheApp\Components\ArrayConfig;
use TheApp\Components\Repositories\RouteRepository;
use TheApp\Components\Router;
use TheApp\Factories\RequestHandlerFactory;
use TheApp\Factories\RouterFactory;

class RouterFactoryTest extends MockeryTestCase
{
    public function testBuildFromConfigAppliesBasePath()
    {
        $router = new Router(
            Mockery::mock(RouteRepository::class),
            Mockery::mock(RequestHandlerFactory::class),
            Mockery::mock(Container::class)
        );

        $container = Mockery::mock(ContainerInterface::class);
        $container->shouldReceive('get')->with(Router::class)->andReturn($router);

        $factory = new RouterFactory($container);
        $result = $factory->buildFromConfig(new ArrayConfig(['router' => ['basePath' => '/api']]));

        $this->assertInstanceOf(Router::class, $result);
        $this->assertEquals('/api', $result->getBasePath());
    }
}
