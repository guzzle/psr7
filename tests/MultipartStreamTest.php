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

    public function testThrowsWhenNameIsNotStringOrInt(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("The 'name' key must be a string or integer");

        new MultipartStream([
            [
                'name' => ['invalid'],
                'contents' => 'value',
            ],
        ]);
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
            "Content-Length: 3\r\n",
            "\r\n",
            "bar\r\n",
            "--boundary\r\n",
            "Content-Disposition: form-data; name=\"baz\"\r\n",
            "Content-Length: 3\r\n",
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
            "Content-Length: 1\r\n",
            "\r\n",
            "1\r\n",
            "--boundary\r\n",
            "Content-Disposition: form-data; name=\"bool\"\r\n",
            "\r\n",
            "\r\n",
            '--boundary',
            "\r\n",
            "Content-Disposition: form-data; name=\"bool2\"\r\n",
            "Content-Length: 1\r\n",
            "\r\n",
            "1\r\n",
            "--boundary\r\n",
            "Content-Disposition: form-data; name=\"float\"\r\n",
            "Content-Length: 3\r\n",
            "\r\n",
            "1.1\r\n",
            "--boundary--\r\n",
            '',
        ]);

        self::assertSame($expected, (string) $b);
    }

    public function testExpandsNestedArrayContents(): void
    {
        $b = new MultipartStream([
            [
                'name' => 'foo',
                'contents' => [
                    ['key' => 'bar'],
                    ['key' => 'baz'],
                ],
            ],
        ], 'boundary');

        $expected = \implode('', [
            "--boundary\r\n",
            "Content-Disposition: form-data; name=\"foo[0][key]\"\r\n",
            "Content-Length: 3\r\n",
            "\r\n",
            "bar\r\n",
            "--boundary\r\n",
            "Content-Disposition: form-data; name=\"foo[1][key]\"\r\n",
            "Content-Length: 3\r\n",
            "\r\n",
            "baz\r\n",
            "--boundary--\r\n",
        ]);

        self::assertSame($expected, (string) $b);
    }

    public function testExpandsFlatArrayContents(): void
    {
        $b = new MultipartStream([
            [
                'name' => 'tags',
                'contents' => ['php', 'guzzle', 'psr7'],
            ],
        ], 'boundary');

        $expected = \implode('', [
            "--boundary\r\n",
            "Content-Disposition: form-data; name=\"tags[0]\"\r\n",
            "Content-Length: 3\r\n",
            "\r\n",
            "php\r\n",
            "--boundary\r\n",
            "Content-Disposition: form-data; name=\"tags[1]\"\r\n",
            "Content-Length: 6\r\n",
            "\r\n",
            "guzzle\r\n",
            "--boundary\r\n",
            "Content-Disposition: form-data; name=\"tags[2]\"\r\n",
            "Content-Length: 4\r\n",
            "\r\n",
            "psr7\r\n",
            "--boundary--\r\n",
        ]);

        self::assertSame($expected, (string) $b);
    }

    public function testExpandsAssociativeArrayContents(): void
    {
        $b = new MultipartStream([
            [
                'name' => 'user',
                'contents' => ['name' => 'John', 'email' => 'john@example.com'],
            ],
        ], 'boundary');

        $expected = \implode('', [
            "--boundary\r\n",
            "Content-Disposition: form-data; name=\"user[name]\"\r\n",
            "Content-Length: 4\r\n",
            "\r\n",
            "John\r\n",
            "--boundary\r\n",
            "Content-Disposition: form-data; name=\"user[email]\"\r\n",
            "Content-Length: 16\r\n",
            "\r\n",
            "john@example.com\r\n",
            "--boundary--\r\n",
        ]);

        self::assertSame($expected, (string) $b);
    }

    public function testExpandsDeeplyNestedArrayContents(): void
    {
        $b = new MultipartStream([
            [
                'name' => 'data',
                'contents' => [
                    'level1' => [
                        'level2' => [
                            'level3' => 'deep',
                        ],
                    ],
                ],
            ],
        ], 'boundary');

        $expected = \implode('', [
            "--boundary\r\n",
            "Content-Disposition: form-data; name=\"data[level1][level2][level3]\"\r\n",
            "Content-Length: 4\r\n",
            "\r\n",
            "deep\r\n",
            "--boundary--\r\n",
        ]);

        self::assertSame($expected, (string) $b);
    }

    public function testExpandsEmptyArrayContents(): void
    {
        $b = new MultipartStream([
            [
                'name' => 'empty',
                'contents' => [],
            ],
        ], 'boundary');

        $expected = "--boundary--\r\n";

        self::assertSame($expected, (string) $b);
    }

    public function testExpandsArrayContentsWithMixedScalarTypes(): void
    {
        $b = new MultipartStream([
            [
                'name' => 'mixed',
                'contents' => [
                    'int' => 42,
                    'float' => 3.14,
                    'bool_true' => true,
                    'bool_false' => false,
                    'string' => 'hello',
                ],
            ],
        ], 'boundary');

        $expected = \implode('', [
            "--boundary\r\n",
            "Content-Disposition: form-data; name=\"mixed[int]\"\r\n",
            "Content-Length: 2\r\n",
            "\r\n",
            "42\r\n",
            "--boundary\r\n",
            "Content-Disposition: form-data; name=\"mixed[float]\"\r\n",
            "Content-Length: 4\r\n",
            "\r\n",
            "3.14\r\n",
            "--boundary\r\n",
            "Content-Disposition: form-data; name=\"mixed[bool_true]\"\r\n",
            "Content-Length: 1\r\n",
            "\r\n",
            "1\r\n",
            "--boundary\r\n",
            "Content-Disposition: form-data; name=\"mixed[bool_false]\"\r\n",
            "\r\n",
            "\r\n",
            "--boundary\r\n",
            "Content-Disposition: form-data; name=\"mixed[string]\"\r\n",
            "Content-Length: 5\r\n",
            "\r\n",
            "hello\r\n",
            "--boundary--\r\n",
        ]);

        self::assertSame($expected, (string) $b);
    }

    public function testExpandsArrayContentsWithNumericStringKeys(): void
    {
        $b = new MultipartStream([
            [
                'name' => 'items',
                'contents' => ['10' => 'ten', '20' => 'twenty'],
            ],
        ], 'boundary');

        $expected = \implode('', [
            "--boundary\r\n",
            "Content-Disposition: form-data; name=\"items[10]\"\r\n",
            "Content-Length: 3\r\n",
            "\r\n",
            "ten\r\n",
            "--boundary\r\n",
            "Content-Disposition: form-data; name=\"items[20]\"\r\n",
            "Content-Length: 6\r\n",
            "\r\n",
            "twenty\r\n",
            "--boundary--\r\n",
        ]);

        self::assertSame($expected, (string) $b);
    }

    public function testThrowsWhenFilenameUsedWithArrayContents(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("The 'filename' and 'headers' options cannot be used when 'contents' is an array");

        new MultipartStream([
            [
                'name' => 'foo',
                'contents' => ['bar' => 'baz'],
                'filename' => 'test.txt',
            ],
        ]);
    }

    public function testThrowsWhenHeadersUsedWithArrayContents(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("The 'filename' and 'headers' options cannot be used when 'contents' is an array");

        new MultipartStream([
            [
                'name' => 'foo',
                'contents' => ['bar' => 'baz'],
                'headers' => ['X-Custom' => 'value'],
            ],
        ]);
    }

    public function testExpandsArrayContentsWithZeroName(): void
    {
        $b = new MultipartStream([
            [
                'name' => '0',
                'contents' => ['a' => 'value'],
            ],
        ], 'boundary');

        $expected = \implode('', [
            "--boundary\r\n",
            "Content-Disposition: form-data; name=\"0[a]\"\r\n",
            "Content-Length: 5\r\n",
            "\r\n",
            "value\r\n",
            "--boundary--\r\n",
        ]);

        self::assertSame($expected, (string) $b);
    }

    public function testExpandsArrayContentsWithIntegerName(): void
    {
        $b = new MultipartStream([
            [
                'name' => 0,
                'contents' => ['a' => 'value'],
            ],
        ], 'boundary');

        $expected = \implode('', [
            "--boundary\r\n",
            "Content-Disposition: form-data; name=\"0[a]\"\r\n",
            "Content-Length: 5\r\n",
            "\r\n",
            "value\r\n",
            "--boundary--\r\n",
        ]);

        self::assertSame($expected, (string) $b);
    }

    public function testThrowsWhenZeroFilenameUsedWithArrayContents(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("The 'filename' and 'headers' options cannot be used when 'contents' is an array");

        new MultipartStream([
            [
                'name' => 'foo',
                'contents' => ['bar' => 'baz'],
                'filename' => '0',
            ],
        ]);
    }

    public function testExpandsArrayContentsWithNestedEmptyBranches(): void
    {
        $b = new MultipartStream([
            [
                'name' => 'data',
                'contents' => ['a' => [], 'b' => 'value'],
            ],
        ], 'boundary');

        $expected = \implode('', [
            "--boundary\r\n",
            "Content-Disposition: form-data; name=\"data[b]\"\r\n",
            "Content-Length: 5\r\n",
            "\r\n",
            "value\r\n",
            "--boundary--\r\n",
        ]);

        self::assertSame($expected, (string) $b);
    }

    public function testExpandsArrayContentsWithEmptyStringName(): void
    {
        $b = new MultipartStream([
            [
                'name' => '',
                'contents' => ['a' => 'value'],
            ],
        ], 'boundary');

        $expected = \implode('', [
            "--boundary\r\n",
            "Content-Disposition: form-data; name=\"[a]\"\r\n",
            "Content-Length: 5\r\n",
            "\r\n",
            "value\r\n",
            "--boundary--\r\n",
        ]);

        self::assertSame($expected, (string) $b);
    }

    public function testThrowsWhenNullFilenameKeyExistsWithArrayContents(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("The 'filename' and 'headers' options cannot be used when 'contents' is an array");

        new MultipartStream([
            [
                'name' => 'foo',
                'contents' => ['bar' => 'baz'],
                'filename' => null,
            ],
        ]);
    }

    public function testThrowsWhenEmptyHeadersKeyExistsWithArrayContents(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("The 'filename' and 'headers' options cannot be used when 'contents' is an array");

        new MultipartStream([
            [
                'name' => 'foo',
                'contents' => ['bar' => 'baz'],
                'headers' => [],
            ],
        ]);
    }

    public function testExpandsArrayContentsWithStreamLeaves(): void
    {
        $fileStream = Psr7\FnStream::decorate(Psr7\Utils::streamFor('file contents'), [
            'getMetadata' => static function (): string {
                return '/path/to/document.pdf';
            },
        ]);

        $b = new MultipartStream([
            [
                'name' => 'files',
                'contents' => [
                    'doc' => $fileStream,
                    'note' => 'plain text',
                ],
            ],
        ], 'boundary');

        $expected = \implode('', [
            "--boundary\r\n",
            "Content-Disposition: form-data; name=\"files[doc]\"; filename=\"document.pdf\"\r\n",
            "Content-Length: 13\r\n",
            "Content-Type: application/pdf\r\n",
            "\r\n",
            "file contents\r\n",
            "--boundary\r\n",
            "Content-Disposition: form-data; name=\"files[note]\"\r\n",
            "Content-Length: 10\r\n",
            "\r\n",
            "plain text\r\n",
            "--boundary--\r\n",
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
            "Content-Length: 3\r\n",
            "Content-Type: text/plain\r\n",
            "\r\n",
            "foo\r\n",
            "--boundary\r\n",
            "Content-Disposition: form-data; name=\"qux\"; filename=\"baz.jpg\"\r\n",
            "Content-Length: 3\r\n",
            "Content-Type: image/jpeg\r\n",
            "\r\n",
            "baz\r\n",
            "--boundary\r\n",
            "Content-Disposition: form-data; name=\"qux\"; filename=\"bar.unknown\"\r\n",
            "Content-Length: 3\r\n",
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
            "Content-Length: {$contentLength}\r\n",
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
            "Content-Length: 3\r\n",
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
            "Content-Length: 3\r\n",
            "Content-Type: text/plain\r\n",
            "\r\n",
            "foo\r\n",
            "--boundary\r\n",
            "cOntenT-Type: custom\r\n",
            "Content-Disposition: form-data; name=\"foo\"; filename=\"baz.jpg\"\r\n",
            "Content-Length: 3\r\n",
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
