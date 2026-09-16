<?php

namespace TheApp\Components;

/*
 * Replace PHP's header functions inside the emitter's namespace, so tests can record headers.
 * Unqualified calls in TheApp\Components resolve to these functions before the global ones.
 */

function header(string $header, bool $replace = true, int $responseCode = 0): void
{
    \TheApp\Tests\Components\HttpResponseEmitterTest::$headers[] = [$header, $replace, $responseCode];
}

function headers_sent(&$file = null, &$line = null): bool
{
    $file = 'index.php';
    $line = 12;

    return \TheApp\Tests\Components\HttpResponseEmitterTest::$headersSent;
}

namespace TheApp\Tests\Components;

use Mockery;
use Mockery\Adapter\Phpunit\MockeryTestCase;
use Mockery\MockInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use RuntimeException;
use TheApp\Components\HttpResponseEmitter;

class HttpResponseEmitterTest extends MockeryTestCase
{
    /** @var array<array{string, bool, int}> */
    public static array $headers = [];
    public static bool $headersSent = false;

    protected function setUp(): void
    {
        self::$headers = [];
        self::$headersSent = false;
    }

    public function testEmitsHeadersBeforeStatusLine()
    {
        $response = $this->response(302, 'Found', ['Location' => ['/login']]);

        $this->expectOutputString('');
        (new HttpResponseEmitter())->emit($response);

        $this->assertSame([
            ['Location: /login', true, 0],
            ['HTTP/1.1 302 Found', true, 302],
        ], self::$headers);
    }

    public function testRepeatedHeaderValuesAreAppended()
    {
        $response = $this->response(200, 'OK', [
            'Set-Cookie' => ['a=1', 'b=2'],
            'Cache-Control' => ['no-cache', 'no-store'],
        ], 'body');

        $this->expectOutputString('body');
        (new HttpResponseEmitter())->emit($response);

        $this->assertSame([
            ['Set-Cookie: a=1', false, 0],
            ['Set-Cookie: b=2', false, 0],
            ['Cache-Control: no-cache', true, 0],
            ['Cache-Control: no-store', false, 0],
            ['HTTP/1.1 200 OK', true, 200],
        ], self::$headers);
    }

    public function testEmptyReasonPhraseLeavesNoTrailingSpace()
    {
        $response = $this->response(299, '', []);

        $this->expectOutputString('');
        (new HttpResponseEmitter())->emit($response);

        $this->assertSame(['HTTP/1.1 299', true, 299], self::$headers[0]);
    }

    public function testThrowsWhenHeadersWereAlreadySent()
    {
        self::$headersSent = true;

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('headers already sent in index.php on line 12');

        (new HttpResponseEmitter())->emit(Mockery::mock(ResponseInterface::class));
    }

    public function testBodyIsRewoundAndEmittedInChunks()
    {
        $stream = Mockery::mock(StreamInterface::class);
        $stream->shouldReceive('isSeekable')->andReturn(true);
        $stream->shouldReceive('rewind')->once()->ordered();
        $stream->shouldReceive('isReadable')->andReturn(true);
        $stream->shouldReceive('eof')->andReturn(false, false, false, true);
        $stream->shouldReceive('read')->with(4)->times(3)->andReturn('abcd', 'efgh', 'ij');

        $response = $this->response(200, 'OK', [], null);
        $response->shouldReceive('getBody')->andReturn($stream);

        $this->expectOutputString('abcdefghij');
        (new HttpResponseEmitter(4))->emit($response);
    }

    public function testUnreadableBodyIsEmittedAsString()
    {
        $stream = Mockery::mock(StreamInterface::class);
        $stream->shouldReceive('isSeekable')->andReturn(false);
        $stream->shouldReceive('isReadable')->andReturn(false);
        $stream->shouldReceive('__toString')->andReturn('content');

        $response = $this->response(200, 'OK', [], null);
        $response->shouldReceive('getBody')->andReturn($stream);

        $this->expectOutputString('content');
        (new HttpResponseEmitter())->emit($response);
    }

    public function testNoBodyForNoContentAndNotModified()
    {
        foreach ([204, 304] as $status) {
            $response = $this->response($status, '', [], null);
            $response->shouldNotReceive('getBody');

            (new HttpResponseEmitter())->emit($response);
        }

        $this->expectOutputString('');
    }

    /**
     * @param array<string, string[]> $headers
     */
    private function response(int $status, string $reason, array $headers, ?string $body = ''): ResponseInterface&MockInterface
    {
        $response = Mockery::mock(ResponseInterface::class);
        $response->shouldReceive('getStatusCode')->andReturn($status);
        $response->shouldReceive('getReasonPhrase')->andReturn($reason);
        $response->shouldReceive('getProtocolVersion')->andReturn('1.1');
        $response->shouldReceive('getHeaders')->andReturn($headers);

        if ($body !== null) {
            $stream = Mockery::mock(StreamInterface::class);
            $stream->shouldReceive('isSeekable')->andReturn(false);
            $stream->shouldReceive('isReadable')->andReturn(true);
            $stream->shouldReceive('eof')->andReturn($body === '', true);
            $stream->shouldReceive('read')->andReturn($body);
            $response->shouldReceive('getBody')->andReturn($stream);
        }

        return $response;
    }
}
