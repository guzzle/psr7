<?php

declare(strict_types=1);

namespace GuzzleHttp\Tests\Psr7;

use GuzzleHttp\Psr7\Exception\TimeoutException;
use GuzzleHttp\Psr7\FnStream;
use GuzzleHttp\Psr7\Stream;
use GuzzleHttp\Psr7\StreamWrapper;
use PHPUnit\Framework\TestCase;

/**
 * @covers \GuzzleHttp\Psr7\Stream
 */
class StreamTest extends TestCase
{
    public static bool $isFReadError = false;
    public static bool $isFWriteError = false;
    public static bool $isFWriteZero = false;
    public static bool $isFWriteException = false;
    public static bool $isStreamTimedOut = false;
    public static bool $isStreamMetadataError = false;

    protected function tearDown(): void
    {
        self::$isFReadError = false;
        self::$isFWriteError = false;
        self::$isFWriteZero = false;
        self::$isFWriteException = false;
        self::$isStreamTimedOut = false;
        self::$isStreamMetadataError = false;
    }

    public function testConstructorThrowsExceptionOnInvalidArgument(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Stream(true);
    }

    public function testConstructorInitializesProperties(): void
    {
        $handle = fopen('php://temp', 'r+');
        fwrite($handle, 'data');
        $stream = new Stream($handle);
        self::assertTrue($stream->isReadable());
        self::assertTrue($stream->isWritable());
        self::assertTrue($stream->isSeekable());
        self::assertSame('php://temp', $stream->getMetadata('uri'));
        self::assertIsArray($stream->getMetadata());
        self::assertSame(4, $stream->getSize());
        self::assertFalse($stream->eof());
        $stream->close();
    }

    public function testConstructorInitializesPropertiesWithRbPlus(): void
    {
        $handle = fopen('php://temp', 'rb+');
        fwrite($handle, 'data');
        $stream = new Stream($handle);
        self::assertTrue($stream->isReadable());
        self::assertTrue($stream->isWritable());
        self::assertTrue($stream->isSeekable());
        self::assertSame('php://temp', $stream->getMetadata('uri'));
        self::assertIsArray($stream->getMetadata());
        self::assertSame(4, $stream->getSize());
        self::assertFalse($stream->eof());
        $stream->close();
    }

    public function testStreamClosesHandleOnDestruct(): void
    {
        $handle = fopen('php://temp', 'r');
        $stream = new Stream($handle);
        unset($stream);
        self::assertFalse(is_resource($handle));
    }

    public function testConvertsToString(): void
    {
        $handle = fopen('php://temp', 'w+');
        fwrite($handle, 'data');
        $stream = new Stream($handle);
        self::assertSame('data', (string) $stream);
        self::assertSame('data', (string) $stream);
        $stream->close();
    }

    public function testConvertsToStringNonSeekableStream(): void
    {
        $handle = popen('echo foo', 'r');
        $stream = new Stream($handle);
        self::assertFalse($stream->isSeekable());
        self::assertSame('foo', trim((string) $stream));
    }

    public function testConvertsToStringNonSeekablePartiallyReadStream(): void
    {
        $handle = popen('echo bar', 'r');
        $stream = new Stream($handle);
        $firstLetter = $stream->read(1);
        self::assertFalse($stream->isSeekable());
        self::assertSame('b', $firstLetter);
        self::assertSame('ar', trim((string) $stream));
    }

    public function testGetsContents(): void
    {
        $handle = fopen('php://temp', 'w+');
        fwrite($handle, 'data');
        $stream = new Stream($handle);
        self::assertSame('', $stream->getContents());
        $stream->seek(0);
        self::assertSame('data', $stream->getContents());
        self::assertSame('', $stream->getContents());
        $stream->close();
    }

    public function testChecksEof(): void
    {
        $handle = fopen('php://temp', 'w+');
        fwrite($handle, 'data');
        $stream = new Stream($handle);
        self::assertSame(4, $stream->tell(), 'Stream cursor already at the end');
        self::assertFalse($stream->eof(), 'Stream still not eof');
        self::assertSame('', $stream->read(1), 'Need to read one more byte to reach eof');
        self::assertTrue($stream->eof());
        $stream->close();
    }

    public function testGetSize(): void
    {
        $size = filesize(__FILE__);
        $handle = fopen(__FILE__, 'r');
        $stream = new Stream($handle);
        self::assertSame($size, $stream->getSize());
        // Load from cache
        self::assertSame($size, $stream->getSize());
        $stream->close();
    }

