<?php

namespace rkistaps\Handlers;

use League\Plates\Engine;
use Throwable;

/**
 * Class ErrorHandler
 * @package rkistaps\Handlers
 */
class ErrorHandler
{
    /** @var Engine */
    private $template;

    /**
     * ErrorHandler constructor.
     * @param Engine $template
     */
    public function __construct(Engine $template)
    {
        $this->template = $template;
    }

    /**
     * @param Throwable $throwable
     * @return string
     */
    public function __invoke(Throwable $throwable)
    {
        return $this->template->render('error', [
            'throwable' => $throwable,
        ]);
    }
}
