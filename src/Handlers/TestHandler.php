<?php

namespace TheApp\Handlers;

use TheApp\Components\WebRequest;

/**
 * Class TestHandler
 * @package TheApp\Handlers
 */
class TestHandler
{
    /** @var WebRequest */
    private $request;

    /**
     * TestHandler constructor.
     * @param WebRequest $request
     */
    public function __construct(
        WebRequest $request
    ) {
        $this->request = $request;
    }

    /**
     * Handle request
     * @param int $id
     * @return string
     */
    public function __invoke($id)
    {
        return 'This is test handler: ' . $id;
    }
}