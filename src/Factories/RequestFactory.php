<?php

namespace TheApp\Factories;

use TheApp\Components\WebRequest;

/**
 * Class RequestFactory
 * @package TheApp\Factories
 */
class RequestFactory
{
    /**
     * @return WebRequest
     * @throws \Exception
     */
    public function fromGlobals(): WebRequest
    {
        return (new WebRequest())
            ->setGlobals($_GET, $_POST, $_REQUEST, $_SERVER, $_COOKIE, $_FILES, getenv())
            ->init();
    }
}
