<?php

declare(strict_types=1);

namespace GuzzleHttp\Tests\Psr7;

use GuzzleHttp\Psr7;
use GuzzleHttp\Psr7\Exception\TimeoutException;
use GuzzleHttp\Psr7\InflateStream;
use GuzzleHttp\Psr7\NoSeekStream;
use PHPUnit\Framework\TestCase;

/**
 * @requires extension zlib
 */
class InflateStreamTest extends TestCase
{
    public function testInflatesRfc1952Streams(): void
    {
        $content = gzencode('test');
        $a = Psr7\Utils::streamFor($content);
        $b = new InflateStream($a);
        self::assertSame('test', (string) $b);
    }

    public function testInflatesStreamsRfc1952WithFilename(): void
    {
        $content = $this->getGzipStringWithFilename('test');
        $a = Psr7\Utils::streamFor($content);
        $b = new InflateStream($a);
        self::assertSame('test', (string) $b);
    }

    public function testInflatesRfc1950Streams(): void
    {
        $content = gzcompress('test');
        $a = Psr7\Utils::streamFor($content);
        $b = new InflateStream($a);
        self::assertSame('test', (string) $b);
    }

    public function testInflatesRfc1952StreamsWithExtraFlags(): void
    {
        $content = gzdeflate('test'); // RFC 1951. Raw deflate. No header.

        //  +---+---+---+---+---+---+---+---+---+---+
        //  |ID1|ID2|CM |FLG|     MTIME     |XFL|OS | (more-->)
        //  +---+---+---+---+---+---+---+---+---+---+
        $header = "\x1f\x8B\x08";
        // set flags FHCRC, FEXTRA, FNAME and FCOMMENT
        $header .= chr(0b00011110);
        $header .= "\x00\x00\x00\x00"; // MTIME
        $header .= "\x02\x03"; // XFL, OS
        // 4 byte extra data
        $header .= "\x04\x00\x41\x70\x00\x00"; /* XLEN + EXTRA */
        // file name (2 bytes + terminator)
        $header .= "\x41\x70\x00";
        // file comment (3 bytes + terminator)
        $header .= "\x41\x42\x43\x00";

        // crc16
        $header .= pack('v', crc32($header));

        $a = Psr7\Utils::streamFor($header.$content);
        $b = new InflateStream($a);
        self::assertSame('test', (string) $b);
    }

    public function testInflatesStreamsPreserveSeekable(): void
    {
        $content = gzencode('test');
        $seekable = Psr7\Utils::streamFor($content);

        $seekableInflate = new InflateStream($seekable);
        self::assertTrue($seekableInflate->isSeekable());
        self::assertSame('test', (string) $seekableInflate);

        $nonSeekable = new NoSeekStream(Psr7\Utils::streamFor($content));
        $nonSeekableInflate = new InflateStream($nonSeekable);
        self::assertFalse($nonSeekableInflate->isSeekable());
        self::assertSame('test', (string) $nonSeekableInflate);
    }

    public function testReadRejectsNegativeLength(): void
    {
        $stream = new InflateStream(Psr7\Utils::streamFor(gzencode('test')));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Length parameter cannot be negative');

        $stream->read(-1);
    }

    public function testReadSurfacesSourceReadTimeout(): void
    {
        $source = new Psr7\FnStream([
            'isReadable' => static fn (): bool => true,
            'isWritable' => static fn (): bool => false,
            'isSeekable' => static fn (): bool => false,
            'eof' => static fn (): bool => false,
            'read' => static function (int $length): string {
                throw new TimeoutException('Unable to read from stream: timed out');
            },
            'getMetadata' => static function (?string $key = null) {
                return $key === 'timed_out' ? true : null;
            },
        ]);

        $stream = new InflateStream($source);

        $this->expectException(TimeoutException::class);
        $this->expectExceptionMessage('Unable to read from stream: timed out');

        $stream->read(1024);
    }

    public function testReadReturnsEmptyStringOnCleanEofWithoutTimeout(): void
    {
        $stream = new InflateStream(Psr7\Utils::streamFor(gzencode('test')));

        self::assertSame('test', (string) $stream);
        self::assertSame('', $stream->read(1024));
    }

    public function testCloseClosesSourceStream(): void
    {
        $resource = Psr7\Utils::tryFopen('php://temp', 'r+');
        fwrite($resource, gzencode('test'));
        rewind($resource);

        $stream = new InflateStream(Psr7\Utils::streamFor($resource));

        $stream->close();

        self::assertFalse(is_resource($resource));
    }

    public function testCloseClosesSourceStreamOnlyOnce(): void
    {
        $closed = 0;

        $source = new Psr7\FnStream([
            'isReadable' => static fn (): bool => true,
            'isWritable' => static fn (): bool => false,
            'isSeekable' => static fn (): bool => false,
            'eof' => static fn (): bool => true,
            'read' => static fn (int $length): string => '',
            'close' => static function () use (&$closed): void {
                ++$closed;
            },
        ]);

        $stream = new InflateStream($source);

        $stream->close();
        $stream->close();

        self::assertSame(1, $closed);
    }