    public function testEnsuresSizeIsConsistent(): void
    {
        $h = fopen('php://temp', 'w+');
        self::assertSame(3, fwrite($h, 'foo'));
        $stream = new Stream($h);
        self::assertSame(3, $stream->getSize());
        self::assertSame(4, $stream->write('test'));
        self::assertSame(7, $stream->getSize());
        self::assertSame(7, $stream->getSize());
        $stream->close();
    }

    public function testProvidesStreamPosition(): void
    {
        $handle = fopen('php://temp', 'w+');
        $stream = new Stream($handle);
        self::assertSame(0, $stream->tell());
        $stream->write('foo');
        self::assertSame(3, $stream->tell());
        $stream->seek(1);
        self::assertSame(1, $stream->tell());
        self::assertSame(ftell($handle), $stream->tell());
        $stream->close();
    }

    public function testDetachStreamAndClearProperties(): void
    {
        $handle = fopen('php://temp', 'r');
        $stream = new Stream($handle);
        self::assertSame($handle, $stream->detach());
        self::assertIsResource($handle, 'Stream is not closed');
        self::assertNull($stream->detach());

        $this->assertStreamStateAfterClosedOrDetached($stream);

        $stream->close();
    }

    public function testDetachNonSeekableStreamAndClearProperties(): void
    {
        $handle = popen('echo foo', 'r');
        $stream = new Stream($handle);
        self::assertFalse($stream->isSeekable());
        $detached = $stream->detach();
        self::assertIsResource($detached);
        self::assertNull($stream->detach());

        $this->assertStreamStateAfterClosedOrDetached($stream);

        pclose($detached);
    }

    public function testCloseResourceAndClearProperties(): void
    {
        $handle = fopen('php://temp', 'r');
        $stream = new Stream($handle);
        $stream->close();

        self::assertFalse(is_resource($handle));

        $this->assertStreamStateAfterClosedOrDetached($stream);
    }

    private function assertStreamStateAfterClosedOrDetached(Stream $stream): void
    {
        self::assertFalse($stream->isReadable());
        self::assertFalse($stream->isWritable());
        self::assertFalse($stream->isSeekable());
        self::assertNull($stream->getSize());
        self::assertSame([], $stream->getMetadata());
        self::assertNull($stream->getMetadata('foo'));

        $throws = function (callable $fn): void {
            try {
                $fn();
            } catch (\Exception $e) {
                $this->assertStringContainsString('Stream is detached', $e->getMessage());

                return;
            }

            $this->fail('Exception should be thrown after the stream is detached.');
        };

        $throws(function () use ($stream): void {
            $stream->read(10);
        });
        $throws(function () use ($stream): void {
            $stream->write('bar');
        });
        $throws(function () use ($stream): void {
            $stream->seek(10);
        });
        $throws(function () use ($stream): void {
            $stream->tell();
        });
        $throws(function () use ($stream): void {
            $stream->eof();
        });
        $throws(function () use ($stream): void {
            $stream->getContents();
        });

        $throws(function () use ($stream): void {
            (string) $stream;
        });
    }

    public function testStreamReadingWithZeroLength(): void
    {
        $r = fopen('php://temp', 'r');
        $stream = new Stream($r);

        self::assertSame('', $stream->read(0));

        $stream->close();
    }

    public function testStreamReadingWithNegativeLength(): void
    {
        $r = fopen('php://temp', 'r');
        $stream = new Stream($r);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Length parameter cannot be negative');

        try {
            $stream->read(-1);
        } catch (\Exception $e) {
            $stream->close();
            throw $e;
        }

        $stream->close();
    }

    public function testStreamReadingFreadFalse(): void
    {
        self::$isFReadError = true;
        $r = fopen('php://temp', 'r');
        $stream = new Stream($r);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unable to read from stream');

        try {
            $stream->read(1);
        } catch (\Exception $e) {
            self::$isFReadError = false;
            $stream->close();
            throw $e;
        }

        self::$isFReadError = false;
        $stream->close();
    }

    public function testStreamReadingFreadException(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unable to read from stream');

        $r = StreamWrapper::getResource(new FnStream([
            'read' => function ($len): string {
                throw new \ErrorException('Some error');
            },
            'isReadable' => function (): bool {
                return true;
            },
            'isWritable' => function (): bool {
                return false;
            },
            'eof' => function (): bool {
                return false;
            },
        ]));

        $stream = new Stream($r);
        $stream->read(1);
    }

