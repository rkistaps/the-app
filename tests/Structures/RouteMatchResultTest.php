<?php

namespace TheApp\Tests\Structures;

use Mockery\Adapter\Phpunit\MockeryTestCase;
use TheApp\Structures\Route;
use TheApp\Structures\RouteMatchResult;

class RouteMatchResultTest extends MockeryTestCase
{
    public function testGettersAndSetters()
    {
        $route = new Route();
        $other = new Route();

        $result = new RouteMatchResult($route, ['id' => '5']);

        $this->assertSame($route, $result->getRoute());
        $this->assertSame(['id' => '5'], $result->getParameters());

        $this->assertSame($result, $result->setRoute($other)->setParameters(['slug' => 'a']));
        $this->assertSame($other, $result->getRoute());
        $this->assertSame(['slug' => 'a'], $result->getParameters());
    }
}
