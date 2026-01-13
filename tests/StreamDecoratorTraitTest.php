<?php

declare(strict_types=1);

namespace GuzzleHttp\Tests\Psr7;

use GuzzleHttp\Psr7;
use GuzzleHttp\Psr7\StreamDecoratorTrait;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\StreamInterface;

class Str implements StreamInterface
{
    use StreamDecoratorTrait;

    /** @var StreamInterface */
    private $stream;
}

/**
 * @covers \GuzzleHttp\Psr7\StreamDecoratorTrait
 */
class StreamDecoratorTraitTest extends TestCase
{
    /** @var StreamInterface */
    private $a;
    /** @var StreamInterface */
    private $b;
    /** @var resource */
    private $c;

    protected function setUp(): void
    {
        $this->c = fopen('php://temp', 'r+');
        fwrite($this->c, 'foo');
        fseek($this->c, 0);
        $this->a = Psr7\Utils::streamFor($this->c);
        $this->b = new Str($this->a);
    }

    /**
     * @requires PHP < 7.4
     */
    public function testCatchesExceptionsWhenCastingToString(): void
    {
        $s = $this->createMock(Str::class);
        $s->expects(self::once())
            ->method('read')
            ->willThrowException(new \RuntimeException('foo'));

        $this->expectException(\Error::class);
        $this->expectExceptionMessageMatches('/foo/');
        (string) new Str($s);
    }

    public function testToString(): void
    {
        self::assertSame('foo', (string) $this->b);
    }

    public function testHasSize(): void
    {
        self::assertSame(3, $this->b->getSize());
    }

    public function testReads(): void
    {
        self::assertSame('foo', $this->b->read(10));
    }

    public function testCheckMethods(): void
    {
        self::assertSame($this->a->isReadable(), $this->b->isReadable());
        self::assertSame($this->a->isWritable(), $this->b->isWritable());
        self::assertSame($this->a->isSeekable(), $this->b->isSeekable());
    }

    public function testSeeksAndTells(): void
    {
        $this->b->seek(1);
        self::assertSame(1, $this->a->tell());
        self::assertSame(1, $this->b->tell());
        $this->b->seek(0);
        self::assertSame(0, $this->a->tell());
        self::assertSame(0, $this->b->tell());
        $this->b->seek(0, SEEK_END);
        self::assertSame(3, $this->a->tell());
        self::assertSame(3, $this->b->tell());
    }

    public function testGetsContents(): void
    {
        self::assertSame('foo', $this->b->getContents());
        self::assertSame('', $this->b->getContents());
        $this->b->seek(1);
        self::assertSame('oo', $this->b->getContents());
    }

    public function testCloses(): void
    {
        $this->b->close();
        self::assertFalse(is_resource($this->c));
    }

    public function testDetaches(): void
    {
        $this->b->detach();
        self::assertFalse($this->b->isReadable());
    }

    public function testWrapsMetadata(): void
    {
        self::assertSame($this->b->getMetadata(), $this->a->getMetadata());
        self::assertSame($this->b->getMetadata('uri'), $this->a->getMetadata('uri'));
    }

    public function testWrapsWrites(): void
    {
        $this->b->seek(0, SEEK_END);
        $this->b->write('foo');
        self::assertSame('foofoo', (string) $this->a);
    }

    public function testThrowsWithInvalidGetter(): void
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->b->foo;
    }

    public function testThrowsWhenGetterNotImplemented(): void
    {
        $this->expectException(\BadMethodCallException::class);
        $s = new BadStream();
        $s->stream;
    }
}

class BadStream
{
    use StreamDecoratorTrait;

    public function __construct()
    {
    }
}
