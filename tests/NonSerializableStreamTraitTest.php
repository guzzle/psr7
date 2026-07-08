<?php

declare(strict_types=1);

namespace GuzzleHttp\Tests\Psr7;

use GuzzleHttp\Psr7;
use PHPUnit\Framework\TestCase;

class NonSerializableStreamTraitTest extends TestCase
{
    /**
     * @dataProvider streamFactoryProvider
     */
    public function testDoNotAllowSerialization(string $class, callable $factory): void
    {
        if ($class === Psr7\InflateStream::class && !extension_loaded('zlib')) {
            self::markTestSkipped('zlib is required for InflateStream serialization coverage.');
        }

        $stream = $factory();

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage($class.' should never be serialized');

        serialize($stream);
    }

    /**
     * @dataProvider inheritedUnserializeClassProvider
     */
    public function testDoNotAllowUnserialization(string $class): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage($class.' should never be unserialized');

        unserialize(sprintf('O:%d:"%s":0:{}', strlen($class), $class));
    }

    public static function streamFactoryProvider(): array
    {
        return [
            Psr7\AppendStream::class => [Psr7\AppendStream::class, static fn () => new Psr7\AppendStream()],
            Psr7\BufferStream::class => [Psr7\BufferStream::class, static fn () => new Psr7\BufferStream()],
            Psr7\CachingStream::class => [Psr7\CachingStream::class, static fn () => new Psr7\CachingStream(Psr7\Utils::streamFor('body'))],
            Psr7\DroppingStream::class => [Psr7\DroppingStream::class, static fn () => new Psr7\DroppingStream(Psr7\Utils::streamFor(''), 1)],
            Psr7\FnStream::class => [Psr7\FnStream::class, static fn () => new Psr7\FnStream([])],
            Psr7\InflateStream::class => [Psr7\InflateStream::class, static fn () => new Psr7\InflateStream(Psr7\Utils::streamFor(gzencode('body')))],
            Psr7\LazyOpenStream::class => [Psr7\LazyOpenStream::class, static fn () => new Psr7\LazyOpenStream('/path/to/nonexistent', 'r')],
            Psr7\LimitStream::class => [Psr7\LimitStream::class, static fn () => new Psr7\LimitStream(Psr7\Utils::streamFor('body'), 1)],
            Psr7\MultipartStream::class => [Psr7\MultipartStream::class, static fn () => new Psr7\MultipartStream([], 'guard')],
            Psr7\NoSeekStream::class => [Psr7\NoSeekStream::class, static fn () => new Psr7\NoSeekStream(Psr7\Utils::streamFor('body'))],
            Psr7\PumpStream::class => [Psr7\PumpStream::class, static fn () => new Psr7\PumpStream(static fn (): bool => false)],
            Psr7\Stream::class => [Psr7\Stream::class, static fn () => new Psr7\Stream(Psr7\Utils::tryFopen('php://temp', 'r+'))],
        ];
    }

    public static function inheritedUnserializeClassProvider(): array
    {
        // FnStream, PumpStream, and LazyOpenStream override __unserialize()
        // and are covered by their own test files.
        return [
            Psr7\AppendStream::class => [Psr7\AppendStream::class],
            Psr7\BufferStream::class => [Psr7\BufferStream::class],
            Psr7\CachingStream::class => [Psr7\CachingStream::class],
            Psr7\DroppingStream::class => [Psr7\DroppingStream::class],
            Psr7\InflateStream::class => [Psr7\InflateStream::class],
            Psr7\LimitStream::class => [Psr7\LimitStream::class],
            Psr7\MultipartStream::class => [Psr7\MultipartStream::class],
            Psr7\NoSeekStream::class => [Psr7\NoSeekStream::class],
            Psr7\Stream::class => [Psr7\Stream::class],
        ];
    }
}
