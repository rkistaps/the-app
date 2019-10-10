<?php

namespace TheApp\Responses;

use JsonSerializable;
use TheApp\Interfaces\ResponseInterface;

class JsonResponse implements ResponseInterface
{
    /** @var string */
    private $contentType = 'application/json';

    /** @var string|JsonSerializable */
    private $content = '';

    /**
     * JsonResponse constructor.
     * @param $content
     * @param string $contentType
     */
    public function __construct($content, $contentType = 'application/json')
    {
        $this->content = $content;

        $this->contentType = $contentType;
    }

    /**
     * Perform response
     */
    public function respond()
    {
        header("Content-Type: " . $this->contentType);
        echo json_encode($this->content);
    }
}