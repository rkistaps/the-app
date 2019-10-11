<?php

namespace rkistaps\Handlers\Demo;

use League\Plates\Engine;

/**
 * Class DemoHandler
 * @package rkistaps\Handlers\Demo
 */
class DemoHandler
{
    /** @var Engine */
    private $template;

    /**
     * DemoHandler constructor.
     * @param Engine $template
     */
    public function __construct(Engine $template)
    {
        $this->template = $template;
    }

    /**
     * @return string
     */
    public function __invoke()
    {
        return $this->template->render('Demo/test', [
            'title' => 'Demo',
        ]);
    }
}
