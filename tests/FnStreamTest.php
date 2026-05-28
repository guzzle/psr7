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

    public function testSwallowsCloseExceptionOnDestruct(): void
    {
        $called = 0;
        $s = new FnStream([
            'close' => function () use (&$called): void {
                ++$called;

                throw new \RuntimeException('close failed');
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

    public function testCloseTerminatesStreamAndPreventsFurtherDelegation(): void
    {
        $called = [];
        $s = $this->createInstrumentedStream($called);

        $s->close();
        $s->close();

        self::assertSame(['close'], $called);

        $this->assertFnStreamIsDetached($s);

        self::assertSame(['close'], $called);
    }

    public function testDetachAfterCloseDoesNotRequireDetachCallback(): void
    {
        $s = new FnStream([
            'close' => function (): void {
            },
        ]);

        $s->close();

        self::assertNull($s->detach());
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

        $this->assertFnStreamIsDetached($s);

        self::assertSame(1, $called);

        unset($s);
    }

    public function testCloseStillThrowsWhenNotImplemented(): void
    {
        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage('close() is not implemented in the FnStream');

        (new FnStream([]))->close();
    }

    public function testDetachStillThrowsWhenNotImplemented(): void
    {
        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage('detach() is not implemented in the FnStream');

        (new FnStream([]))->detach();
    }

    public function testDetachTerminatesStreamAndPreventsFurtherDelegation(): void
    {
        $called = [];
        $resource = fopen('php://temp', 'r+');
        $s = $this->createInstrumentedStream($called, [
            'detach' => function () use (&$called, $resource) {
                $called[] = 'detach';

                return $resource;
            },
        ]);

        try {
            self::assertSame($resource, $s->detach());
            self::assertSame(['detach'], $called);

            $this->assertFnStreamIsDetached($s);

            self::assertSame(['detach'], $called);

            unset($s);

            self::assertSame(['detach'], $called);
        } finally {
            if (is_resource($resource)) {
                fclose($resource);
            }
        }
    }

    public function testCloseAfterDetachDoesNotRequireCloseCallback(): void
    {
        $resource = fopen('php://temp', 'r+');
        $s = new FnStream([
            'detach' => function () use ($resource) {
                return $resource;
            },
        ]);

        try {
            self::assertSame($resource, $s->detach());

            $s->close();
            unset($s);
        } finally {
            if (is_resource($resource)) {
                fclose($resource);
            }
        }
    }

    public function testDetachFailureDoesNotTerminateStream(): void
    {
        $called = 0;
        $s = new FnStream([
            'detach' => function () use (&$called): void {
                ++$called;

                throw new \RuntimeException('detach failed');
            },
            'read' => function (int $length): string {
                return str_repeat('x', $length);
            },
        ]);

        try {
            $s->detach();
            self::fail('Expected detach to fail');
        } catch (\RuntimeException $e) {
            self::assertSame('detach failed', $e->getMessage());
        }

        self::assertSame(1, $called);
        self::assertSame('xxx', $s->read(3));
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

        $resource = $b->detach();
        self::assertIsResource($resource);

        try {
            $b->close();
        } finally {
            fclose($resource);
        }
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

    private function assertFnStreamIsDetached(FnStream $stream): void
    {
        self::assertNull($stream->getSize());
        self::assertFalse($stream->isReadable());
        self::assertFalse($stream->isWritable());
        self::assertFalse($stream->isSeekable());
        self::assertSame([], $stream->getMetadata());
        self::assertNull($stream->getMetadata('foo'));

        $this->assertDetachedOperationThrows(static function () use ($stream): void {
            (string) $stream;
        });
        $this->assertDetachedOperationThrows(static function () use ($stream): void {
            $stream->getContents();
        });
        $this->assertDetachedOperationThrows(static function () use ($stream): void {
            $stream->read(1);
        });
        $this->assertDetachedOperationThrows(static function () use ($stream): void {
            $stream->read(-1);
        });
        $this->assertDetachedOperationThrows(static function () use ($stream): void {
            $stream->write('foo');
        });
        $this->assertDetachedOperationThrows(static function () use ($stream): void {
            $stream->seek(0);
        });
        $this->assertDetachedOperationThrows(static function () use ($stream): void {
            $stream->rewind();
        });
        $this->assertDetachedOperationThrows(static function () use ($stream): void {
            $stream->tell();
        });
        $this->assertDetachedOperationThrows(static function () use ($stream): void {
            $stream->eof();
        });

        self::assertNull($stream->detach());
        $stream->close();
    }

    private function assertDetachedOperationThrows(callable $operation): void
    {
        try {
            $operation();
        } catch (\RuntimeException $e) {
            self::assertSame('Stream is detached', $e->getMessage());

            return;
        }

        self::fail('Expected stream to be detached');
    }

    /**
     * @param list<string>            $called
     * @param array<string, callable> $overrides
     */
    private function createInstrumentedStream(array &$called, array $overrides = []): FnStream
    {
        return new FnStream($overrides + [
            '__toString' => static function () use (&$called): string {
                $called[] = '__toString';

                return 'stream';
            },
            'close' => static function () use (&$called): void {
                $called[] = 'close';
            },
            'detach' => static function () use (&$called) {
                $called[] = 'detach';

                return null;
            },
            'getSize' => static function () use (&$called): ?int {
                $called[] = 'getSize';

                return 3;
            },
            'tell' => static function () use (&$called): int {
                $called[] = 'tell';

                return 0;
            },
            'eof' => static function () use (&$called): bool {
                $called[] = 'eof';

                return false;
            },
            'isSeekable' => static function () use (&$called): bool {
                $called[] = 'isSeekable';

                return true;
            },
            'rewind' => static function () use (&$called): void {
                $called[] = 'rewind';
            },
            'seek' => static function (int $offset, int $whence = SEEK_SET) use (&$called): void {
                $called[] = 'seek';
            },
            'isWritable' => static function () use (&$called): bool {
                $called[] = 'isWritable';

                return true;
            },
            'write' => static function (string $string) use (&$called): int {
                $called[] = 'write';

                return strlen($string);
            },
            'isReadable' => static function () use (&$called): bool {
                $called[] = 'isReadable';

                return true;
            },
            'read' => static function (int $length) use (&$called): string {
                $called[] = 'read';

                return str_repeat('x', $length);
            },
            'getContents' => static function () use (&$called): string {
                $called[] = 'getContents';

                return 'contents';
            },
            'getMetadata' => static function (?string $key = null) use (&$called) {
                $called[] = 'getMetadata';

                return $key === null ? ['foo' => 'bar'] : 'bar';
            },
        ]);
    }
}
