<?php

namespace TheApp\Components;

use TheApp\Responses\SimpleResponse;
use Throwable;

/**
 * Class DefaultErrorHandler
 * @package TheApp\Components
 */
class DefaultErrorHandler
{
    /**
     * @param Throwable $throwable
     * @return SimpleResponse
     */
    public function __invoke(Throwable $throwable)
    {
        return new SimpleResponse($throwable->getTraceAsString());
    }
}