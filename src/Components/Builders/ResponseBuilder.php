<?php

namespace TheApp\Components\Builders;

use Jasny\HttpMessage\Response;
use Psr\Http\Message\ResponseInterface;

class ResponseBuilder
{
    protected ResponseInterface $response;

    public function __construct()
    {
        $this->response = new Response();
    }

    public function withRedirect(string $location, int $statusCode = 301): ResponseBuilder
    {
        $this->withStatus($statusCode);
        $this->withHeader('location', $location);

        return $this;
    }

    /**
     * @param string $key
     * @param string|string[] $value
     * @return ResponseBuilder
     */
    public function withHeader(string $key, $value): ResponseBuilder
    {
        $this->response = $this->response->withAddedHeader($key, $value);

        return $this;
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

