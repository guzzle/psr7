<?php

declare(strict_types=1);

namespace GuzzleHttp\Tests\Psr7;

use GuzzleHttp\Psr7;
use GuzzleHttp\Psr7\MultipartStream;
use PHPUnit\Framework\TestCase;

class MultipartStreamTest extends TestCase
{
    public function testCreatesDefaultBoundary(): void
    {
        $b = new MultipartStream();
        self::assertNotEmpty($b->getBoundary());
    }

    public function testCanProvideBoundary(): void
    {
        $b = new MultipartStream([], 'foo');
        self::assertSame('foo', $b->getBoundary());
    }

    public function testIsNotWritable(): void
    {
        $b = new MultipartStream();
        self::assertFalse($b->isWritable());
    }

    public function testCanCreateEmptyStream(): void
    {
        $b = new MultipartStream();
        $boundary = $b->getBoundary();
        self::assertSame("--{$boundary}--\r\n", $b->getContents());
        self::assertSame(strlen($boundary) + 6, $b->getSize());
    }

    public function testValidatesFilesArrayElement(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new MultipartStream([['foo' => 'bar']]);
    }

    public function testEnsuresFileHasName(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new MultipartStream([['contents' => 'bar']]);
    }

    public function testSerializesFields(): void
    {
        $b = new MultipartStream([
            [
                'name' => 'foo',
                'contents' => 'bar',
            ],
            [
                'name' => 'baz',
                'contents' => 'bam',
            ],
        ], 'boundary');

        $expected = \implode('', [
            "--boundary\r\n",
            "Content-Disposition: form-data; name=\"foo\"\r\n",
            "\r\n",
            "bar\r\n",
            "--boundary\r\n",
            "Content-Disposition: form-data; name=\"baz\"\r\n",
            "\r\n",
            "bam\r\n",
            "--boundary--\r\n",
        ]);

        self::assertSame($expected, (string) $b);
    }

    public function testSerializesNonStringFields(): void
    {
        $b = new MultipartStream([
            [
                'name' => 'int',
                'contents' => (int) 1,
            ],
            [
                'name' => 'bool',
                'contents' => (bool) false,
            ],
            [
                'name' => 'bool2',
                'contents' => (bool) true,
            ],
            [
                'name' => 'float',
                'contents' => (float) 1.1,
            ],
        ], 'boundary');

        $expected = \implode('', [
            "--boundary\r\n",
            "Content-Disposition: form-data; name=\"int\"\r\n",
            "\r\n",
            "1\r\n",
            "--boundary\r\n",
            "Content-Disposition: form-data; name=\"bool\"\r\n",
            "\r\n",
            "\r\n",
            '--boundary',
            "\r\n",
            "Content-Disposition: form-data; name=\"bool2\"\r\n",
            "\r\n",
            "1\r\n",
            "--boundary\r\n",
            "Content-Disposition: form-data; name=\"float\"\r\n",
            "\r\n",
            "1.1\r\n",
            "--boundary--\r\n",
            '',
        ]);

        self::assertSame($expected, (string) $b);
    }

    public function testSerializesFiles(): void
    {
        $f1 = Psr7\FnStream::decorate(Psr7\Utils::streamFor('foo'), [
            'getMetadata' => static function (): string {
                return '/foo/bar.txt';
            },
        ]);

        $f2 = Psr7\FnStream::decorate(Psr7\Utils::streamFor('baz'), [
            'getMetadata' => static function (): string {
                return '/foo/baz.jpg';
            },
        ]);

        $f3 = Psr7\FnStream::decorate(Psr7\Utils::streamFor('bar'), [
            'getMetadata' => static function (): string {
                return '/foo/bar.unknown';
            },
        ]);

        $b = new MultipartStream([
            [
                'name' => 'foo',
                'contents' => $f1,
            ],
            [
                'name' => 'qux',
                'contents' => $f2,
            ],
            [
                'name' => 'qux',
                'contents' => $f3,
            ],
        ], 'boundary');

        $expected = \implode('', [
            "--boundary\r\n",
            "Content-Disposition: form-data; name=\"foo\"; filename=\"bar.txt\"\r\n",
            "Content-Type: text/plain\r\n",
            "\r\n",
            "foo\r\n",
            "--boundary\r\n",
            "Content-Disposition: form-data; name=\"qux\"; filename=\"baz.jpg\"\r\n",
            "Content-Type: image/jpeg\r\n",
            "\r\n",
            "baz\r\n",
            "--boundary\r\n",
            "Content-Disposition: form-data; name=\"qux\"; filename=\"bar.unknown\"\r\n",
            "Content-Type: application/octet-stream\r\n",
            "\r\n",
            "bar\r\n",
            "--boundary--\r\n",
        ]);

        self::assertSame($expected, (string) $b);
    }

