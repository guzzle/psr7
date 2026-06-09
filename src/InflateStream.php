<?php

declare(strict_types=1);

namespace GuzzleHttp\Psr7;

use GuzzleHttp\Psr7\Exception\TimeoutException;
use Psr\Http\Message\StreamInterface;

/**
 * Uses PHP's zlib.inflate filter to inflate zlib (HTTP deflate, RFC1950) or gzipped (RFC1952) content.
 *
 * This stream decorator converts the provided stream to a PHP stream resource,
 * then appends the zlib.inflate filter. The stream is then converted back
 * to a Guzzle stream resource to be used as a Guzzle stream.
 *
 * @see https://datatracker.ietf.org/doc/html/rfc1950
 * @see https://datatracker.ietf.org/doc/html/rfc1952
 * @see https://www.php.net/manual/en/filters.compression.php
 */
final class InflateStream implements StreamInterface
{
    use StreamDecoratorTrait;
    use NonSerializableStreamTrait;

    private StreamInterface $stream;

    private ?StreamInterface $source;

    public function __construct(StreamInterface $stream)
    {
        $this->source = $stream;
        $resource = StreamWrapper::getResource($stream);
        // Specify window=15+32, so zlib will use header detection to both gzip (with header) and zlib data
        // See https://www.zlib.net/manual.html#Advanced definition of inflateInit2
        // "Add 32 to windowBits to enable zlib and gzip decoding with automatic header detection"
        // Default window size is 15.
        stream_filter_append($resource, 'zlib.inflate', STREAM_FILTER_READ, ['window' => 15 + 32]);
        $this->stream = $stream->isSeekable() ? new Stream($resource) : new NoSeekStream(new Stream($resource));
    }

    public function read(int $length): string
    {
        if ($length <= 0 || $this->source === null) {
            return $this->stream->read($length);
        }

        try {
            $data = $this->stream->read($length);
        } catch (TimeoutException $e) {
            throw $e;
        } catch (\RuntimeException $e) {
            if (StreamTimeout::isReadTimedOut($this->source)) {
                throw new TimeoutException('Unable to read from stream: timed out', 0, $e);
            }

            throw $e;
        }

        if ($data === '' && StreamTimeout::isReadTimedOut($this->source)) {
            throw new TimeoutException('Unable to read from stream: timed out');
        }

        return $data;
    }

    public function close(): void
    {
        $source = $this->source;
        $this->source = null;

        $exception = null;

        try {
            $this->stream->close();
        } catch (\Throwable $e) {
            $exception = $e;
        }

        if ($source !== null) {
            try {
                $source->close();
            } catch (\Throwable $e) {
                if ($exception === null) {
                    $exception = $e;
                }
            }
        }

        if ($exception !== null) {
            throw $exception;
        }
    }

    public function detach()
    {
        $this->source = null;

        return $this->stream->detach();
    }
}
