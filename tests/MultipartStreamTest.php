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

    /**
     * @dataProvider validCustomBoundaryProvider
     */
    public function testCanProvideRfc2046Boundary(string $boundary): void
    {
        $b = new MultipartStream([], $boundary);
        self::assertSame($boundary, $b->getBoundary());
    }

    public static function validCustomBoundaryProvider(): iterable
    {
        yield 'letters and digits' => ['abc123'];
        yield 'zero' => ['0'];
        yield 'apostrophe' => ["abc'def"];
        yield 'parentheses' => ['abc(def)'];
        yield 'plus' => ['abc+def'];
        yield 'underscore' => ['abc_def'];
        yield 'comma' => ['abc,def'];
        yield 'hyphen' => ['abc-def'];
        yield 'period' => ['abc.def'];
        yield 'slash' => ['abc/def'];
        yield 'colon' => ['abc:def'];
        yield 'equals' => ['abc=def'];
        yield 'question mark' => ['abc?def'];
        yield 'internal space' => ['abc def'];
        yield 'seventy bytes' => [str_repeat('a', 70)];
    }

    /**
     * @dataProvider invalidCustomBoundaryProvider
     */
    public function testRejectsInvalidCustomBoundary(string $boundary): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid multipart boundary.');

        new MultipartStream([], $boundary);
    }

    public static function invalidCustomBoundaryProvider(): iterable
    {
        yield 'empty' => [''];
        yield 'seventy one bytes' => [str_repeat('a', 71)];
        yield 'trailing space' => ['abc '];
        yield 'carriage return' => ["abc\rdef"];
        yield 'line feed' => ["abc\ndef"];
        yield 'nul' => ["abc\0def"];
        yield 'quote' => ['abc"def'];
        yield 'backslash' => ['abc\\def'];
        yield 'semicolon' => ['abc;def'];
        yield 'non ascii' => ["abc\xC3\xA9def"];
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

    public function testSerializesRawCallableContents(): void
    {
        $chunks = ['callable body', false];
        $b = new MultipartStream([
            [
                'name' => 'foo',
                'contents' => static function (int $length) use (&$chunks) {
                    if ($chunks === []) {
                        return false;
                    }

                    return array_shift($chunks);
                },
            ],
        ], 'boundary');

        $expected = \implode('', [
            "--boundary\r\n",
            "Content-Disposition: form-data; name=\"foo\"\r\n",
            "\r\n",
            "callable body\r\n",
            "--boundary--\r\n",
        ]);

        self::assertSame($expected, (string) $b);
    }

    public function testEscapesGeneratedContentDispositionName(): void
    {
        $b = new MultipartStream([
            [
                'name' => "field\"\r\nname",
                'contents' => 'value',
            ],
        ], 'boundary');

        $expected = \implode('', [
            "--boundary\r\n",
            "Content-Disposition: form-data; name=\"field%22%0D%0Aname\"\r\n",
            "\r\n",
            "value\r\n",
            "--boundary--\r\n",
        ]);

        self::assertSame($expected, (string) $b);
    }

    public function testGeneratedContentDispositionNameCannotSmuggleAdditionalParts(): void
    {
        $evilName = \implode('', [
            "x\"\r\n\r\n--BOUND\r\n",
            "Content-Disposition: form-data; name=\"role\"\r\n\r\n",
            "admin\r\n--BOUND\r\n",
            'Content-Disposition: form-data; name="_ignore',
        ]);

        $body = new MultipartStream([
            [
                'name' => $evilName,
                'contents' => '',
            ],
            [
                'name' => 'realfield',
                'contents' => 'real-value',
            ],
        ], 'BOUND');

        $serialized = (string) $body;
        $escapedName = \implode('', [
            'x%22%0D%0A%0D%0A--BOUND%0D%0A',
            'Content-Disposition: form-data; name=%22role%22%0D%0A%0D%0A',
            'admin%0D%0A--BOUND%0D%0A',
            'Content-Disposition: form-data; name=%22_ignore',
        ]);

        self::assertSame(
            3,
            \preg_match_all('/(?:^|\r\n)--BOUND(?:\r\n|--\r\n)/', $serialized)
        );

        self::assertSame(
            2,
            \preg_match_all('/(?:^|\r\n)Content-Disposition: form-data; /', $serialized)
        );

        self::assertStringNotContainsString(
            "\r\n--BOUND\r\nContent-Disposition: form-data; name=\"role\"\r\n\r\nadmin",
            $serialized
        );

        self::assertStringContainsString(
            "Content-Disposition: form-data; name=\"{$escapedName}\"",
            $serialized
        );

        self::assertStringContainsString(
            "Content-Disposition: form-data; name=\"realfield\"\r\n\r\nreal-value",
            $serialized
        );
    }

    public function testSerializesLiteralBackslashesUnchangedInGeneratedContentDispositionName(): void
    {
        $b = new MultipartStream([
            [
                'name' => 'field\\name',
                'contents' => 'value',
            ],
        ], 'boundary');

        $expected = \implode('', [
            "--boundary\r\n",
            "Content-Disposition: form-data; name=\"field\\name\"\r\n",
            "\r\n",
            "value\r\n",
            "--boundary--\r\n",
        ]);

        self::assertSame($expected, (string) $b);
    }

    public function testRejectsGeneratedContentDispositionNameWithNul(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid multipart part header value:');

        new MultipartStream([
            [
                'name' => "field\0name",
                'contents' => 'value',
            ],
        ], 'boundary');
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
            "\r\n",
            "bar\r\n",
            "--boundary\r\n",
            "Content-Disposition: form-data; name=\"foo[1][key]\"\r\n",
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
            "\r\n",
            "php\r\n",
            "--boundary\r\n",
            "Content-Disposition: form-data; name=\"tags[1]\"\r\n",
            "\r\n",
            "guzzle\r\n",
            "--boundary\r\n",
            "Content-Disposition: form-data; name=\"tags[2]\"\r\n",
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
            "\r\n",
            "John\r\n",
            "--boundary\r\n",
            "Content-Disposition: form-data; name=\"user[email]\"\r\n",
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
            "\r\n",
            "42\r\n",
            "--boundary\r\n",
            "Content-Disposition: form-data; name=\"mixed[float]\"\r\n",
            "\r\n",
            "3.14\r\n",
            "--boundary\r\n",
            "Content-Disposition: form-data; name=\"mixed[bool_true]\"\r\n",
            "\r\n",
            "1\r\n",
            "--boundary\r\n",
            "Content-Disposition: form-data; name=\"mixed[bool_false]\"\r\n",
            "\r\n",
            "\r\n",
            "--boundary\r\n",
            "Content-Disposition: form-data; name=\"mixed[string]\"\r\n",
            "\r\n",
            "hello\r\n",
            "--boundary--\r\n",
        ]);

        self::assertSame($expected, (string) $b);
    }

    /**
     * @dataProvider nonFiniteFloatContentsProvider
     */
    public function testRejectsNonFiniteFloatContents(float $value): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot create a stream from a non-finite float.');

        new MultipartStream([
            [
                'name' => 'field',
                'contents' => $value,
            ],
        ]);
    }

    /**
     * @dataProvider nonFiniteFloatContentsProvider
     */
    public function testRejectsNonFiniteFloatInArrayContents(float $value): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot create a stream from a non-finite float.');

        new MultipartStream([
            [
                'name' => 'field',
                'contents' => ['nested' => $value],
            ],
        ]);
    }

    public static function nonFiniteFloatContentsProvider(): iterable
    {
        yield 'NAN' => [\NAN];
        yield 'INF' => [\INF];
        yield '-INF' => [-\INF];
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
            "\r\n",
            "ten\r\n",
            "--boundary\r\n",
            "Content-Disposition: form-data; name=\"items[20]\"\r\n",
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
            "Content-Type: application/pdf\r\n",
            "\r\n",
            "file contents\r\n",
            "--boundary\r\n",
            "Content-Disposition: form-data; name=\"files[note]\"\r\n",
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

    public function testEscapesGeneratedContentDispositionFilename(): void
    {
        $b = new MultipartStream([
            [
                'name' => 'upload',
                'contents' => 'body',
                'filename' => "avatar\"\r\n.txt",
            ],
        ], 'boundary');

        $expected = \implode('', [
            "--boundary\r\n",
            "Content-Disposition: form-data; name=\"upload\"; filename=\"avatar%22%0D%0A.txt\"\r\n",
            "Content-Type: text/plain\r\n",
            "\r\n",
            "body\r\n",
            "--boundary--\r\n",
        ]);

        self::assertSame($expected, (string) $b);
    }

    public function testSerializesLiteralBackslashesUnchangedInGeneratedContentDispositionFilename(): void
    {
        $b = new MultipartStream([
            [
                'name' => 'upload',
                'contents' => 'body',
                'filename' => 'avatar\\name.txt',
            ],
        ], 'boundary');

        $expected = \implode('', [
            "--boundary\r\n",
            "Content-Disposition: form-data; name=\"upload\"; filename=\"avatar\\name.txt\"\r\n",
            "Content-Type: text/plain\r\n",
            "\r\n",
            "body\r\n",
            "--boundary--\r\n",
        ]);

        self::assertSame($expected, (string) $b);
    }

    public function testEscapesUriDerivedContentDispositionFilename(): void
    {
        $file = Psr7\FnStream::decorate(Psr7\Utils::streamFor('body'), [
            'getMetadata' => static function (): string {
                return "/foo/avatar\"\r\n.txt";
            },
        ]);

        $b = new MultipartStream([
            [
                'name' => 'upload',
                'contents' => $file,
            ],
        ], 'boundary');

        $expected = \implode('', [
            "--boundary\r\n",
            "Content-Disposition: form-data; name=\"upload\"; filename=\"avatar%22%0D%0A.txt\"\r\n",
            "Content-Type: text/plain\r\n",
            "\r\n",
            "body\r\n",
            "--boundary--\r\n",
        ]);

        self::assertSame($expected, (string) $b);
    }

    public function testSerializesLiteralBackslashesUnchangedInUriDerivedContentDispositionFilename(): void
    {
        $file = Psr7\FnStream::decorate(Psr7\Utils::streamFor('body'), [
            'getMetadata' => static function (): string {
                return '/foo/avatar\\name.txt';
            },
        ]);

        $b = new MultipartStream([
            [
                'name' => 'upload',
                'contents' => $file,
            ],
        ], 'boundary');

        $expected = \implode('', [
            "--boundary\r\n",
            "Content-Disposition: form-data; name=\"upload\"; filename=\"avatar\\name.txt\"\r\n",
            "Content-Type: text/plain\r\n",
            "\r\n",
            "body\r\n",
            "--boundary--\r\n",
        ]);

        self::assertSame($expected, (string) $b);
    }

    public function testRejectsGeneratedContentDispositionFilenameWithNul(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid multipart part header value:');

        new MultipartStream([
            [
                'name' => 'upload',
                'contents' => 'body',
                'filename' => "avatar\0.txt",
            ],
        ], 'boundary');
    }

    public function testSerializesFilesWithMixedNewlines(): void
    {
        $content = "LF\nCRLF\r\nCR\r";

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

    public function testPreservesTrailingWhitespaceInFinalCustomPartHeader(): void
    {
        $b = new MultipartStream([
            [
                'name' => 'field',
                'contents' => 'body',
                'headers' => [
                    'Content-Disposition' => 'form-data; name="field"',
                    'X-Trailing' => "value \t",
                ],
            ],
        ], 'boundary');

        $expected = \implode('', [
            "--boundary\r\n",
            "Content-Disposition: form-data; name=\"field\"\r\n",
            "X-Trailing: value \t\r\n",
            "\r\n",
            "body\r\n",
            "--boundary--\r\n",
        ]);

        self::assertSame($expected, (string) $b);
        self::assertSame(\strlen($expected), $b->getSize());
    }

    public function testPreservesAllWhitespaceFinalCustomPartHeaderValue(): void
    {
        $b = new MultipartStream([
            [
                'name' => 'field',
                'contents' => 'body',
                'headers' => [
                    'Content-Disposition' => 'form-data; name="field"',
                    'X-Blank' => " \t ",
                ],
            ],
        ], 'boundary');

        $expected = \implode('', [
            "--boundary\r\n",
            "Content-Disposition: form-data; name=\"field\"\r\n",
            "X-Blank:  \t \r\n",
            "\r\n",
            "body\r\n",
            "--boundary--\r\n",
        ]);

        self::assertSame($expected, (string) $b);
        self::assertSame(\strlen($expected), $b->getSize());
    }

    /**
     * @dataProvider unstringableCustomHeaderValueProvider
     */
    public function testRejectsUnstringableCustomHeaderValues($value): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Multipart part header value must be a string.');

        new MultipartStream([
            [
                'name' => 'field',
                'contents' => 'body',
                'headers' => ['X-Test' => $value],
            ],
        ], 'boundary');
    }

    public static function unstringableCustomHeaderValueProvider(): iterable
    {
        yield 'array' => [['value']];
        yield 'object' => [new \stdClass()];
    }

    public function testRejectsResourceCustomHeaderValue(): void
    {
        $resource = fopen('php://temp', 'r');
        self::assertIsResource($resource);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Multipart part header value must be a string.');

        try {
            new MultipartStream([
                [
                    'name' => 'field',
                    'contents' => 'body',
                    'headers' => ['X-Test' => $resource],
                ],
            ], 'boundary');
        } finally {
            fclose($resource);
        }
    }

    public function testSerializesNumericCustomHeaderNames(): void
    {
        $b = new MultipartStream([
            [
                'name' => 'field',
                'contents' => 'body',
                'headers' => [123 => 'value'],
            ],
        ], 'boundary');

        $expected = \implode('', [
            "--boundary\r\n",
            "123: value\r\n",
            "Content-Disposition: form-data; name=\"field\"\r\n",
            "\r\n",
            "body\r\n",
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

    /**
     * @dataProvider invalidCustomPartHeaderNameProvider
     */
    public function testRejectsInvalidCustomPartHeaderNames(string $header, string $message): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage($message);

        new MultipartStream([
            [
                'name' => 'field',
                'contents' => 'body',
                'headers' => [$header => 'value'],
            ],
        ], 'boundary');
    }

    public static function invalidCustomPartHeaderNameProvider(): iterable
    {
        yield 'empty' => ['', 'Invalid multipart part header name: '];
        yield 'space' => ['Bad Header', 'Invalid multipart part header name: Bad Header'];
        yield 'carriage return' => ["Bad\rHeader", 'Invalid multipart part header name: Bad\\x0DHeader'];
        yield 'line feed' => ["Bad\nHeader", 'Invalid multipart part header name: Bad\\x0AHeader'];
    }

    /**
     * @dataProvider invalidCustomPartHeaderValueProvider
     */
    public function testRejectsInvalidCustomPartHeaderValues(string $value, string $message): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage($message);

        new MultipartStream([
            [
                'name' => 'field',
                'contents' => 'body',
                'headers' => ['X-Test' => $value],
            ],
        ], 'boundary');
    }

    public static function invalidCustomPartHeaderValueProvider(): iterable
    {
        yield 'carriage return' => [
            "ok\rX-Injected: yes",
            'Invalid multipart part header value: ok\\x0DX-Injected: yes',
        ];
        yield 'line feed' => [
            "ok\nX-Injected: yes",
            'Invalid multipart part header value: ok\\x0AX-Injected: yes',
        ];
        yield 'nul' => [
            "ok\0bad",
            'Invalid multipart part header value: ok\\x00bad',
        ];
    }

    public function testRejectsNonStringCustomPartHeaderValue(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Multipart part header value must be a string.');

        new MultipartStream([
            [
                'name' => 'field',
                'contents' => 'body',
                'headers' => ['X-Test' => 123],
            ],
        ], 'boundary');
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
