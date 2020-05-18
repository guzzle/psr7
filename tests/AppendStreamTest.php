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
            Psr7\stream_for('foo'),
            Psr7\stream_for('bar'),
            Psr7\stream_for('baz'),
        ]);

        $a->seek(3);
        self::assertEquals(3, $a->tell());
        self::assertEquals('bar', $a->read(3));

        $a->seek(6);
        self::assertEquals(6, $a->tell());
        self::assertEquals('baz', $a->read(3));
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

        $s1 = Psr7\stream_for($handle);
        $s2 = Psr7\stream_for('bar');
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

        $s1 = Psr7\stream_for($handle);
        $s2 = Psr7\stream_for('bar');
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
        $a = new AppendStream([Psr7\stream_for('foo')]);
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
        self::assertEquals('', (string) $a);
    }

    public function testCanReadFromMultipleStreams(): void
    {
        $a = new AppendStream([
            Psr7\stream_for('foo'),
            Psr7\stream_for('bar'),
            Psr7\stream_for('baz'),
        ]);
        self::assertFalse($a->eof());
        self::assertSame(0, $a->tell());
        self::assertEquals('foo', $a->read(3));
        self::assertEquals('bar', $a->read(3));
        self::assertEquals('baz', $a->read(3));
        self::assertSame('', $a->read(1));
        self::assertTrue($a->eof());
        self::assertSame(9, $a->tell());
        self::assertEquals('foobarbaz', (string) $a);
    }

    public function testCanDetermineSizeFromMultipleStreams(): void
    {
        $a = new AppendStream([
            Psr7\stream_for('foo'),
            Psr7\stream_for('bar')
        ]);
        self::assertEquals(6, $a->getSize());

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
            return false;
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
        self::assertEquals([], $s->getMetadata());
        self::assertNull($s->getMetadata('foo'));
    }
}
