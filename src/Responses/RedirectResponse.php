<?php

namespace TheApp\Responses;

use TheApp\Interfaces\ResponseInterface;

/**
 * Class RedirectResponse
 * @package TheApp\Responses
 */
class RedirectResponse implements ResponseInterface
{
    /**
     * Redirect status code
     * @var int
     */
    private $statusCode = 302;

    /**
     * Redirect location
     * @var string
     */
    private $location;

    /**
     * RedirectResponse constructor.
     * @param string $location
     * @param int $statusCode
     */
    public function __construct($location, $statusCode = 302)
    {
        $this->location = $location;
        $this->statusCode = $statusCode;
    }

    /**
     * Perform response
     */
    public function respond()
    {
        http_response_code($this->statusCode);
        header('location: ' . $this->location);

    }
}
