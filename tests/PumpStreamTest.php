<?php

declare(strict_types=1);

namespace GuzzleHttp\Tests\Psr7;

use GuzzleHttp\Psr7;
use GuzzleHttp\Psr7\LimitStream;
use GuzzleHttp\Psr7\PumpStream;
use PHPUnit\Framework\TestCase;

class PumpStreamTest extends TestCase
{
    /** @var list<string|false|null> */
    private static array $chunks = [];

    /**
     * @return string|false|null
     */
    public static function dequeueChunk(int $length)
    {
        if (self::$chunks === []) {
            return false;
        }

        return array_shift(self::$chunks);
    }

    public function testHasMetadataAndSize(): void
    {
        $p = new PumpStream(function (): void {
        }, [
            'metadata' => ['foo' => 'bar'],
            'size' => 100,
        ]);

        self::assertSame('bar', $p->getMetadata('foo'));
        self::assertSame(['foo' => 'bar'], $p->getMetadata());
        self::assertSame(100, $p->getSize());
    }

    public function testCanReadFromCallable(): void
    {
        $p = Psr7\Utils::streamFor(function ($size) {
            return 'a';
        });
        self::assertSame('a', $p->read(1));
        self::assertSame(1, $p->tell());
        self::assertSame('aaaaa', $p->read(5));
        self::assertSame(6, $p->tell());
    }

    public function testCanReadFromCallableString(): void
    {
        self::$chunks = ['foo', false];

        $p = new PumpStream(self::class.'::dequeueChunk');

        self::assertSame('foo', $p->getContents());
    }

    public function testCanReadFromCallableArray(): void
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

        $p = new PumpStream([$source, 'read']);

        self::assertSame('foo', $p->getContents());
    }

    public function testCanReadFromInvokableObject(): void
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

        $p = new PumpStream($source);

        self::assertSame('foo', $p->getContents());
    }

    public function testStoresExcessDataInBuffer(): void
    {
        $called = [];
        $p = Psr7\Utils::streamFor(function ($size) use (&$called) {
            $called[] = $size;

            return 'abcdef';
        });
        self::assertSame('a', $p->read(1));
        self::assertSame('b', $p->read(1));
        self::assertSame('cdef', $p->read(4));
        self::assertSame('abcdefabc', $p->read(9));
        self::assertSame([1, 9, 3], $called);
    }

    public function testInifiniteStreamWrappedInLimitStream(): void
    {
        $p = Psr7\Utils::streamFor(function () {
            return 'a';
        });
        $s = new LimitStream($p, 5);
        self::assertSame('aaaaa', (string) $s);
    }

    public function testDescribesCapabilities(): void
    {
        $p = Psr7\Utils::streamFor(function (): void {
        });
        self::assertTrue($p->isReadable());
        self::assertFalse($p->isSeekable());
        self::assertFalse($p->isWritable());
        self::assertNull($p->getSize());
        self::assertSame('', $p->getContents());
        self::assertSame('', (string) $p);
        $p->close();
        self::assertSame('', $p->read(10));
        self::assertTrue($p->eof());

        try {
            self::assertFalse($p->write('aa'));
            self::fail();
        } catch (\RuntimeException $e) {
        }
    }

    public function testThatConvertingStreamToStringWillThrowException(): void
    {
        $p = Psr7\Utils::streamFor(function ($size): void {
            throw new \Exception('foo');
        });
        self::assertInstanceOf(PumpStream::class, $p);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('foo');

        (string) $p;
    }
}
