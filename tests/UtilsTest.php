<?php

declare(strict_types=1);

namespace GuzzleHttp\Tests\Psr7;

use GuzzleHttp\Psr7;
use GuzzleHttp\Psr7\FnStream;
use GuzzleHttp\Psr7\NoSeekStream;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\StreamInterface;

class UtilsTest extends TestCase
{
    protected function tearDown(): void
    {
        PhpStreamMock::reset();
    }

    public function testAsciiToLower(): void
    {
        self::assertSame('abcdefghijklmnopqrstuvwxyz', Psr7\Utils::asciiToLower('ABCDEFGHIJKLMNOPQRSTUVWXYZ'));
        self::assertSame('x-checksum', Psr7\Utils::asciiToLower('X-Checksum'));
        self::assertSame('0123456789 !#$%&*+-.^_`|~', Psr7\Utils::asciiToLower('0123456789 !#$%&*+-.^_`|~'));
        self::assertSame("\xC4\xB0i", Psr7\Utils::asciiToLower("\xC4\xB0I"));
        self::assertSame('', Psr7\Utils::asciiToLower(''));
    }

    public function testAsciiToUpper(): void
    {
        self::assertSame('ABCDEFGHIJKLMNOPQRSTUVWXYZ', Psr7\Utils::asciiToUpper('abcdefghijklmnopqrstuvwxyz'));
        self::assertSame('X-CHECKSUM', Psr7\Utils::asciiToUpper('x-Checksum'));
        self::assertSame('0123456789 !#$%&*+-.^_`|~', Psr7\Utils::asciiToUpper('0123456789 !#$%&*+-.^_`|~'));
        self::assertSame("\xC4\xB1I", Psr7\Utils::asciiToUpper("\xC4\xB1i"));
        self::assertSame('', Psr7\Utils::asciiToUpper(''));
    }

    public function testAsciiUcFirst(): void
    {
        self::assertSame('Index', Psr7\Utils::asciiUcFirst('index'));
        self::assertSame('Index', Psr7\Utils::asciiUcFirst('Index'));
        self::assertSame('X-a', Psr7\Utils::asciiUcFirst('x-a'));
        self::assertSame('0abc', Psr7\Utils::asciiUcFirst('0abc'));
        self::assertSame("\xC4\xB1i", Psr7\Utils::asciiUcFirst("\xC4\xB1i"));
        self::assertSame('', Psr7\Utils::asciiUcFirst(''));
    }

    public function testCaselessContains(): void
    {
        self::assertTrue(Psr7\Utils::caselessContains('Connection TIMEOUT after', 'timeout'));
        self::assertFalse(Psr7\Utils::caselessContains('Connection reset', 'timeout'));
        self::assertTrue(Psr7\Utils::caselessContains('Connection timeout after', 'timeout'));
        self::assertTrue(Psr7\Utils::caselessContains('Connection reset', ''));
    }

    public function testCaselessEquals(): void
    {
        self::assertTrue(Psr7\Utils::caselessEquals('HOST', 'host'));
        self::assertFalse(Psr7\Utils::caselessEquals('host', 'hosts'));
        self::assertTrue(Psr7\Utils::caselessEquals('', ''));
        self::assertFalse(Psr7\Utils::caselessEquals("\xC4\xB0", 'i'));
    }

    public function testCopiesToString(): void
    {
        $s = Psr7\Utils::streamFor('foobaz');
        self::assertSame('foobaz', Psr7\Utils::copyToString($s));
        $s->seek(0);
        self::assertSame('foo', Psr7\Utils::copyToString($s, 3));
        self::assertSame('baz', Psr7\Utils::copyToString($s, 3));
        self::assertSame('', Psr7\Utils::copyToString($s));
    }

    public function testCopiesToStringStopsWhenReadFails(): void
    {
        $s1 = Psr7\Utils::streamFor('foobaz');
        $s1 = FnStream::decorate($s1, [
            'read' => function () {
                return '';
            },
        ]);
        $result = Psr7\Utils::copyToString($s1);
        self::assertSame('', $result);
    }

    public function testCopyToStringThrowsWhenReadTimesOut(): void
    {
        $s = new FnStream([
            'eof' => function (): bool {
                return false;
            },
            'read' => function (): string {
                return '';
            },
            'getMetadata' => function (?string $key = null) {
                return $key === 'timed_out' ? true : null;
            },
        ]);

        $this->expectException(Psr7\Exception\TimeoutException::class);
        $this->expectExceptionMessage('Unable to read from stream: timed out');

        Psr7\Utils::copyToString($s);
    }

