<?php

declare(strict_types=1);

namespace GuzzleHttp\Tests\Psr7;

use GuzzleHttp\Psr7\NoSeekStream;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\StreamInterface;

/**
 * @covers \GuzzleHttp\Psr7\NoSeekStream
 * @covers \GuzzleHttp\Psr7\StreamDecoratorTrait
 */
class NoSeekStreamTest extends TestCase
{
    public function testCannotSeek(): void
    {
        $s = $this->createMock(StreamInterface::class);
        $s->expects(self::never())->method('seek');
        $s->expects(self::never())->method('isSeekable');
        $wrapped = new NoSeekStream($s);
        self::assertFalse($wrapped->isSeekable());
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cannot seek a NoSeekStream');
        $wrapped->seek(2);
    }

    public function testToStringDoesNotSeek(): void
    {
        $s = \GuzzleHttp\Psr7\Utils::streamFor('foo');
        $s->seek(1);
        $wrapped = new NoSeekStream($s);
        self::assertSame('oo', (string) $wrapped);

        $wrapped->close();
    }

    public function testDetachDelegatesToDecoratedStream(): void
    {
        $resource = fopen('php://temp', 'r+');
        $wrapped = new NoSeekStream(\GuzzleHttp\Psr7\Utils::streamFor($resource));

        self::assertFalse($wrapped->isSeekable());
        self::assertSame($resource, $wrapped->detach());
        self::assertNull($wrapped->detach());

        fclose($resource);
    }
}
