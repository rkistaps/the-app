<?php

declare(strict_types=1);

namespace TheApp\Tests\Structures;

use Mockery\Adapter\Phpunit\MockeryTestCase;
use TheApp\Structures\Route;
use TheApp\Structures\RouteMatchResult;

class RouteMatchResultTest extends MockeryTestCase
{
    public function testGettersAndSetters()
    {
        $route = new Route([Route::METHOD_GET], '/a', 'Handler');
        $other = new Route([Route::METHOD_GET], '/b', 'Handler');

        $result = new RouteMatchResult($route, ['id' => '5']);

        $this->assertSame($route, $result->getRoute());
        $this->assertSame(['id' => '5'], $result->getParameters());

        $this->assertSame($result, $result->setRoute($other)->setParameters(['slug' => 'a']));
        $this->assertSame($other, $result->getRoute());
        $this->assertSame(['slug' => 'a'], $result->getParameters());
    }
}
