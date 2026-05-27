<?php

declare(strict_types=1);

namespace GuzzleHttp\Tests\Psr7;

use GuzzleHttp\Psr7;
use GuzzleHttp\Psr7\AppendStream;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\StreamInterface;

class AppendStreamTest extends TestCase
{
    public function testValidatesStreamsAreReadable(): void
    {
        $a = new AppendStream();
        $s = $this->createMock(StreamInterface::class);
        $s->expects(self::once())
            ->method('isReadable')
            ->willReturn(false);
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Each stream must be readable');
        $a->addStream($s);
    }

    public function testValidatesSeekType(): void
    {
        $a = new AppendStream();
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('The AppendStream can only seek with SEEK_SET');
        $a->seek(100, SEEK_CUR);
    }

    public function testTriesToRewindOnSeek(): void
    {
        $a = new AppendStream();
        $s = $this->createMock(StreamInterface::class);
        $s->expects(self::once())
            ->method('isReadable')
            ->willReturn(true);
        $s->expects(self::once())
            ->method('isSeekable')
            ->willReturn(true);
        $s->expects(self::once())
            ->method('rewind')
            ->willThrowException(new \RuntimeException());
        $a->addStream($s);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unable to seek stream 0 of the AppendStream');
        $a->seek(10);
    }

    public function testSeeksToPositionByReading(): void
    {
        $a = new AppendStream([
            Psr7\Utils::streamFor('foo'),
            Psr7\Utils::streamFor('bar'),
            Psr7\Utils::streamFor('baz'),
        ]);

        $a->seek(3);
        self::assertSame(3, $a->tell());
        self::assertSame('bar', $a->read(3));

        $a->seek(6);
        self::assertSame(6, $a->tell());
        self::assertSame('baz', $a->read(3));
    }

    public function testDetachWithoutStreams(): void
    {
        $s = new AppendStream();
        $s->detach();

        self::assertSame(0, $s->getSize());
        self::assertTrue($s->eof());
        self::assertTrue($s->isReadable());
        self::assertSame('', (string) $s);
        self::assertTrue($s->isSeekable());
        self::assertFalse($s->isWritable());
    }

    public function testDetachesEachStream(): void
    {
        $handle = fopen('php://temp', 'r');

        $s1 = Psr7\Utils::streamFor($handle);
        $s2 = Psr7\Utils::streamFor('bar');
        $a = new AppendStream([$s1, $s2]);

        $a->detach();

        self::assertSame(0, $a->getSize());
        self::assertTrue($a->eof());
        self::assertTrue($a->isReadable());
        self::assertSame('', (string) $a);
        self::assertTrue($a->isSeekable());
        self::assertFalse($a->isWritable());

        self::assertNull($s1->detach());
        self::assertIsResource($handle, 'resource is not closed when detaching');
        fclose($handle);
    }

    public function testClosesEachStream(): void
    {
        $handle = fopen('php://temp', 'r');

        $s1 = Psr7\Utils::streamFor($handle);
        $s2 = Psr7\Utils::streamFor('bar');
        $a = new AppendStream([$s1, $s2]);

        $a->close();

        self::assertSame(0, $a->getSize());
        self::assertTrue($a->eof());
        self::assertTrue($a->isReadable());
        self::assertSame('', (string) $a);
        self::assertTrue($a->isSeekable());
        self::assertFalse($a->isWritable());

        self::assertFalse(is_resource($handle));
    }

    public function testIsNotWritable(): void
    {
        $a = new AppendStream([Psr7\Utils::streamFor('foo')]);
        self::assertFalse($a->isWritable());
        self::assertTrue($a->isSeekable());
        self::assertTrue($a->isReadable());
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cannot write to an AppendStream');
        $a->write('foo');
    }

    public function testDoesNotNeedStreams(): void
    {
        $a = new AppendStream();
        self::assertSame('', (string) $a);
    }

    public function testReadWithoutStreamsReturnsEmptyString(): void
    {
        $stream = new AppendStream();

        self::assertSame('', $stream->read(1));
        self::assertSame(0, $stream->tell());
        self::assertTrue($stream->eof());
    }

    public function testReadAfterCloseReturnsEmptyString(): void
    {
        $stream = new AppendStream([Psr7\Utils::streamFor('foo')]);

        $stream->close();

        self::assertSame('', $stream->read(1));
        self::assertTrue($stream->eof());
    }

    public function testReadAfterDetachReturnsEmptyString(): void
    {
        $stream = new AppendStream([Psr7\Utils::streamFor('foo')]);

        $stream->detach();

        self::assertSame('', $stream->read(1));
        self::assertTrue($stream->eof());
    }

    public function testCanReadFromMultipleStreams(): void
    {
        $a = new AppendStream([
            Psr7\Utils::streamFor('foo'),
            Psr7\Utils::streamFor('bar'),
            Psr7\Utils::streamFor('baz'),
        ]);
        self::assertFalse($a->eof());
        self::assertSame(0, $a->tell());
        self::assertSame('foo', $a->read(3));
        self::assertSame('bar', $a->read(3));
        self::assertSame('baz', $a->read(3));
        self::assertSame('', $a->read(1));
        self::assertTrue($a->eof());
        self::assertSame(9, $a->tell());
        self::assertSame('foobarbaz', (string) $a);
    }

    public function testCanDetermineSizeFromMultipleStreams(): void
    {
        $a = new AppendStream([
            Psr7\Utils::streamFor('foo'),
            Psr7\Utils::streamFor('bar'),
        ]);
        self::assertSame(6, $a->getSize());

        $s = $this->createMock(StreamInterface::class);
        $s->expects(self::once())
            ->method('isSeekable')
            ->willReturn(false);
        $s->expects(self::once())
            ->method('isReadable')
            ->willReturn(true);
        $a->addStream($s);
        self::assertNull($a->getSize());
    }

    /**
     * @requires PHP < 7.4
     */
    public function testCatchesExceptionsWhenCastingToString(): void
    {
        $s = $this->createMock(StreamInterface::class);
        $s->expects(self::once())
            ->method('isSeekable')
            ->willReturn(true);
        $s->expects(self::once())
            ->method('read')
            ->willThrowException(new \RuntimeException('foo'));
        $s->expects(self::once())
            ->method('isReadable')
            ->willReturn(true);
        $s->expects(self::any())
            ->method('eof')
            ->willReturn(false);
        $a = new AppendStream([$s]);
        self::assertFalse($a->eof());

        $errors = [];
        set_error_handler(static function (int $errorNumber, string $errorMessage) use (&$errors): bool {
            $errors[] = ['number' => $errorNumber, 'message' => $errorMessage];

            return true;
        });
        (string) $a;

        restore_error_handler();

        self::assertCount(1, $errors);
        self::assertSame(E_USER_ERROR, $errors[0]['number']);
        self::assertStringStartsWith('GuzzleHttp\Psr7\AppendStream::__toString exception:', $errors[0]['message']);
    }

    public function testReturnsEmptyMetadata(): void
    {
        $s = new AppendStream();
        self::assertSame([], $s->getMetadata());
        self::assertNull($s->getMetadata('foo'));
    }
}
