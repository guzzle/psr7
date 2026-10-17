<?php

declare(strict_types=1);

namespace GuzzleHttp\Tests\Psr7;

use GuzzleHttp\Psr7;
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

    public function testReadReturnsDecodedBytesBeforeSourceReadFailures(): void
    {
        $failure = new \RuntimeException('Unable to read from stream');
        $deflate = deflate_init(ZLIB_ENCODING_GZIP);
        $chunks = [
            deflate_add($deflate, 'foo', ZLIB_SYNC_FLUSH),
            $failure,
            $failure,
            deflate_add($deflate, 'bar', ZLIB_FINISH),
        ];
        $stream = new InflateStream($this->getNonSeekableSource(
            function () use (&$chunks): string {
                $chunk = array_shift($chunks);

                if ($chunk instanceof \Exception) {
                    throw $chunk;
                }

                return $chunk ?? '';
            },
            function () use (&$chunks): bool {
                return $chunks === [];
            }
        ));

        self::assertSame('foo', $stream->read(1024));
        self::assertFalse($stream->eof());
        self::assertSame('', $stream->read(0));

        try {
            $stream->read(1024);
            self::fail();
        } catch (\RuntimeException $e) {
            self::assertSame($failure, $e->getPrevious());
        }

        try {
            $stream->read(1024);
            self::fail();
        } catch (\RuntimeException $e) {
            self::assertSame($failure, $e->getPrevious());
        }

        self::assertSame('bar', $stream->read(1024));
        self::assertTrue($stream->eof());
    }

    public function testReadReportsSourceReadFailureBeforeEof(): void
    {
        $chunk = deflate_add(deflate_init(ZLIB_ENCODING_GZIP), 'foo', ZLIB_SYNC_FLUSH);
        $reads = 0;
        $stream = new InflateStream($this->getNonSeekableSource(
            function () use ($chunk, &$reads): string {
                if (++$reads === 1) {
                    return $chunk;
                }

                throw new \RuntimeException('Connection reset');
            },
            function () use (&$reads): bool {
                return $reads > 1;
            }
        ));

        self::assertSame('foo', $stream->read(1024));
        self::assertFalse($stream->eof());

        try {
            $stream->read(1024);
            self::fail();
        } catch (\RuntimeException $e) {
            self::assertSame('Connection reset', $e->getPrevious()->getMessage());
        }

        self::assertTrue($stream->eof());
    }

    public function testReadReturnsDecodedBytesBeforeSourceLogicException(): void
    {
        $failure = new \LogicException('Not supported');
        $chunk = deflate_add(deflate_init(ZLIB_ENCODING_GZIP), 'foo', ZLIB_SYNC_FLUSH);
        $reads = 0;
        $stream = new InflateStream($this->getNonSeekableSource(
            function () use ($chunk, $failure, &$reads): string {
                if (++$reads === 1) {
                    return $chunk;
                }

                throw $failure;
            },
            function (): bool {
                return false;
            }
        ));

        self::assertSame('foo', $stream->read(1024));

        try {
            $stream->read(1024);
            self::fail();
        } catch (\RuntimeException $e) {
            self::assertSame($failure, $e->getPrevious());
        }
    }

    public function testDetachedResourceReadsTheSourceDirectly(): void
    {
        if (\PHP_VERSION_ID < 70400) {
            self::markTestSkipped('Reading a filtered resource whose source throws never returns before PHP 7.4.');
        }

        $failure = new \RuntimeException('Connection reset');
        $resource = (new InflateStream($this->getNonSeekableSource(
            function () use ($failure): string {
                throw $failure;
            },
            function (): bool {
                return false;
            }
        )))->detach();

        try {
            fread($resource, 1024);
            self::fail();
        } catch (\RuntimeException $e) {
            self::assertSame($failure, $e);
        }
    }

    public function testCloseReleasesTheSourceStream(): void
    {
        $handle = Psr7\Utils::tryFopen('php://temp', 'r+');
        fwrite($handle, gzencode('test'));
        rewind($handle);
        $stream = new InflateStream(new Psr7\Stream($handle));

        $stream->close();

        self::assertFalse(is_resource($handle));
    }

    public function testClosingDetachedResourceReleasesTheSourceStream(): void
    {
        $handle = Psr7\Utils::tryFopen('php://temp', 'r+');
        fwrite($handle, gzencode('test'));
        rewind($handle);
        $stream = new InflateStream(new Psr7\Stream($handle));

        fclose($stream->detach());

        self::assertFalse(is_resource($handle));
    }

    private function getNonSeekableSource(callable $read, callable $eof): Psr7\FnStream
    {
        return new Psr7\FnStream([
            'read' => $read,
            'eof' => $eof,
            'isReadable' => function (): bool {
                return true;
            },
            'isWritable' => function (): bool {
                return false;
            },
            'isSeekable' => function (): bool {
                return false;
            },
        ]);
    }

    private function getGzipStringWithFilename($original_string)
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
