<?php

declare(strict_types=1);

namespace GuzzleHttp\Tests\Psr7;

use GuzzleHttp\Psr7;
use GuzzleHttp\Psr7\CachingStream;
use GuzzleHttp\Psr7\Stream;
use PHPUnit\Framework\TestCase;

/**
 * @covers \GuzzleHttp\Psr7\CachingStream
 */
class CachingStreamTest extends TestCase
{
    /** @var CachingStream */
    private $body;
    /** @var Stream */
    private $decorated;

    protected function setUp(): void
    {
        $this->decorated = Psr7\Utils::streamFor('testing');
        $this->body = new CachingStream($this->decorated);
    }

    protected function tearDown(): void
    {
        $this->decorated->close();
        $this->body->close();
    }

    public function testUsesRemoteSizeIfAvailable(): void
    {
        $body = Psr7\Utils::streamFor('test');
        $caching = new CachingStream($body);
        self::assertSame(4, $caching->getSize());
    }

    public function testUsesRemoteSizeIfNotAvailable(): void
    {
        $body = new Psr7\PumpStream(function () {
            return 'a';
        });
        $caching = new CachingStream($body);
        self::assertNull($caching->getSize());
    }

    public function testReadsUntilCachedToByte(): void
    {
        $this->body->seek(5);
        self::assertSame('n', $this->body->read(1));
        $this->body->seek(0);
        self::assertSame('t', $this->body->read(1));
    }

    public function testCanSeekNearEndWithSeekEnd(): void
    {
        $baseStream = Psr7\Utils::streamFor(implode('', range('a', 'z')));
        $cached = new CachingStream($baseStream);
        $cached->seek(-1, SEEK_END);
        self::assertSame(25, $baseStream->tell());
        self::assertSame('z', $cached->read(1));
        self::assertSame(26, $cached->getSize());
    }

    public function testCanSeekToEndWithSeekEnd(): void
    {
        $baseStream = Psr7\Utils::streamFor(implode('', range('a', 'z')));
        $cached = new CachingStream($baseStream);
        $cached->seek(0, SEEK_END);
        self::assertSame(26, $baseStream->tell());
        self::assertSame('', $cached->read(1));
        self::assertSame(26, $cached->getSize());
    }

    public function testCanUseSeekEndWithUnknownSize(): void
    {
        $baseStream = Psr7\Utils::streamFor('testing');
        $decorated = Psr7\FnStream::decorate($baseStream, [
            'getSize' => function () {
                return null;
            },
        ]);
        $cached = new CachingStream($decorated);
        $cached->seek(-1, SEEK_END);
        self::assertSame('g', $cached->read(1));
    }

    public function testRewind(): void
    {
        $a = Psr7\Utils::streamFor('foo');
        $d = new CachingStream($a);
        self::assertSame('foo', $d->read(3));
        $d->rewind();
        self::assertSame('foo', $d->read(3));
    }

    public function testCanSeekToReadBytes(): void
    {
        self::assertSame('te', $this->body->read(2));
        $this->body->seek(0);
        self::assertSame('test', $this->body->read(4));
        self::assertSame(4, $this->body->tell());
        $this->body->seek(2);
        self::assertSame(2, $this->body->tell());
        $this->body->seek(2, SEEK_CUR);
        self::assertSame(4, $this->body->tell());
        self::assertSame('ing', $this->body->read(3));
    }

    public function testCanSeekToReadBytesWithPartialBodyReturned(): void
    {
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, 'testing');
        fseek($stream, 0);

        $this->decorated = $this->getMockBuilder(Stream::class)
            ->setConstructorArgs([$stream])
            ->onlyMethods(['read'])
            ->getMock();

        $this->decorated->expects(self::exactly(2))
            ->method('read')
            ->willReturnCallback(function (int $length) use ($stream) {
                return fread($stream, 2);
            });

        $this->body = new CachingStream($this->decorated);

        self::assertSame(0, $this->body->tell());
        $this->body->seek(4, SEEK_SET);
        self::assertSame(4, $this->body->tell());