    public function testDetachDoesNotCloseOrDetachSourceStream(): void
    {
        $closed = 0;
        $detached = 0;
        $source = new Psr7\FnStream([
            'isReadable' => static fn (): bool => true,
            'isWritable' => static fn (): bool => false,
            'isSeekable' => static fn (): bool => false,
            'eof' => static fn (): bool => true,
            'read' => static fn (int $length): string => '',
            'close' => static function () use (&$closed): void {
                ++$closed;
            },
            'detach' => static function () use (&$detached) {
                ++$detached;

                return null;
            },
        ]);

        $stream = new InflateStream($source);
        $resource = $stream->detach();

        try {
            self::assertIsResource($resource);
            self::assertSame(0, $closed);
            self::assertSame(0, $detached);
        } finally {
            if (is_resource($resource)) {
                fclose($resource);
            }

            $source->close();
        }
    }

    public function testReadSurfacesPartialGzipSourceReadTimeout(): void
    {
        $compressed = gzencode(str_repeat('A', 100000));
        $offset = 0;
        $timedOut = false;

        $source = new Psr7\FnStream([
            'isReadable' => static fn (): bool => true,
            'isWritable' => static fn (): bool => false,
            'isSeekable' => static fn (): bool => false,
            'eof' => static fn (): bool => false,
            'read' => static function (int $length) use ($compressed, &$offset, &$timedOut): string {
                if ($offset >= 64) {
                    $timedOut = true;

                    throw new TimeoutException('Unable to read from stream: timed out');
                }

                $chunk = substr($compressed, $offset, min($length, 64 - $offset));
                $offset += strlen($chunk);

                return $chunk;
            },
            'getMetadata' => static function (?string $key = null) use (&$timedOut) {
                return $key === 'timed_out' ? $timedOut : null;
            },
        ]);

        $stream = new InflateStream($source);

        $this->expectException(TimeoutException::class);
        $this->expectExceptionMessage('Unable to read from stream: timed out');

        Psr7\Utils::copyToString($stream);
    }

    public function testReadReportsSourceReadTimeoutAfterDecodedBytes(): void
    {
        $timeout = new TimeoutException('Unable to read from stream: timed out');
        $deflate = deflate_init(ZLIB_ENCODING_GZIP);
        $chunks = [
            deflate_add($deflate, 'foo', ZLIB_SYNC_FLUSH),
            $timeout,
            deflate_add($deflate, 'bar', ZLIB_FINISH),
        ];

        $source = new Psr7\FnStream([
            'isReadable' => static fn (): bool => true,
            'isWritable' => static fn (): bool => false,
            'isSeekable' => static fn (): bool => false,
            'eof' => static function () use (&$chunks): bool {
                return $chunks === [];
            },
            'read' => static function (int $length) use (&$chunks): string {
                $chunk = array_shift($chunks);

                if ($chunk instanceof TimeoutException) {
                    throw $chunk;
                }

                return $chunk ?? '';
            },
        ]);

        $stream = new InflateStream($source);

        self::assertSame('foo', $stream->read(1024));
        self::assertFalse($stream->eof());

        try {
            $stream->read(1024);
            self::fail('Expected the source read timeout to be reported');
        } catch (TimeoutException $e) {
            self::assertSame($timeout, $e->getPrevious());
        }

        self::assertSame('bar', $stream->read(1024));
        self::assertTrue($stream->eof());
    }

    public function testReadReportsSourceReadFailureBeforeEof(): void
    {
        $chunk = deflate_add(deflate_init(ZLIB_ENCODING_GZIP), 'foo', ZLIB_SYNC_FLUSH);
        $reads = 0;

        $source = new Psr7\FnStream([
            'isReadable' => static fn (): bool => true,
            'isWritable' => static fn (): bool => false,
            'isSeekable' => static fn (): bool => false,
            'eof' => static function () use (&$reads): bool {
                return $reads > 1;
            },
            'read' => static function (int $length) use ($chunk, &$reads): string {
                if (++$reads === 1) {
                    return $chunk;
                }

                throw new \RuntimeException('Connection reset');
            },
        ]);

        $stream = new InflateStream($source);

        self::assertSame('foo', $stream->read(1024));
        self::assertFalse($stream->eof());

        try {
            $stream->read(1024);
            self::fail('Expected the source read failure to be reported');
        } catch (\RuntimeException $e) {
            self::assertSame('Connection reset', $e->getPrevious()->getMessage());
        }

        self::assertTrue($stream->eof());
    }

    private function getGzipStringWithFilename(string $original_string): string
    {
        $gzipped = bin2hex(gzencode($original_string));

        $header = substr($gzipped, 0, 20);
        // set FNAME flag
        $header[6] = 0;
        $header[7] = 8;
        // make a dummy filename
        $filename = '64756d6d7900';
        $rest = substr($gzipped, 20);

        return hex2bin($header.$filename.$rest);
    }
}
