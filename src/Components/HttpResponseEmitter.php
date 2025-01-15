<?php

namespace TheApp\Components;

use Psr\Http\Message\ResponseInterface;

class HttpResponseEmitter
{
    /**
     * Emits the HTTP response according to the PSR-7 specification.
     */
    public function emit(ResponseInterface $response): void
    {
        // Emit the status line
        $this->emitStatusLine($response);

        // Emit the headers
        $this->emitHeaders($response);

        // Emit the body
        $this->emitBody($response);
    }

    /**
     * Emits the status line.
     */
    private function emitStatusLine(ResponseInterface $response): void
    {
        $protocolVersion = $response->getProtocolVersion();
        $statusCode = $response->getStatusCode();
        $reasonPhrase = $response->getReasonPhrase();

        header(sprintf('HTTP/%s %d %s', $protocolVersion, $statusCode, $reasonPhrase), true, $statusCode);
    }

    /**
     * Emits the headers.
     */
    private function emitHeaders(ResponseInterface $response): void
    {
        foreach ($response->getHeaders() as $name => $values) {
            foreach ($values as $value) {
                header(sprintf('%s: %s', $name, $value), false);
            }
        }
    }

    /**
     * Emits the body.
     */
    private function emitBody(ResponseInterface $response): void
    {
        echo $response->getBody();
    }
}