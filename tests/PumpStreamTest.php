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

    /**
     * @dataProvider invalidSizes
     *
     * @param mixed $size
     */
    public function testRejectsInvalidSizeOption($size): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Stream size must be a non-negative integer or null');

        new PumpStream(static function (): void {
        }, ['size' => $size]);
    }

    public static function invalidSizes(): iterable
    {
        yield 'negative integer' => [-1];
        yield 'string integer' => ['100'];
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

    public function testReadRejectsNegativeLengthWithoutCallingSource(): void
    {
        $called = false;
        $p = new PumpStream(function () use (&$called): string {
            $called = true;

            return 'abc';
        });

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Length parameter cannot be negative');

        try {
            $p->read(-1);
        } finally {
            self::assertFalse($called);
        }
    }

    public function testReadThrowsWhenSourceReturnsEmptyString(): void
    {
        /** @var list<string|false> $chunks */
        $chunks = ['', false];

        $p = new PumpStream(static function (int $length) use (&$chunks) {
            return array_shift($chunks);
        });

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('PumpStream source returned an empty string');

        $p->read(1);
    }

    public function testFalseAndNullStillMeanEof(): void
    {
        self::assertSame('', (new PumpStream(static function () {
            return false;
        }))->read(1));

        self::assertSame('', (new PumpStream(static function (): void {
        }))->read(1));
    }

    public function testReadAcceptsStringZeroFromSource(): void
    {
        $p = new PumpStream(static function (): string {
            return '0';
        });

        self::assertSame('0', $p->read(1));
    }

    public function testReadFailureDoesNotDiscardBufferedBytes(): void
    {
        /** @var list<string|false> $chunks */
        $chunks = ['abc', '', false];

        $p = new PumpStream(static function (int $length) use (&$chunks) {
            return array_shift($chunks);
        });

        self::assertSame('ab', $p->read(2));
        self::assertSame(2, $p->tell());

        try {
            $p->read(2);
            self::fail('Expected empty source chunk to throw.');
        } catch (\RuntimeException $e) {
            self::assertSame('PumpStream source returned an empty string', $e->getMessage());
        }

        self::assertSame(2, $p->tell());
        self::assertSame('c', $p->read(2));
        self::assertSame(3, $p->tell());
        self::assertTrue($p->eof());
    }

    public function testReadFailureDoesNotDiscardBytesBufferedDuringSameRead(): void
    {
        /** @var list<string|false> $chunks */
        $chunks = ['a', '', false];

        $p = new PumpStream(static function (int $length) use (&$chunks) {
            return array_shift($chunks);
        });

        try {
            $p->read(2);
            self::fail('Expected empty source chunk to throw.');
        } catch (\RuntimeException $e) {
            self::assertSame('PumpStream source returned an empty string', $e->getMessage());
        }

        self::assertSame(0, $p->tell());
        self::assertSame('a', $p->read(1));
        self::assertSame(1, $p->tell());
        self::assertFalse($p->eof());
        self::assertSame('', $p->read(1));
        self::assertSame(1, $p->tell());
        self::assertTrue($p->eof());
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

    public function testCloseClearsBufferedBytesAndResetsPosition(): void
    {
        $p = new PumpStream(function (): string {
            return 'abc';
        });

        self::assertSame('a', $p->read(1));
        self::assertSame(1, $p->tell());

        $p->close();

        self::assertTrue($p->eof());
        self::assertSame(0, $p->tell());
        self::assertSame('', $p->read(10));
        self::assertSame(0, $p->tell());
        self::assertSame('', $p->getContents());
    }

    public function testDetachClearsBufferedBytesAndResetsPosition(): void
    {
        $p = new PumpStream(function (): string {
            return 'abc';
        });

        self::assertSame('a', $p->read(1));
        self::assertSame(1, $p->tell());

        self::assertNull($p->detach());

        self::assertTrue($p->eof());
        self::assertSame(0, $p->tell());
        self::assertSame('', $p->getContents());
        self::assertSame('', $p->read(10));
        self::assertSame(0, $p->tell());
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

    public function testUnserializationCannotInvokeSourceFromDestructor(): void
    {
        PumpStreamUnserializeMarker::$calls = 0;
        $payload = self::serializedObjectWithProperties(PumpStreamUnserializeStringCastOnDestruct::class, [
            'stream' => self::serializedObjectWithProperties(PumpStream::class, [
                self::privateProperty(PumpStream::class, 'source') => serialize([PumpStreamUnserializeMarker::class, 'mark']),
            ]),
        ]);

        try {
            unserialize($payload);
            self::fail('Expected unserialization to fail.');
        } catch (\LogicException $e) {
            self::assertSame('PumpStream should never be unserialized', $e->getMessage());
        }

        self::assertSame(0, PumpStreamUnserializeMarker::$calls);
    }

    private static function privateProperty(string $class, string $property): string
    {
        return "\0".$class."\0".$property;
    }

    /**
     * @param array<string, string> $properties Serialized property values indexed by property name.
     */
    private static function serializedObjectWithProperties(string $class, array $properties): string
    {
        $body = '';
        foreach ($properties as $name => $serializedValue) {
            $body .= serialize($name).$serializedValue;
        }

        return sprintf('O:%d:"%s":%d:{%s}', strlen($class), $class, count($properties), $body);
    }
}

final class PumpStreamUnserializeStringCastOnDestruct
{
    /** @var mixed */
    public $stream;

    public function __destruct()
    {
        try {
            (string) $this->stream;
        } catch (\Throwable $e) {
        }
    }
}

final class PumpStreamUnserializeMarker
{
    public static int $calls = 0;

    public static function mark(): string
    {
        ++self::$calls;

        return 'marked';
    }
}
