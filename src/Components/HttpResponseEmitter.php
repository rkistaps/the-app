<?php

namespace TheApp\Components;

use Psr\Http\Message\ResponseInterface;
use RuntimeException;

class HttpResponseEmitter
{
    /** Status codes whose responses must not include a body */
    private const STATUSES_WITHOUT_BODY = [204, 304];

    public function __construct(private int $chunkSize = 8192)
    {
    }

    /**
     * Emits the HTTP response according to the PSR-7 specification.
     * @throws RuntimeException When headers were already sent
     */
    public function emit(ResponseInterface $response): void
    {
        if (headers_sent($file, $line)) {
            throw new RuntimeException(sprintf('Unable to emit response: headers already sent in %s on line %d', $file, $line));
        }

        // Headers go first: PHP changes the status code when some headers, such as Location, are sent
        $this->emitHeaders($response);
        $this->emitStatusLine($response);

        if (!in_array($response->getStatusCode(), self::STATUSES_WITHOUT_BODY, true)) {
            $this->emitBody($response);
        }
    }

    /**
     * Emits the status line.
     */
    private function emitStatusLine(ResponseInterface $response): void
    {
        $statusCode = $response->getStatusCode();
        $statusLine = rtrim(sprintf(
            'HTTP/%s %d %s',
            $response->getProtocolVersion(),
            $statusCode,
            $response->getReasonPhrase()
        ));

        header($statusLine, true, $statusCode);
    }

    /**
     * Emits the headers. The first value of a header replaces any earlier one, except for Set-Cookie.
     */
    private function emitHeaders(ResponseInterface $response): void
    {
        foreach ($response->getHeaders() as $name => $values) {
            $replace = strtolower((string) $name) !== 'set-cookie';
            foreach ($values as $value) {
                header(sprintf('%s: %s', $name, $value), $replace);
                $replace = false;
            }
        }
    }

    /**
     * Emits the body in chunks, so large bodies aren't loaded into memory at once.
     */
    private function emitBody(ResponseInterface $response): void
    {
        $body = $response->getBody();

        if ($body->isSeekable()) {
            $body->rewind();
        }

        if (!$body->isReadable()) {
            echo $body;
            return;
        }

        while (!$body->eof()) {
            echo $body->read($this->chunkSize);
        }
    }
}
