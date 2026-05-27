<?php

declare(strict_types=1);

namespace GuzzleHttp\Tests\Psr7;

use GuzzleHttp\Psr7;
use GuzzleHttp\Psr7\FnStream;
use GuzzleHttp\Psr7\LimitStream;
use GuzzleHttp\Psr7\NoSeekStream;
use GuzzleHttp\Psr7\Stream;
use PHPUnit\Framework\TestCase;

/**
 * @covers \GuzzleHttp\Psr7\LimitStream
 */
class LimitStreamTest extends TestCase
{
    /** @var LimitStream */
    private $body;

    /** @var Stream */
    private $decorated;

    protected function setUp(): void
    {
        $this->decorated = Psr7\Utils::streamFor(fopen(__FILE__, 'r'));
        $this->body = new LimitStream($this->decorated, 10, 3);
    }

    public function testReturnsSubset(): void
    {
        $body = new LimitStream(Psr7\Utils::streamFor('foo'), -1, 1);
        self::assertSame('oo', (string) $body);
        self::assertTrue($body->eof());
        $body->seek(0);
        self::assertFalse($body->eof());
        self::assertSame('oo', $body->read(100));
        self::assertSame('', $body->read(1));
        self::assertTrue($body->eof());
    }

    public function testReturnsSubsetWhenCastToString(): void
    {
        $body = Psr7\Utils::streamFor('foo_baz_bar');
        $limited = new LimitStream($body, 3, 4);
        self::assertSame('baz', (string) $limited);
    }

    public function testReturnsSubsetOfEmptyBodyWhenCastToString(): void
    {
        $body = Psr7\Utils::streamFor('01234567891234');
        $limited = new LimitStream($body, 0, 10);
        self::assertSame('', (string) $limited);
    }

    public function testReturnsSpecificSubsetOBodyWhenCastToString(): void
    {
        $body = Psr7\Utils::streamFor('0123456789abcdef');
        $limited = new LimitStream($body, 3, 10);
        self::assertSame('abc', (string) $limited);
    }

    public function testSeeksWhenConstructed(): void
    {
        self::assertSame(0, $this->body->tell());
        self::assertSame(3, $this->decorated->tell());
    }

    public function testAllowsBoundedSeek(): void
    {
        $this->body->seek(100);
        self::assertSame(10, $this->body->tell());
        self::assertSame(13, $this->decorated->tell());
        $this->body->seek(0);
        self::assertSame(0, $this->body->tell());
        self::assertSame(3, $this->decorated->tell());
        try {
            $this->body->seek(-10);
            self::fail();
        } catch (\RuntimeException $e) {
        }
        self::assertSame(0, $this->body->tell());
        self::assertSame(3, $this->decorated->tell());
        $this->body->seek(5);
        self::assertSame(5, $this->body->tell());
        self::assertSame(8, $this->decorated->tell());
        // Fail
        try {
            $this->body->seek(1000, SEEK_END);
            self::fail();
        } catch (\RuntimeException $e) {
        }
    }

    public function testReadsOnlySubsetOfData(): void
    {
        $data = $this->body->read(100);
        self::assertSame(10, strlen($data));
        self::assertSame('', $this->body->read(1000));

        $this->body->setOffset(10);
        $newData = $this->body->read(100);
        self::assertSame(10, strlen($newData));
        self::assertNotSame($data, $newData);
    }

    public function testThrowsWhenCurrentGreaterThanOffsetSeek(): void
    {
        $a = Psr7\Utils::streamFor('foo_bar');
        $b = new NoSeekStream($a);
        $c = new LimitStream($b);
        $a->getContents();
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Could not seek to stream offset 2');
        $c->setOffset(2);
    }

    public function testCanGetContentsWithoutSeeking(): void
    {
        $a = Psr7\Utils::streamFor('foo_bar');
        $b = new NoSeekStream($a);
        $c = new LimitStream($b);
        self::assertSame('foo_bar', $c->getContents());
    }

    public function testClaimsConsumedWhenReadLimitIsReached(): void
    {
        self::assertFalse($this->body->eof());
        $this->body->read(1000);
        self::assertTrue($this->body->eof());
    }

    public function testContentLengthIsBounded(): void
    {
        self::assertSame(10, $this->body->getSize());
    }

    public function testGetContentsIsBasedOnSubset(): void
    {
        $body = new LimitStream(Psr7\Utils::streamFor('foobazbar'), 3, 3);
        self::assertSame('baz', $body->getContents());
    }

    public function testReturnsNullIfSizeCannotBeDetermined(): void
    {
        $a = new FnStream([
            'getSize' => function () {
                return null;
            },
            'tell' => function () {
                return 0;
            },
        ]);
        $b = new LimitStream($a);
        self::assertNull($b->getSize());
    }

    public function testLengthLessOffsetWhenNoLimitSize(): void
    {
        $a = Psr7\Utils::streamFor('foo_bar');
        $b = new LimitStream($a, -1, 4);
        self::assertSame(3, $b->getSize());
    }

    public function testSizeIsZeroWhenOffsetExceedsUnderlyingSizeWithoutLimit(): void
    {
        $a = new NoSeekStream(Psr7\Utils::streamFor('foo'));
        $b = new LimitStream($a, -1, 4);
        self::assertSame(0, $b->getSize());
    }

    public function testSizeIsZeroWhenOffsetExceedsUnderlyingSizeWithLimit(): void
    {
        $a = new NoSeekStream(Psr7\Utils::streamFor('foo'));
        $b = new LimitStream($a, 5, 4);
        self::assertSame(0, $b->getSize());
    }
}