        $this->body->seek(0);
        self::assertSame('test', $this->body->read(4));
    }

    public function testReadThrowsWhenCacheTargetDoesNotPersistEntireWrite(): void
    {
        $remote = Psr7\Utils::streamFor('ABCDEFGHIJ');
        $lossyCache = Psr7\FnStream::decorate(Psr7\Utils::streamFor(''), [
            'write' => function (string $string): int {
                return max(0, strlen($string) - 1);
            },
        ]);
        $stream = new CachingStream($remote, $lossyCache);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unable to cache the entire read from the remote stream');

        $stream->read(10);
    }

    public function testSeekStopsWhenRemoteReadMakesNoProgress(): void
    {
        $reads = 0;
        $remote = new Psr7\FnStream([
            'read' => function () use (&$reads): string {
                ++$reads;

                return '';
            },
            'eof' => function (): bool {
                return false;
            },
            'getSize' => function (): int {
                return 10;
            },
            'close' => function (): void {
            },
        ]);
        $stream = new CachingStream($remote);

        $stream->seek(1);

        self::assertSame(1, $reads);
        self::assertSame(0, $stream->tell());
        $stream->close();
    }

    public function testSeekContinuesWhenRemoteReadOnlySatisfiesSkippedBytes(): void
    {
        $chunks = ['ab', 'cd'];
        $remote = new Psr7\FnStream([
            'read' => function () use (&$chunks): string {
                return $chunks === [] ? '' : array_shift($chunks);
            },
            'eof' => function (): bool {
                return false;
            },
            'getSize' => function (): int {
                return 4;
            },
            'tell' => function (): int {
                return 0;
            },
            'close' => function (): void {
            },
        ]);
        $stream = new CachingStream($remote);

        self::assertSame(2, $stream->write('XX'));
        $stream->seek(4);

        self::assertSame(4, $stream->tell());
        $stream->seek(0);
        self::assertSame('XXcd', $stream->read(4));
        $stream->close();
    }

    public function testWritesToBufferStream(): void
    {
        $this->body->read(2);
        $this->body->write('hi');
        $this->body->seek(0);
        self::assertSame('tehiing', (string) $this->body);
    }

    public function testDetachReturnsCompleteResourceWithoutPriorRead(): void
    {
        $body = new CachingStream(Psr7\Utils::streamFor('Hello world!'));

        $resource = $body->detach();

        self::assertIsResource($resource);
        self::assertSame(0, ftell($resource));

        $stats = fstat($resource);
        self::assertIsArray($stats);
        self::assertSame(strlen('Hello world!'), $stats['size']);
        self::assertSame('Hello world!', stream_get_contents($resource));

        fclose($resource);
        $body->close();
    }

    public function testDetachReturnsCompleteResourceAfterPartialRead(): void
    {
        $body = new CachingStream(Psr7\Utils::streamFor('Hello world!'));

        self::assertSame('Hello ', $body->read(6));

        $resource = $body->detach();

        self::assertIsResource($resource);
        self::assertSame(6, ftell($resource));
        self::assertSame('world!', stream_get_contents($resource));

        rewind($resource);
        self::assertSame('Hello world!', stream_get_contents($resource));

        fclose($resource);
        $body->close();
    }

    public function testDetachPreservesCachedWritesAndUnreadRemoteBytes(): void
    {
        $body = new CachingStream(Psr7\Utils::streamFor('testing'));

        self::assertSame('te', $body->read(2));
        self::assertSame(2, $body->write('hi'));
        $body->seek(0);

        $resource = $body->detach();

        self::assertIsResource($resource);
        self::assertSame(0, ftell($resource));
        self::assertSame('tehiing', stream_get_contents($resource));

        fclose($resource);
        $body->close();
    }

    public function testDetachReturnsNullAfterDetach(): void
    {
        $body = new CachingStream(Psr7\Utils::streamFor('testing'));

        $resource = $body->detach();

        self::assertIsResource($resource);
        fclose($resource);

        self::assertNull($body->detach());

        $body->close();
    }

    public function testDetachReturnsNullAfterClose(): void
    {
        $body = new CachingStream(Psr7\Utils::streamFor('testing'));

        $body->close();

        self::assertNull($body->detach());
    }

    public function testSkipsOverwrittenBytes(): void
    {
        $decorated = Psr7\Utils::streamFor(
            implode("\n", array_map(function ($n) {
                return str_pad((string) $n, 4, '0', STR_PAD_LEFT);
            }, range(0, 25)))
        );

        $body = new CachingStream($decorated);

        self::assertSame("0000\n", Psr7\Utils::readLine($body));
        self::assertSame("0001\n", Psr7\Utils::readLine($body));
        // Write over part of the body yet to be read, so skip some bytes
        self::assertSame(5, $body->write("TEST\n"));
        // Read, which skips bytes, then reads
        self::assertSame("0003\n", Psr7\Utils::readLine($body));
        self::assertSame("0004\n", Psr7\Utils::readLine($body));
        self::assertSame("0005\n", Psr7\Utils::readLine($body));

        // Overwrite part of the cached body (so don't skip any bytes)
        $body->seek(5);
        self::assertSame(5, $body->write("ABCD\n"));
        self::assertSame("TEST\n", Psr7\Utils::readLine($body));
        self::assertSame("0003\n", Psr7\Utils::readLine($body));
        self::assertSame("0004\n", Psr7\Utils::readLine($body));
        self::assertSame("0005\n", Psr7\Utils::readLine($body));
        self::assertSame("0006\n", Psr7\Utils::readLine($body));
        self::assertSame(5, $body->write("1234\n"));

        // Seek to 0 and ensure the overwritten bit is replaced
        $body->seek(0);
        self::assertSame("0000\nABCD\nTEST\n0003\n0004\n0005\n0006\n1234\n0008\n0009\n", $body->read(50));

        // Ensure that casting it to a string does not include the bit that was overwritten
        self::assertStringContainsString("0000\nABCD\nTEST\n0003\n0004\n0005\n0006\n1234\n0008\n0009\n", (string) $body);
    }

    public function testClosesBothStreams(): void
    {
        $s = fopen('php://temp', 'r');
        $a = Psr7\Utils::streamFor($s);
        $d = new CachingStream($a);
        $d->close();
        self::assertFalse(is_resource($s));
    }

    public function testEnsuresValidWhence(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid whence');
        $this->body->seek(10, -123456);
    }

    public function testEnsuresSeekCurTargetIsNonNegative(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->body->seek(-1, SEEK_CUR);
    }

    public function testEnsuresSeekEndTargetIsNonNegative(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->body->seek(-100, SEEK_END);
    }
}