    public function testStreamWritingFwriteFalseThrowsRuntimeException(): void
    {
        self::$isFWriteError = true;
        $r = fopen('php://temp', 'w+');
        $stream = new Stream($r);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unable to write to stream');

        try {
            $stream->write('x');
        } finally {
            $stream->close();
        }
    }

    public function testStreamWritingFwriteFalseWhenTimedOutThrowsTimeoutException(): void
    {
        self::$isFWriteError = true;
        self::$isStreamTimedOut = true;
        $r = fopen('php://temp', 'w+');
        $stream = new Stream($r);

        $this->expectException(TimeoutException::class);
        $this->expectExceptionMessage('Unable to write to stream: timed out');

        try {
            $stream->write('x');
        } finally {
            $stream->close();
        }
    }

    public function testStreamWritingZeroBytesWhenTimedOutThrowsTimeoutException(): void
    {
        self::$isFWriteZero = true;
        self::$isStreamTimedOut = true;
        $r = fopen('php://temp', 'w+');
        $stream = new Stream($r);

        $this->expectException(TimeoutException::class);
        $this->expectExceptionMessage('Unable to write to stream: timed out');

        try {
            $stream->write('x');
        } finally {
            $stream->close();
        }
    }

    public function testStreamWritingZeroBytesWithoutTimeoutReturnsZero(): void
    {
        self::$isFWriteZero = true;
        $r = fopen('php://temp', 'w+');
        $stream = new Stream($r);

        try {
            self::assertSame(0, $stream->write('x'));
        } finally {
            $stream->close();
        }
    }

    public function testStreamWritingEmptyStringReturnsZeroWithoutWritingOrInvalidatingSize(): void
    {
        $r = fopen('php://temp', 'w+');
        fwrite($r, 'data');
        $stream = new Stream($r);
        self::assertSame(4, $stream->getSize());

        self::$isFWriteException = true;
        self::$isStreamTimedOut = true;
        self::$isStreamMetadataError = true;

        try {
            self::assertSame(0, $stream->write(''));
            self::assertSame(4, $stream->getSize());
        } finally {
            $stream->close();
        }
    }

    public function testStreamWritingEmptyStringStillRequiresWritableStream(): void
    {
        $r = fopen('php://input', 'r');
        $stream = new Stream($r);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cannot write to a non-writable stream');

        try {
            $stream->write('');
        } finally {
            $stream->close();
        }
    }

    public function testStreamWritingPositiveBytesIgnoresStaleTimeoutMetadata(): void
    {
        $r = fopen('php://temp', 'w+');
        $stream = new Stream($r);
        self::$isStreamTimedOut = true;

        try {
            self::assertSame(3, $stream->write('foo'));
        } finally {
            $stream->close();
        }
    }

    public function testStreamWritingFwriteExceptionWhenTimedOutThrowsTimeoutExceptionWithPrevious(): void
    {
        self::$isFWriteException = true;
        self::$isStreamTimedOut = true;
        $r = fopen('php://temp', 'w+');
        $stream = new Stream($r);

        try {
            $stream->write('x');
            self::fail('Expected timeout exception');
        } catch (TimeoutException $e) {
            self::assertSame('Unable to write to stream: timed out', $e->getMessage());
            self::assertInstanceOf(\ErrorException::class, $e->getPrevious());
            self::assertSame('Some write error', $e->getPrevious()->getMessage());
        } finally {
            $stream->close();
        }
    }

    public function testStreamWritingFwriteExceptionWithoutTimeoutThrowsRuntimeExceptionWithPrevious(): void
    {
        self::$isFWriteException = true;
        $r = fopen('php://temp', 'w+');
        $stream = new Stream($r);

        try {
            $stream->write('x');
            self::fail('Expected runtime exception');
        } catch (\RuntimeException $e) {
            self::assertNotInstanceOf(TimeoutException::class, $e);
            self::assertSame('Unable to write to stream', $e->getMessage());
            self::assertInstanceOf(\ErrorException::class, $e->getPrevious());
            self::assertSame('Some write error', $e->getPrevious()->getMessage());
        } finally {
            $stream->close();
        }
    }

    public function testStreamWritingFwriteFalseWhenMetadataProbeFailsPreservesGenericRuntimeException(): void
    {
        self::$isFWriteError = true;
        $r = fopen('php://temp', 'w+');
        $stream = new Stream($r);
        self::$isStreamMetadataError = true;

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unable to write to stream');

        try {
            $stream->write('x');
        } finally {
            $stream->close();
        }
    }

