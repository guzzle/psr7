<?php

declare(strict_types=1);

namespace GuzzleHttp\Tests\Psr7;

use GuzzleHttp\Psr7\LazyOpenStream;
use PHPUnit\Framework\TestCase;

class LazyOpenStreamTest extends TestCase
{
    /** @var string|false */
    private $fname;

    protected function setUp(): void
    {
        $this->fname = tempnam(sys_get_temp_dir(), 'tfile');

        if (file_exists($this->fname)) {
            unlink($this->fname);
        }
    }

    protected function tearDown(): void
    {
        if (file_exists($this->fname)) {
            unlink($this->fname);
        }
    }

    public function testOpensLazily(): void
    {
        $l = new LazyOpenStream($this->fname, 'w+');
        $l->write('foo');
        self::assertIsArray($l->getMetadata());
        self::assertFileExists($this->fname);
        self::assertSame('foo', file_get_contents($this->fname));
        self::assertSame('foo', (string) $l);
    }

    public function testProxiesToFile(): void
    {
        file_put_contents($this->fname, 'foo');
        $l = new LazyOpenStream($this->fname, 'r');
        self::assertSame('foo', $l->read(4));
        self::assertTrue($l->eof());
        self::assertSame(3, $l->tell());
        self::assertTrue($l->isReadable());
        self::assertTrue($l->isSeekable());
        self::assertFalse($l->isWritable());
        $l->seek(1);
        self::assertSame('oo', $l->getContents());
        self::assertSame('foo', (string) $l);
        self::assertSame(3, $l->getSize());
        self::assertIsArray($l->getMetadata());
        $l->close();
    }

    public function testReadRejectsNegativeLengthWithoutOpeningFile(): void
    {
        $l = new LazyOpenStream($this->fname, 'r');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Length parameter cannot be negative');

        try {
            $l->read(-1);
        } finally {
            self::assertFileDoesNotExist($this->fname);
        }
    }

    public function testDetachesUnderlyingStream(): void
    {
        file_put_contents($this->fname, 'foo');
        $l = new LazyOpenStream($this->fname, 'r');
        $r = $l->detach();
        self::assertIsResource($r);
        fseek($r, 0);
        self::assertSame('foo', stream_get_contents($r));
        fclose($r);
    }

    public function testDoNotAllowUnserialization(): void
    {
        $class = LazyOpenStream::class;

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage($class.' should never be unserialized');
        unserialize(sprintf('O:%d:"%s":0:{}', strlen($class), $class));
    }

    public function testUnserializationCannotOpenFileFromDestructor(): void
    {
        LazyOpenStreamUnserializeStringCastOnDestruct::$casts = [];
        $payload = self::serializedObjectWithProperties(LazyOpenStreamUnserializeStringCastOnDestruct::class, [
            'stream' => self::serializedObjectWithProperties(LazyOpenStream::class, [
                self::privateProperty(LazyOpenStream::class, 'filename') => serialize($this->fname),
                self::privateProperty(LazyOpenStream::class, 'mode') => serialize('w+'),
            ]),
        ]);

        try {
            unserialize($payload);
            self::fail('Expected unserialization to fail.');
        } catch (\LogicException $e) {
            self::assertSame(LazyOpenStream::class.' should never be unserialized', $e->getMessage());
        }

        self::assertSame([''], LazyOpenStreamUnserializeStringCastOnDestruct::$casts);
        self::assertFileDoesNotExist($this->fname);
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

final class LazyOpenStreamUnserializeStringCastOnDestruct
{
    /** @var mixed */
    public $stream;

    /** @var list<string> */
    public static array $casts = [];

    public function __destruct()
    {
        try {
            if ($this->stream instanceof LazyOpenStream) {
                self::$casts[] = (string) $this->stream;
            }
        } catch (\Throwable $e) {
        }
    }
}