    public function testSerializesFilesWithMixedNewlines(): void
    {
        $content = "LF\nCRLF\r\nCR\r";
        $contentLength = \strlen($content);

        $f1 = Psr7\FnStream::decorate(Psr7\Utils::streamFor($content), [
            'getMetadata' => static function (): string {
                return '/foo/newlines.txt';
            },
        ]);

        $b = new MultipartStream([
            [
                'name' => 'newlines',
                'contents' => $f1,
            ],
        ], 'boundary');

        $expected = \implode('', [
            "--boundary\r\n",
            "Content-Disposition: form-data; name=\"newlines\"; filename=\"newlines.txt\"\r\n",
            "Content-Type: text/plain\r\n",
            "\r\n",
            "{$content}\r\n",
            "--boundary--\r\n",
        ]);

        // Do not perform newline normalization in the assertion! The `$content` must
        // be embedded as-is in the payload.
        self::assertSame($expected, (string) $b);
    }

    public function testSerializesFilesWithCustomHeaders(): void
    {
        $f1 = Psr7\FnStream::decorate(Psr7\Utils::streamFor('foo'), [
            'getMetadata' => static function (): string {
                return '/foo/bar.txt';
            },
        ]);

        $b = new MultipartStream([
            [
                'name' => 'foo',
                'contents' => $f1,
                'headers' => [
                    'x-foo' => 'bar',
                    'content-disposition' => 'custom',
                ],
            ],
        ], 'boundary');

        $expected = \implode('', [
            "--boundary\r\n",
            "x-foo: bar\r\n",
            "content-disposition: custom\r\n",
            "Content-Type: text/plain\r\n",
            "\r\n",
            "foo\r\n",
            "--boundary--\r\n",
        ]);

        self::assertSame($expected, (string) $b);
    }

    public function testSerializesFilesWithCustomHeadersAndMultipleValues(): void
    {
        $f1 = Psr7\FnStream::decorate(Psr7\Utils::streamFor('foo'), [
            'getMetadata' => static function (): string {
                return '/foo/bar.txt';
            },
        ]);

        $f2 = Psr7\FnStream::decorate(Psr7\Utils::streamFor('baz'), [
            'getMetadata' => static function (): string {
                return '/foo/baz.jpg';
            },
        ]);

        $b = new MultipartStream([
            [
                'name' => 'foo',
                'contents' => $f1,
                'headers' => [
                    'x-foo' => 'bar',
                    'content-disposition' => 'custom',
                ],
            ],
            [
                'name' => 'foo',
                'contents' => $f2,
                'headers' => ['cOntenT-Type' => 'custom'],
            ],
        ], 'boundary');

        $expected = \implode('', [
            "--boundary\r\n",
            "x-foo: bar\r\n",
            "content-disposition: custom\r\n",
            "Content-Type: text/plain\r\n",
            "\r\n",
            "foo\r\n",
            "--boundary\r\n",
            "cOntenT-Type: custom\r\n",
            "Content-Disposition: form-data; name=\"foo\"; filename=\"baz.jpg\"\r\n",
            "\r\n",
            "baz\r\n",
            "--boundary--\r\n",
        ]);

        self::assertSame($expected, (string) $b);
    }

    public function testCanCreateWithNoneMetadataStreamField(): void
    {
        $str = 'dummy text';
        $a = Psr7\Utils::streamFor(static function () use ($str): string {
            return $str;
        });
        $b = new Psr7\LimitStream($a, \strlen($str));
        $c = new MultipartStream([
            [
                'name' => 'foo',
                'contents' => $b,
            ],
        ], 'boundary');

        $expected = \implode('', [
            "--boundary\r\n",
            "Content-Disposition: form-data; name=\"foo\"\r\n",
            "\r\n",
            $str."\r\n",
            "--boundary--\r\n",
        ]);

        self::assertSame($expected, (string) $c);
    }
}