    /**
     * @requires extension zlib
     *
     * @dataProvider gzipModeProvider
     */
    public function testGzipStreamModes(string $mode, bool $readable, bool $writable): void
    {
        $r = gzopen('php://temp', $mode);
        $stream = new Stream($r);

        self::assertSame($readable, $stream->isReadable());
        self::assertSame($writable, $stream->isWritable());

        $stream->close();
    }

    public static function gzipModeProvider(): iterable
    {
        return [
            ['mode' => 'rb9', 'readable' => true, 'writable' => false],
            ['mode' => 'wb2', 'readable' => false, 'writable' => true],
            ['mode' => 'wb6f', 'readable' => false, 'writable' => true],
            ['mode' => 'wb1h', 'readable' => false, 'writable' => true],
            ['mode' => 'ab9', 'readable' => false, 'writable' => true],
        ];
    }

    /**
     * @dataProvider fileModeCapabilityProvider
     */
    public function testFileStreamModeCapabilities(string $mode, bool $readable, bool $writable): void
    {
        $path = tempnam(sys_get_temp_dir(), 'guzzle-psr7-mode-');
        if ($path === false) {
            self::fail('Unable to create temporary file');
        }

        try {
            if ($mode[0] === 'x') {
                if (!unlink($path)) {
                    self::fail('Unable to remove temporary file before exclusive create test');
                }
            } else {
                if (file_put_contents($path, 'data') === false) {
                    self::fail('Unable to write temporary file');
                }
            }

            $r = fopen($path, $mode);
            if (!is_resource($r)) {
                self::fail(sprintf('Unable to open temporary file using mode "%s"', $mode));
            }

            $stream = new Stream($r);

            try {
                self::assertSame($readable, $stream->isReadable());
                self::assertSame($writable, $stream->isWritable());
            } finally {
                $stream->close();
            }
        } finally {
            if (file_exists($path)) {
                unlink($path);
            }
        }
    }

    public static function fileModeCapabilityProvider(): iterable
    {
        return [
            'r' => ['mode' => 'r', 'readable' => true, 'writable' => false],
            'rb' => ['mode' => 'rb', 'readable' => true, 'writable' => false],
            'rt' => ['mode' => 'rt', 'readable' => true, 'writable' => false],
            'rw' => ['mode' => 'rw', 'readable' => true, 'writable' => false],
            'r+' => ['mode' => 'r+', 'readable' => true, 'writable' => true],
            'rb+' => ['mode' => 'rb+', 'readable' => true, 'writable' => true],
            'r+b' => ['mode' => 'r+b', 'readable' => true, 'writable' => true],
            'rt+' => ['mode' => 'rt+', 'readable' => true, 'writable' => true],
            'r+t' => ['mode' => 'r+t', 'readable' => true, 'writable' => true],
            'w' => ['mode' => 'w', 'readable' => false, 'writable' => true],
            'wb' => ['mode' => 'wb', 'readable' => false, 'writable' => true],
            'wt' => ['mode' => 'wt', 'readable' => false, 'writable' => true],
            'w+' => ['mode' => 'w+', 'readable' => true, 'writable' => true],
            'wb+' => ['mode' => 'wb+', 'readable' => true, 'writable' => true],
            'w+b' => ['mode' => 'w+b', 'readable' => true, 'writable' => true],
            'wt+' => ['mode' => 'wt+', 'readable' => true, 'writable' => true],
            'w+t' => ['mode' => 'w+t', 'readable' => true, 'writable' => true],
            'a' => ['mode' => 'a', 'readable' => false, 'writable' => true],
            'ab' => ['mode' => 'ab', 'readable' => false, 'writable' => true],
            'at' => ['mode' => 'at', 'readable' => false, 'writable' => true],
            'a+' => ['mode' => 'a+', 'readable' => true, 'writable' => true],
            'ab+' => ['mode' => 'ab+', 'readable' => true, 'writable' => true],
            'a+b' => ['mode' => 'a+b', 'readable' => true, 'writable' => true],
            'at+' => ['mode' => 'at+', 'readable' => true, 'writable' => true],
            'a+t' => ['mode' => 'a+t', 'readable' => true, 'writable' => true],
            'x' => ['mode' => 'x', 'readable' => false, 'writable' => true],
            'xb' => ['mode' => 'xb', 'readable' => false, 'writable' => true],
            'xt' => ['mode' => 'xt', 'readable' => false, 'writable' => true],
            'x+' => ['mode' => 'x+', 'readable' => true, 'writable' => true],
            'xb+' => ['mode' => 'xb+', 'readable' => true, 'writable' => true],
            'x+b' => ['mode' => 'x+b', 'readable' => true, 'writable' => true],
            'xt+' => ['mode' => 'xt+', 'readable' => true, 'writable' => true],
            'x+t' => ['mode' => 'x+t', 'readable' => true, 'writable' => true],
            'c' => ['mode' => 'c', 'readable' => false, 'writable' => true],
            'cb' => ['mode' => 'cb', 'readable' => false, 'writable' => true],
            'ct' => ['mode' => 'ct', 'readable' => false, 'writable' => true],
            'c+' => ['mode' => 'c+', 'readable' => true, 'writable' => true],
            'cb+' => ['mode' => 'cb+', 'readable' => true, 'writable' => true],
            'c+b' => ['mode' => 'c+b', 'readable' => true, 'writable' => true],
            'ct+' => ['mode' => 'ct+', 'readable' => true, 'writable' => true],
            'c+t' => ['mode' => 'c+t', 'readable' => true, 'writable' => true],
        ];
    }

