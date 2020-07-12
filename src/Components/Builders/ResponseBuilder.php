<?php

namespace TheApp\Components\Builders;

use Jasny\HttpMessage\Response;
use Psr\Http\Message\ResponseInterface;

class ResponseBuilder
{
    private ResponseInterface $response;

    public function __construct()
    {
        $this->response = new Response();
    }

    public function withStatus(int $statusCode): ResponseBuilder
    {
        $this->response = $this->response->withStatus($statusCode);

        return $this;
    }

    public function withContent(string $content): ResponseBuilder
    {
        $this->response->getBody()->write($content);

        return $this;
    }

    public function build(): ResponseInterface
    {
        return $this->response;
    }
}

