<?php

declare(strict_types=1);

namespace GuzzleHttp\Tests\Psr7;

use GuzzleHttp\Psr7;
use GuzzleHttp\Psr7\FnStream;
use PHPUnit\Framework\TestCase;

/**
 * @covers \GuzzleHttp\Psr7\FnStream
 */
class FnStreamTest extends TestCase
{
    public function testThrowsWhenNotImplemented(): void
    {
        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage('seek() is not implemented in the FnStream');
        (new FnStream([]))->seek(1);
    }

    public function testProxiesToFunction(): void
    {
        $s = new FnStream([
            'read' => function ($len) {
                $this->assertSame(3, $len);

                return 'foo';
            },
        ]);

        self::assertSame('foo', $s->read(3));
    }

    public function testReadRejectsNegativeLengthWithoutCallingCallback(): void
    {
        $called = false;
        $s = new FnStream([
            'read' => function () use (&$called): string {
                $called = true;

                return 'foo';
            },
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Length parameter cannot be negative');

        try {
            $s->read(-1);
        } finally {
            self::assertFalse($called);
        }
    }

    public function testProxiesToNonClosureCallable(): void
    {
        $s = new FnStream([
            'write' => 'strlen',
        ]);

        self::assertSame(3, $s->write('foo'));
    }

    public function testDecoratesWithNonClosureCallable(): void
    {
        $source = new class {
            public function read(int $length): string
            {
                return str_repeat('x', $length);
            }
        };

        $s = FnStream::decorate(Psr7\Utils::streamFor('foo'), [
            'read' => [$source, 'read'],
        ]);

        self::assertSame('xxx', $s->read(3));
    }

    public function testCanCloseOnDestruct(): void
    {
        $called = 0;
        $s = new FnStream([
            'close' => function () use (&$called): void {
                ++$called;
            },
        ]);
        unset($s);
        self::assertSame(1, $called);
    }

    public function testCanCloseUsingCallable(): void
    {
        $called = 0;
        $s = new FnStream([
            'close' => function () use (&$called): void {
                ++$called;
            },
        ]);

        $s->close();

        self::assertSame(1, $called);
    }

    public function testExplicitCloseAndDestructorOnlyCloseOnce(): void
    {
        $called = 0;
        $s = new FnStream([
            'close' => function () use (&$called): void {
                ++$called;
            },
        ]);

        $s->close();
        unset($s);

        self::assertSame(1, $called);
    }

    public function testCloseIsIdempotent(): void
    {
        $called = 0;
        $s = new FnStream([
            'close' => function () use (&$called): void {
                ++$called;
            },
        ]);

        $s->close();
        $s->close();
        unset($s);

        self::assertSame(1, $called);
    }

    public function testCloseFailureIsNotRetried(): void
    {
        $called = 0;
        $s = new FnStream([
            'close' => function () use (&$called): void {
                ++$called;

                throw new \RuntimeException('close failed');
            },
        ]);

        try {
            $s->close();
            self::fail('Expected close to fail');
        } catch (\RuntimeException $e) {
            self::assertSame('close failed', $e->getMessage());
        }

        $s->close();
        unset($s);

        self::assertSame(1, $called);
    }

    public function testCloseStillThrowsWhenNotImplemented(): void
    {
        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage('close() is not implemented in the FnStream');

        (new FnStream([]))->close();
    }

    public function testCanDetachUsingCallable(): void
    {
        $called = false;
        $resource = fopen('php://temp', 'r+');
        $s = new FnStream([
            'detach' => function () use (&$called, $resource) {
                $called = true;

                return $resource;
            },
        ]);

        self::assertSame($resource, $s->detach());
        self::assertTrue($called);

        fclose($resource);
    }

    public function testDoesNotRequireClose(): void
    {
        $s = new FnStream([]);
        unset($s);
        self::assertTrue(true); // strict mode requires an assertion
    }

    public function testDecoratesStream(): void
    {
        $a = Psr7\Utils::streamFor('foo');
        $b = FnStream::decorate($a, []);
        self::assertSame(3, $b->getSize());
        self::assertSame($b->isWritable(), true);
        self::assertSame($b->isReadable(), true);
        self::assertSame($b->isSeekable(), true);
        self::assertSame($b->read(3), 'foo');
        self::assertSame($b->tell(), 3);
        self::assertSame($a->tell(), 3);
        self::assertSame('', $a->read(1));
        self::assertSame($b->eof(), true);
        self::assertSame($a->eof(), true);
        $b->seek(0);
        self::assertSame('foo', (string) $b);
        $b->seek(0);
        self::assertSame('foo', $b->getContents());
        self::assertSame($a->getMetadata(), $b->getMetadata());
        $b->seek(0, SEEK_END);
        $b->write('bar');
        self::assertSame('foobar', (string) $b);
        self::assertIsResource($b->detach());
        $b->close();
    }

    public function testDecoratesWithCustomizations(): void
    {
        $called = false;
        $a = Psr7\Utils::streamFor('foo');
        $b = FnStream::decorate($a, [
            'read' => function ($len) use (&$called, $a) {
                $called = true;

                return $a->read($len);
            },
        ]);
        self::assertSame('foo', $b->read(3));
        self::assertTrue($called);
    }

    public function testDoNotAllowUnserialization(): void
    {
        $a = new FnStream([]);
        $b = serialize($a);
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('FnStream should never be unserialized');
        unserialize($b);
    }

    public function testThatConvertingStreamToStringWillThrowException(): void
    {
        $a = new FnStream([
            '__toString' => function (): void {
                throw new \Exception('foo');
            },
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('foo');

        (string) $a;
    }
}