    /**
     * @dataProvider readableModeProvider
     */
    public function testReadableStream(string $mode): void
    {
        $r = fopen('php://temp', $mode);
        $stream = new Stream($r);

        self::assertTrue($stream->isReadable());

        $stream->close();
    }

    public static function readableModeProvider(): iterable
    {
        return [
            ['r'],
            ['w+'],
            ['r+'],
            ['x+'],
            ['c+'],
            ['rb'],
            ['w+b'],
            ['r+b'],
            ['x+b'],
            ['c+b'],
            ['rt'],
            ['w+t'],
            ['r+t'],
            ['x+t'],
            ['c+t'],
            ['a+'],
            ['rb+'],
        ];
    }

    public function testWriteOnlyStreamIsNotReadable(): void
    {
        $r = fopen('php://output', 'w');
        $stream = new Stream($r);

        self::assertFalse($stream->isReadable());

        $stream->close();
    }

    /**
     * @dataProvider writableModeProvider
     */
    public function testWritableStream(string $mode): void
    {
        $r = fopen('php://temp', $mode);
        $stream = new Stream($r);

        self::assertTrue($stream->isWritable());

        $stream->close();
    }

    public static function writableModeProvider(): iterable
    {
        return [
            ['w'],
            ['w+'],
            ['r+'],
            ['x+'],
            ['c+'],
            ['wb'],
            ['w+b'],
            ['r+b'],
            ['rb+'],
            ['x+b'],
            ['c+b'],
            ['w+t'],
            ['r+t'],
            ['x+t'],
            ['c+t'],
            ['a'],
            ['a+'],
        ];
    }

    public function testReadOnlyStreamIsNotWritable(): void
    {
        $r = fopen('php://input', 'r');
        $stream = new Stream($r);

        self::assertFalse($stream->isWritable());

        $stream->close();
    }

    public function testCannotReadUnreadableStream(): void
    {
        $r = fopen(tempnam(sys_get_temp_dir(), 'guzzle-psr7-'), 'w');
        $stream = new Stream($r);

        $stream->write('Hello world!!');

        $stream->seek(0);

        $this->expectException(\RuntimeException::class);

        try {
            $stream->getContents();
        } finally {
            $stream->close();
        }
    }
}

namespace GuzzleHttp\Psr7;

use GuzzleHttp\Tests\Psr7\StreamTest;

/**
 * @param resource $handle
 *
 * @return string|false
 */
function fread($handle, int $length)
{
    return StreamTest::$isFReadError ? false : \fread($handle, $length);
}

/**
 * @param resource $handle
 *
 * @return int|false
 */
function fwrite($handle, string $string, ?int $length = null)
{
    if (StreamTest::$isFWriteException) {
        throw new \ErrorException('Some write error');
    }

    if (StreamTest::$isFWriteError) {
        return false;
    }

    if (StreamTest::$isFWriteZero) {
        return 0;
    }

    if ($length === null) {
        return \fwrite($handle, $string);
    }

    return \fwrite($handle, $string, $length);
}

/**
 * @param resource $stream
 *
 * @return array<string, mixed>
 */
function stream_get_meta_data($stream): array
{
    if (StreamTest::$isStreamMetadataError) {
        throw new \RuntimeException('metadata failed');
    }

    $metadata = \stream_get_meta_data($stream);

    if (StreamTest::$isStreamTimedOut) {
        $metadata['timed_out'] = true;
    }

    return $metadata;
}