    public function testCopyToStringIgnoresStaleTimeoutMetadataAfterSuccessfulRead(): void
    {
        $read = false;
        $s = new FnStream([
            'eof' => function () use (&$read): bool {
                return $read;
            },
            'read' => function () use (&$read): string {
                $read = true;

                return 'foo';
            },
            'getMetadata' => function (?string $key = null) {
                return $key === 'timed_out' ? true : null;
            },
        ]);

        self::assertSame('foo', Psr7\Utils::copyToString($s));
    }

    public function testCopyToStringIgnoresMetadataProbeFailure(): void
    {
        $s = new FnStream([
            'eof' => function (): bool {
                return false;
            },
            'read' => function (): string {
                return '';
            },
            'getMetadata' => function (): void {
                throw new \RuntimeException('metadata failed');
            },
        ]);

        self::assertSame('', Psr7\Utils::copyToString($s));
    }

    public function testCopyToStringPreservesReadExceptionWhenMetadataProbeFails(): void
    {
        $s = new FnStream([
            'eof' => function (): bool {
                return false;
            },
            'read' => function (): string {
                throw new \RuntimeException('read failed');
            },
            'getMetadata' => function (): void {
                throw new \RuntimeException('metadata failed');
            },
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('read failed');

        Psr7\Utils::copyToString($s);
    }

    public function testCopyToStringIgnoresEofProbeFailureDuringTimeoutDetection(): void
    {
        $eofCalls = 0;
        $s = new FnStream([
            'eof' => function () use (&$eofCalls): bool {
                ++$eofCalls;
                if ($eofCalls > 1) {
                    throw new \RuntimeException('eof failed');
                }

                return false;
            },
            'read' => function (): string {
                return '';
            },
            'getMetadata' => function (?string $key = null) {
                return $key === 'timed_out' ? true : null;
            },
        ]);

        self::assertSame('', Psr7\Utils::copyToString($s));
    }

    public function testCopyToStringIgnoresNonBooleanTimedOutMetadata(): void
    {
        $s = new FnStream([
            'eof' => function (): bool {
                return false;
            },
            'read' => function (): string {
                return '';
            },
            'getMetadata' => function (?string $key = null) {
                return $key === 'timed_out' ? 1 : null;
            },
        ]);

        self::assertSame('', Psr7\Utils::copyToString($s));
    }

    public function testCopiesToStream(): void
    {
        $s1 = Psr7\Utils::streamFor('foobaz');
        $s2 = Psr7\Utils::streamFor('');
        self::assertSame(6, Psr7\Utils::copyToStream($s1, $s2));
        self::assertSame('foobaz', (string) $s2);
        $s2 = Psr7\Utils::streamFor('');
        $s1->seek(0);
        self::assertSame(3, Psr7\Utils::copyToStream($s1, $s2, 3));
        self::assertSame('foo', (string) $s2);
        self::assertSame(3, Psr7\Utils::copyToStream($s1, $s2, 3));
        self::assertSame('foobaz', (string) $s2);
    }

    public function testCopyToStreamReturnsActualBytesWhenSourceShorterThanMaxLen(): void
    {
        $dest = Psr7\Utils::streamFor('');

        self::assertSame(2, Psr7\Utils::copyToStream(Psr7\Utils::streamFor('ab'), $dest, 5));
        self::assertSame('ab', (string) $dest);
    }

    public function testCopyToStreamWithZeroMaxLenCopiesNothing(): void
    {
        $dest = Psr7\Utils::streamFor('');

        self::assertSame(0, Psr7\Utils::copyToStream(Psr7\Utils::streamFor('abc'), $dest, 0));
        self::assertSame('', (string) $dest);
    }

    public function testCopyToStreamWithNegativeMaxLenOtherThanMinusOneCopiesNothing(): void
    {
        $dest = Psr7\Utils::streamFor('');

        self::assertSame(0, Psr7\Utils::copyToStream(Psr7\Utils::streamFor('abc'), $dest, -2));
        self::assertSame('', (string) $dest);
    }

    public function testCopyToStreamRetriesShortWrites(): void
    {
        $s1 = Psr7\Utils::streamFor('foobaz');
        $sink = Psr7\Utils::streamFor('');
        $writes = 0;

        $s2 = FnStream::decorate($sink, [
            'write' => function (string $string) use ($sink, &$writes): int {
                ++$writes;

                return $sink->write(substr($string, 0, 1));
            },
        ]);

        self::assertSame(6, Psr7\Utils::copyToStream($s1, $s2));

        self::assertSame('foobaz', (string) $sink);
        self::assertSame(6, $writes);
    }

    public function testCopyToStreamRetriesShortWritesWithMaxLen(): void
    {
        $s1 = Psr7\Utils::streamFor('foobaz');
        $sink = Psr7\Utils::streamFor('');
        $writes = 0;

        $s2 = FnStream::decorate($sink, [
            'write' => function (string $string) use ($sink, &$writes): int {
                ++$writes;

                return $sink->write(substr($string, 0, 1));
            },
        ]);

        self::assertSame(3, Psr7\Utils::copyToStream($s1, $s2, 3));

        self::assertSame('foo', (string) $sink);
        self::assertSame(3, $writes);
    }

    public function testCopyToStreamThrowsWhenReadTimesOut(): void
    {
        $s1 = new FnStream([
            'eof' => function (): bool {
                return false;
            },
            'read' => function (): string {
                return '';
            },
            'getMetadata' => function (?string $key = null) {
                return $key === 'timed_out' ? true : null;
            },
        ]);
        $s2 = Psr7\Utils::streamFor('');

        $this->expectException(Psr7\Exception\TimeoutException::class);
        $this->expectExceptionMessage('Unable to read from stream: timed out');

        Psr7\Utils::copyToStream($s1, $s2);
    }

    public function testCopyToStreamThrowsWhenReadTimesOutWithMaxLen(): void
    {
        $s1 = new FnStream([
            'eof' => function (): bool {
                return false;
            },
            'read' => function (): string {
                return '';
            },
            'getMetadata' => function (?string $key = null) {
                return $key === 'timed_out' ? true : null;
            },
        ]);
        $s2 = Psr7\Utils::streamFor('');

        $this->expectException(Psr7\Exception\TimeoutException::class);
        $this->expectExceptionMessage('Unable to read from stream: timed out');

        Psr7\Utils::copyToStream($s1, $s2, 10);
    }

    public function testCopyToStreamThrowsWhenWriteTimesOut(): void
    {
        $s1 = Psr7\Utils::streamFor('foobaz');
        $s2 = Psr7\Utils::streamFor('');
        $s2 = FnStream::decorate($s2, [
            'write' => function (): int {
                return 0;
            },
            'getMetadata' => function (?string $key = null) {
                return $key === 'timed_out' ? true : null;
            },
        ]);

        $this->expectException(Psr7\Exception\TimeoutException::class);
        $this->expectExceptionMessage('Unable to write to stream: timed out');

        Psr7\Utils::copyToStream($s1, $s2);
    }

    public function testCopyToStreamPreservesWriteFailureWhenMetadataProbeFails(): void
    {
        $s1 = Psr7\Utils::streamFor('foobaz');
        $s2 = Psr7\Utils::streamFor('');
        $s2 = FnStream::decorate($s2, [
            'write' => function (): int {
                return 0;
            },
            'getMetadata' => function (): void {
                throw new \RuntimeException('metadata failed');
            },
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unable to write to stream');

        Psr7\Utils::copyToStream($s1, $s2);
    }

    public function testCopyToStreamPreservesThrownWriteExceptionWhenMetadataProbeFails(): void
    {
        $s1 = Psr7\Utils::streamFor('foobaz');
        $s2 = Psr7\Utils::streamFor('');
        $s2 = FnStream::decorate($s2, [
            'write' => function (): int {
                throw new \RuntimeException('write failed');
            },
            'getMetadata' => function (): void {
                throw new \RuntimeException('metadata failed');
            },
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('write failed');

        Psr7\Utils::copyToStream($s1, $s2);
    }

    public function testCopyToStreamIgnoresStaleTimeoutMetadataAfterMaxLenIsSatisfied(): void
    {
        $s1 = Psr7\Utils::streamFor('foobaz');
        $s1 = FnStream::decorate($s1, [
            'getMetadata' => function (?string $key = null) {
                return $key === 'timed_out' ? true : null;
            },
        ]);
        $s2 = Psr7\Utils::streamFor('');

        self::assertSame(3, Psr7\Utils::copyToStream($s1, $s2, 3));

        self::assertSame('foo', (string) $s2);
    }

    public function testCopyToStreamThrowsWhenWriteFails(): void
    {
        $s1 = Psr7\Utils::streamFor('foobaz');
        $s2 = Psr7\Utils::streamFor('');
        $s2 = FnStream::decorate($s2, [
            'write' => function () {
                return 0;
            },
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unable to write to stream');

        Psr7\Utils::copyToStream($s1, $s2);
    }

    public function testCopyToStreamThrowsWhenWriteFailsWithMaxLen(): void
    {
        $s1 = Psr7\Utils::streamFor('foobaz');
        $s2 = Psr7\Utils::streamFor('');
        $s2 = FnStream::decorate($s2, [
            'write' => function () {
                return 0;
            },
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unable to write to stream');

        Psr7\Utils::copyToStream($s1, $s2, 10);
    }

    public function testCopyToStreamThrowsWhenDestinationBufferStreamReachesHighWaterMark(): void
    {
        $source = Psr7\Utils::streamFor('foobaz');
        $dest = new Psr7\BufferStream(3);

        try {
            Psr7\Utils::copyToStream($source, $dest);
            self::fail('Expected a RuntimeException to be thrown');
        } catch (\RuntimeException $e) {
            self::assertSame('Unable to write to stream', $e->getMessage());
        }

        // The bytes are buffered even though copyToStream reports failure.
        self::assertSame('foobaz', (string) $dest);
    }

    public function testCopyToStreamThrowsWhenDestinationDroppingStreamIsFull(): void
    {
        $source = Psr7\Utils::streamFor('foobaz');
        $underlying = new Psr7\BufferStream();
        $dest = new Psr7\DroppingStream($underlying, 3);

        try {
            Psr7\Utils::copyToStream($source, $dest);
            self::fail('Expected a RuntimeException to be thrown');
        } catch (\RuntimeException $e) {
            self::assertSame('Unable to write to stream', $e->getMessage());
        }

        // The destination kept up to maxLength bytes and dropped the rest.
        self::assertSame('foo', (string) $underlying);
    }

    public function testCopyToStreamReadsInChunksInsteadOfAllInMemory(): void
    {
        $sizes = [];
        $s1 = new FnStream([
            'eof' => function () {
                return false;
            },
            'read' => function ($size) use (&$sizes) {
                $sizes[] = $size;

                return str_repeat('.', $size);
            },
        ]);
        $s2 = Psr7\Utils::streamFor('');
        self::assertSame(16394, Psr7\Utils::copyToStream($s1, $s2, 16394));
        $s2->seek(0);
        self::assertSame(16394, strlen($s2->getContents()));
        self::assertSame(8192, $sizes[0]);
        self::assertSame(8192, $sizes[1]);
        self::assertSame(10, $sizes[2]);
    }

    public function testStopsCopyToSteamWhenReadFailsWithMaxLen(): void
    {
        $s1 = Psr7\Utils::streamFor('foobaz');
        $s1 = FnStream::decorate($s1, [
            'read' => function () {
                return '';
            },
        ]);
        $s2 = Psr7\Utils::streamFor('');
        self::assertSame(0, Psr7\Utils::copyToStream($s1, $s2, 10));
        self::assertSame('', (string) $s2);
    }

    public function testReadsLines(): void
    {
        $s = Psr7\Utils::streamFor("foo\nbaz\nbar");
        self::assertSame("foo\n", Psr7\Utils::readLine($s));
        self::assertSame("baz\n", Psr7\Utils::readLine($s));
        self::assertSame('bar', Psr7\Utils::readLine($s));
    }

    public function testReadsLinesUpToMaxLength(): void
    {
        $s = Psr7\Utils::streamFor("12345\n");
        self::assertSame('123', Psr7\Utils::readLine($s, 4));
        self::assertSame("45\n", Psr7\Utils::readLine($s));
    }

    public function testReadLinesEof(): void
    {
        // Should return empty string on EOF
        $s = Psr7\Utils::streamFor("foo\nbar");
        while (!$s->eof()) {
            Psr7\Utils::readLine($s);
        }
        self::assertSame('', Psr7\Utils::readLine($s));
    }

    public function testReadsLineUntilEmptyStringReturnedFromRead(): void
    {
        $s = $this->createMock(StreamInterface::class);
        $s->expects(self::exactly(2))
            ->method('read')
            ->willReturnCallback(function () {
                static $called = false;
                if ($called) {
                    return '';
                }
                $called = true;

                return 'h';
            });
        $s->expects(self::exactly(2))
            ->method('eof')
            ->willReturn(false);
        self::assertSame('h', Psr7\Utils::readLine($s));
    }

    public function testReadLineThrowsWhenReadTimesOut(): void
    {
        $s = $this->createMock(StreamInterface::class);
        $s->method('read')->willReturn('');
        $s->method('eof')->willReturn(false);
        $s->method('getMetadata')->with('timed_out')->willReturn(true);

        $this->expectException(Psr7\Exception\TimeoutException::class);
        $this->expectExceptionMessage('Unable to read line from stream: timed out');

        Psr7\Utils::readLine($s);
    }

    public function testReadLineIgnoresMetadataProbeFailure(): void
    {
        $s = $this->createMock(StreamInterface::class);
        $s->method('read')->willReturn('');
        $s->method('eof')->willReturn(false);
        $s->method('getMetadata')->with('timed_out')->willThrowException(new \RuntimeException('metadata failed'));

        self::assertSame('', Psr7\Utils::readLine($s));
    }

    public function testCalculatesHash(): void
    {
        $s = Psr7\Utils::streamFor('foobazbar');
        self::assertSame(md5('foobazbar'), Psr7\Utils::hash($s, 'md5'));
    }

    public function testCalculatesHashThrowsWhenSeekFails(): void
    {
        $s = new NoSeekStream(Psr7\Utils::streamFor('foobazbar'));
        $s->read(2);

        $this->expectException(\RuntimeException::class);

        Psr7\Utils::hash($s, 'md5');
    }

    public function testCalculatesHashSeeksToOriginalPosition(): void
    {
        $s = Psr7\Utils::streamFor('foobazbar');
        $s->seek(4);
        self::assertSame(md5('foobazbar'), Psr7\Utils::hash($s, 'md5'));
        self::assertSame(4, $s->tell());
    }

    public function testCalculatesHashThrowsWhenReadTimesOut(): void
    {
        $s = $this->createMock(StreamInterface::class);
        $s->method('tell')->willReturn(0);
        $s->method('eof')->willReturn(false);
        $s->method('read')->willReturn('');
        $s->method('getMetadata')->with('timed_out')->willReturn(true);

        $this->expectException(Psr7\Exception\TimeoutException::class);
        $this->expectExceptionMessage('Unable to calculate stream hash: timed out');

        Psr7\Utils::hash($s, 'md5');
    }

    public function testCalculatesHashStopsWhenReadReturnsEmptyString(): void
    {
        $s = $this->createMock(StreamInterface::class);
        $s->method('tell')->willReturn(0);
        $s->method('eof')->willReturn(false);
        $s->method('read')->willReturn('');
        $s->method('getMetadata')->with('timed_out')->willReturn(false);
        $s->expects(self::once())->method('seek')->with(0);

        self::assertSame(md5(''), Psr7\Utils::hash($s, 'md5'));
    }

    public function testOpensFilesSuccessfully(): void
    {
        $r = Psr7\Utils::tryFopen(__FILE__, 'r');
        self::assertIsResource($r);
        fclose($r);
    }

    public function testThrowsExceptionNotWarning(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unable to open /path/to/does/not/exist using mode r');

        Psr7\Utils::tryFopen('/path/to/does/not/exist', 'r');
    }

    public function testTryFopenEscapesFilenameInException(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unable to open /path/to/does\\x0Anot-exist using mode r');

        Psr7\Utils::tryFopen("/path/to/does\nnot-exist", 'r');
    }

    public function testThrowsExceptionNotValueError(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unable to open  using mode r');

        Psr7\Utils::tryFopen('', 'r');
    }

    public function testGetsContentsThrowExceptionWhenNotReadable(): void
    {
        $r = fopen(tempnam(sys_get_temp_dir(), 'guzzle-psr7-'), 'w');
        fwrite($r, 'hello world!');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unable to read stream contents');

        try {
            Psr7\Utils::tryGetContents($r);
        } finally {
            fclose($r);
        }
    }

    public function testGetsContentsThrowExceptionWhenCLosed(): void
    {
        $r = fopen(tempnam(sys_get_temp_dir(), 'guzzle-psr7-'), 'r+');
        fclose($r);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unable to read stream contents');

        Psr7\Utils::tryGetContents($r);
    }

    public function testTryGetContentsThrowsTimeoutWhenReadReturnsFalseAndResourceTimedOut(): void
    {
        PhpStreamMock::$streamGetContentsReturnsFalse = true;
        PhpStreamMock::$isStreamTimedOut = true;
        $resource = Psr7\Utils::tryFopen('php://temp', 'r+');

        $this->expectException(Psr7\Exception\TimeoutException::class);
        $this->expectExceptionMessage('Unable to read stream contents: timed out');

        try {
            Psr7\Utils::tryGetContents($resource);
        } finally {
            fclose($resource);
        }
    }

    public function testTryGetContentsThrowsTimeoutWhenPartialReadTimesOut(): void
    {
        PhpStreamMock::$streamGetContentsResult = 'partial';
        PhpStreamMock::$isStreamTimedOut = true;
        $resource = Psr7\Utils::tryFopen('php://temp', 'r+');

        $this->expectException(Psr7\Exception\TimeoutException::class);
        $this->expectExceptionMessage('Unable to read stream contents: timed out');

        try {
            Psr7\Utils::tryGetContents($resource);
        } finally {
            fclose($resource);
        }
    }

    public function testTryGetContentsWrapsReadFailureWhenResourceTimedOut(): void
    {
        $previous = new \ErrorException('read failed');
        PhpStreamMock::$streamGetContentsThrowable = $previous;
        PhpStreamMock::$isStreamTimedOut = true;
        $resource = Psr7\Utils::tryFopen('php://temp', 'r+');

        try {
            Psr7\Utils::tryGetContents($resource);
            self::fail('Expected timeout exception.');
        } catch (Psr7\Exception\TimeoutException $e) {
            self::assertSame('Unable to read stream contents: timed out', $e->getMessage());
            self::assertSame($previous, $e->getPrevious());
        } finally {
            fclose($resource);
        }
    }

    public function testCreatesUriForValue(): void
    {
        self::assertInstanceOf('GuzzleHttp\Psr7\Uri', Psr7\Utils::uriFor('/foo'));
        self::assertInstanceOf(
            'GuzzleHttp\Psr7\Uri',
            Psr7\Utils::uriFor(new Psr7\Uri('/foo'))
        );
    }

    public function testValidatesUri(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Psr7\Utils::uriFor([]);
    }

    public function testKeepsPositionOfResource(): void
    {
        $h = fopen(__FILE__, 'r');
        fseek($h, 10);
        $stream = Psr7\Utils::streamFor($h);
        self::assertSame(10, $stream->tell());
        $stream->close();
    }

    public function testCreatesWithFactory(): void
    {
        $stream = Psr7\Utils::streamFor('foo');
        self::assertInstanceOf('GuzzleHttp\Psr7\Stream', $stream);
        self::assertSame('foo', $stream->getContents());
        $stream->close();
    }

    public function testFactoryCreatesFromEmptyString(): void
    {
        $s = Psr7\Utils::streamFor();
        self::assertInstanceOf('GuzzleHttp\Psr7\Stream', $s);
    }

    public function testFactoryCreatesFromNull(): void
    {
        $s = Psr7\Utils::streamFor(null);
        self::assertInstanceOf('GuzzleHttp\Psr7\Stream', $s);
    }

    public function testFactoryCreatesFromResource(): void
    {
        $r = fopen(__FILE__, 'r');
        $s = Psr7\Utils::streamFor($r);
        self::assertInstanceOf('GuzzleHttp\Psr7\Stream', $s);
        self::assertSame(file_get_contents(__FILE__), (string) $s);
    }

    public function testFactoryCreatesFromObjectWithToString(): void
    {
        $r = new HasToString();
        $s = Psr7\Utils::streamFor($r);
        self::assertInstanceOf('GuzzleHttp\Psr7\Stream', $s);
        self::assertSame('foo', (string) $s);
    }

    public function testFactoryTreatsCallableStringAsStringBody(): void
    {
        $s = Psr7\Utils::streamFor('strlen');

        self::assertNotInstanceOf(Psr7\PumpStream::class, $s);
        self::assertSame('strlen', $s->getContents());
    }

    public function testFactoryCreatesFromCallableArray(): void
    {
        $source = new class {
            /** @var list<string|false> */
            private array $chunks = ['foo', false];

            /**
             * @return string|false
             */
            public function read(int $length)
            {
                if ($this->chunks === []) {
                    return false;
                }

                return array_shift($this->chunks);
            }
        };

        $s = Psr7\Utils::streamFor([$source, 'read']);

        self::assertInstanceOf(Psr7\PumpStream::class, $s);
        self::assertSame('foo', $s->getContents());
    }

    public function testFactoryCreatesFromInvokableObject(): void
    {
        $source = new class {
            /** @var list<string|false> */
            private array $chunks = ['foo', false];

            /**
             * @return string|false
             */
            public function __invoke(int $length)
            {
                if ($this->chunks === []) {
                    return false;
                }

                return array_shift($this->chunks);
            }
        };

        $s = Psr7\Utils::streamFor($source);

        self::assertInstanceOf(Psr7\PumpStream::class, $s);
        self::assertSame('foo', $s->getContents());
    }

    public function testCreatePassesThrough(): void
    {
        $s = Psr7\Utils::streamFor('foo');
        self::assertSame($s, Psr7\Utils::streamFor($s));
    }

    public function testThrowsExceptionForUnknown(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Psr7\Utils::streamFor(new \stdClass());
    }

    public function testReturnsCustomMetadata(): void
    {
        $s = Psr7\Utils::streamFor('foo', ['metadata' => ['hwm' => 3]]);
        self::assertSame(3, $s->getMetadata('hwm'));
        self::assertArrayHasKey('hwm', $s->getMetadata());
    }

    public function testCustomUriMetadataDoesNotAffectStreamSize(): void
    {
        $s = Psr7\Utils::streamFor('foo', ['metadata' => ['uri' => []]]);

        self::assertSame([], $s->getMetadata('uri'));
        self::assertSame(3, $s->getSize());
    }

    public function testCanSetSize(): void
    {
        $s = Psr7\Utils::streamFor('', ['size' => 10]);
        self::assertSame(10, $s->getSize());
    }

    /**
     * @dataProvider invalidStreamSizes
     *
     * @param mixed $size
     */
    public function testStreamForRejectsInvalidSizeOption($size): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Stream size must be a non-negative integer or null');

        Psr7\Utils::streamFor('', ['size' => $size]);
    }

    public static function invalidStreamSizes(): iterable
    {
        yield 'negative integer' => [-1];
        yield 'string integer' => ['10'];
        yield 'float' => [10.0];
    }

    /**
     * @dataProvider nonStringScalars
     *
     * @param mixed $value
     */
    public function testStreamForRejectsNonStringScalar($value): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot create a stream from');

        Psr7\Utils::streamFor($value);
    }

    public static function nonStringScalars(): iterable
    {
        yield 'int' => [1];
        yield 'float' => [1.5];
        yield 'bool' => [true];
        yield 'NAN' => [\NAN];
        yield 'INF' => [\INF];
        yield '-INF' => [-\INF];
    }

    public function testCanCreateIteratorBasedStream(): void
    {
        $a = new \ArrayIterator(['foo', 'bar', '123']);
        $p = Psr7\Utils::streamFor($a);
        self::assertInstanceOf('GuzzleHttp\Psr7\PumpStream', $p);
        self::assertSame('foo', $p->read(3));
        self::assertFalse($p->eof());
        self::assertSame('b', $p->read(1));
        self::assertSame('a', $p->read(1));
        self::assertSame('r12', $p->read(3));
        self::assertFalse($p->eof());
        self::assertSame('3', $p->getContents());
        self::assertTrue($p->eof());
        self::assertSame(9, $p->tell());
    }

    public function testIteratorBasedStreamDoesNotTreatFalseAsEof(): void
    {
        $stream = Psr7\Utils::streamFor(new \ArrayIterator([false, 'x']));

        self::assertSame('x', $stream->getContents());
    }

    public function testIteratorBasedStreamDoesNotTreatNullAsEof(): void
    {
        $stream = Psr7\Utils::streamFor(new \ArrayIterator([null, 'x']));

        self::assertSame('x', $stream->getContents());
    }

    public function testIteratorBasedStreamCastsScalarValuesToStrings(): void
    {
        $stream = Psr7\Utils::streamFor(new \ArrayIterator([1, 2.5, true, false, 'x']));

        self::assertSame('12.51x', $stream->getContents());
    }

    public function testIteratorBasedStreamCastsStringableObjectsToStrings(): void
    {
        $value = new class {
            public function __toString(): string
            {
                return 'foo';
            }
        };

        $stream = Psr7\Utils::streamFor(new \ArrayIterator([$value, 'bar']));

        self::assertSame('foobar', $stream->getContents());
    }

    public function testIteratorBasedStreamSkipsEmptyConvertedChunks(): void
    {
        $emptyStringable = new class {
            public function __toString(): string
            {
                return '';
            }
        };

        $stream = Psr7\Utils::streamFor(new \ArrayIterator([
            '',
            false,
            null,
            $emptyStringable,
            'foo',
            '',
            'bar',
            null,
        ]));

        self::assertSame('foobar', $stream->getContents());
        self::assertTrue($stream->eof());
    }

    public function testIteratorBasedStreamTerminatesWhenOnlyEmptyConvertedChunksAreYielded(): void
    {
        $stream = Psr7\Utils::streamFor(new \ArrayIterator(['', false, null]));

        self::assertSame('', $stream->getContents());
        self::assertTrue($stream->eof());
    }

    public function testCallableStreamCloseClearsBufferedBytes(): void
    {
        $stream = Psr7\Utils::streamFor(static function (): string {
            return 'abc';
        });

        self::assertSame('a', $stream->read(1));

        $stream->close();

        self::assertSame('', $stream->read(10));
        self::assertTrue($stream->eof());
    }

    public function testIteratorStreamCloseClearsBufferedBytes(): void
    {
        $stream = Psr7\Utils::streamFor(new \ArrayIterator(['abc']));

        self::assertSame('a', $stream->read(1));

        $stream->close();

        self::assertSame('', $stream->read(10));
        self::assertTrue($stream->eof());
    }

    public function testIteratorBasedStreamRejectsArrayValues(): void
    {
        $stream = Psr7\Utils::streamFor(new \ArrayIterator([['x']]));

        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage('Iterator must yield scalar, null, or stringable values');

        $stream->getContents();
    }

    public function testIteratorBasedStreamRejectsNonStringableObjects(): void
    {
        $stream = Psr7\Utils::streamFor(new \ArrayIterator([new \stdClass()]));

        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage('Iterator must yield scalar, null, or stringable values');

        $stream->getContents();
    }

    /**
     * @dataProvider nonFiniteIteratorChunkProvider
     */
    public function testIteratorBasedStreamRejectsNonFiniteFloatValues(float $value): void
    {
        $stream = Psr7\Utils::streamFor(new \ArrayIterator([$value]));

        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage('Iterator must not yield non-finite float values');

        $stream->getContents();
    }

    public static function nonFiniteIteratorChunkProvider(): iterable
    {
        yield 'NAN' => [\NAN];
        yield 'INF' => [\INF];
        yield '-INF' => [-\INF];
    }

    public function testIteratorBasedStreamRejectsNonFiniteFloatAfterValidChunks(): void
    {
        $stream = Psr7\Utils::streamFor(new \ArrayIterator(['foo', \NAN]));

        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage('Iterator must not yield non-finite float values');

        $stream->getContents();
    }

    public function testConvertsRequestsToStrings(): void
    {
        $request = new Psr7\Request('PUT', 'http://foo.com/hi?123', [
            'Baz' => 'bar',
            'Qux' => 'ipsum',
        ], 'hello', '1.0');
        self::assertSame(
            "PUT /hi?123 HTTP/1.0\r\nHost: foo.com\r\nBaz: bar\r\nQux: ipsum\r\n\r\nhello",
            Psr7\Message::toString($request)
        );
    }

    public function testConvertsResponsesToStrings(): void
    {
        $response = new Psr7\Response(200, [
            'Baz' => 'bar',
            'Qux' => 'ipsum',
        ], 'hello', '1.0', 'FOO');
        self::assertSame(
            "HTTP/1.0 200 FOO\r\nBaz: bar\r\nQux: ipsum\r\n\r\nhello",
            Psr7\Message::toString($response)
        );
    }

    public function testCorrectlyRendersSetCookieHeadersToString(): void
    {
        $response = new Psr7\Response(200, [
            'Set-Cookie' => ['bar', 'baz', 'qux'],
        ], 'hello', '1.0', 'FOO');
        self::assertSame(
            "HTTP/1.0 200 FOO\r\nSet-Cookie: bar\r\nSet-Cookie: baz\r\nSet-Cookie: qux\r\n\r\nhello",
            Psr7\Message::toString($response)
        );
    }

    /**
     * @return list<array{0: string[], 1: array, 2: array}>
     */
    public static function providesCaselessRemoveCases(): array
    {
        return [
            [
                ['foo-bar'],
                ['Foo-Bar' => 'hello'],
                [],
            ],
            [
                ['foo-bar'],
                ['hello'],
                ['hello'],
            ],
            [
                ['foo-Bar'],
                ['Foo-Bar' => 'hello', 123 => '', 'Foo-BAR' => 'hello123', 'foobar' => 'baz'],
                [123 => '', 'foobar' => 'baz'],
            ],
            [
                ['foo-Bar', 123],
                ['Foo-Bar' => 'hello', 123 => '', 'Foo-BAR' => 'hello123', 'foobar' => 'baz'],
                ['foobar' => 'baz'],
            ],
        ];
    }

    /**
     * @dataProvider providesCaselessRemoveCases
     *
     * @param string[] $keys
     */
    public function testCaselessRemove(array $keys, array $data, array $expected): void
    {
        self::assertSame($expected, Psr7\Utils::caselessRemove($keys, $data));
    }
}
