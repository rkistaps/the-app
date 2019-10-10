<?php

namespace rkistaps\Handlers\Demo;

/**
 * Class DemoHandler
 * @package rkistaps\Handlers\Demo
 */
class DemoHandler
{
    public function __invoke($mar)
    {
        return 'This is it ';
    }
}
