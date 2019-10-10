<?php

namespace TheApp\Responses;

use TheApp\Interfaces\ResponseInterface;

/**
 * Class SimpleResponse
 * @package TheApp\Responses
 */
class SimpleResponse implements ResponseInterface
{
    /** @var mixed */
    private $content;

    /**
     * SimpleResponse constructor.
     * @param mixed $content
     */
    public function __construct($content)
    {
        $this->content = $content;
    }

    /**
     * Perform response
     */
    public function respond()
    {
        echo $this->content;
    }
}
