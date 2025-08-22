<?php

declare(strict_types=1);

namespace GuzzleHttp\Tests\Psr7;

use GuzzleHttp\Psr7;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Uri;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\StreamInterface;

/**
 * @covers \GuzzleHttp\Psr7\MessageTrait
 * @covers \GuzzleHttp\Psr7\Request
 */
class RequestTest extends TestCase
{
    public function testRequestUriMayBeString(): void
    {
        $r = new Request('GET', '/');
        self::assertSame('/', (string) $r->getUri());
    }

    public function testRequestUriMayBeUri(): void
    {
        $uri = new Uri('/');
        $r = new Request('GET', $uri);
        self::assertSame($uri, $r->getUri());
    }

    public function testValidateRequestUri(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Request('GET', '///');
    }

    public function testCanConstructWithBody(): void
    {
        $r = new Request('GET', '/', [], 'baz');
        self::assertInstanceOf(StreamInterface::class, $r->getBody());
        self::assertSame('baz', (string) $r->getBody());
    }

    public function testNullBody(): void
    {
        $r = new Request('GET', '/', [], null);
        self::assertInstanceOf(StreamInterface::class, $r->getBody());
        self::assertSame('', (string) $r->getBody());
    }

    public function testFalseyBody(): void
    {
        $r = new Request('GET', '/', [], '0');
        self::assertInstanceOf(StreamInterface::class, $r->getBody());
        self::assertSame('0', (string) $r->getBody());
    }

    public function testConstructorDoesNotReadStreamBody(): void
    {
        $streamIsRead = false;
        $body = Psr7\FnStream::decorate(Psr7\Utils::streamFor(''), [
            '__toString' => function () use (&$streamIsRead) {
                $streamIsRead = true;

                return '';
            },
        ]);

        $r = new Request('GET', '/', [], $body);
        self::assertFalse($streamIsRead);
        self::assertSame($body, $r->getBody());
    }

    public function testCapitalizesMethod(): void
    {
        $r = new Request('get', '/');
        self::assertSame('GET', $r->getMethod());
    }

    public function testCapitalizesWithMethod(): void
    {
        $r = new Request('GET', '/');
        self::assertSame('PUT', $r->withMethod('put')->getMethod());
    }

    public function testWithUri(): void
    {
        $r1 = new Request('GET', '/');
        $u1 = $r1->getUri();
        $u2 = new Uri('http://www.example.com');
        $r2 = $r1->withUri($u2);
        self::assertNotSame($r1, $r2);
        self::assertSame($u2, $r2->getUri());
        self::assertSame($u1, $r1->getUri());
    }

    /**
     * @dataProvider invalidMethodsProvider
     */
    public function testConstructWithInvalidMethods($method): void
    {
        $this->expectException(\TypeError::class);
        new Request($method, '/');
    }

    /**
     * @dataProvider invalidMethodsProvider
     */
    public function testWithInvalidMethods($method): void
    {
        $r = new Request('get', '/');
        $this->expectException(\InvalidArgumentException::class);
        $r->withMethod($method);
    }

    public static function invalidMethodsProvider(): iterable
    {
        return [
            [null],
            [false],
            [['foo']],
            [new \stdClass()],
        ];
    }

    public function testSameInstanceWhenSameUri(): void
    {
        $r1 = new Request('GET', 'http://foo.com');
        $r2 = $r1->withUri($r1->getUri());
        self::assertSame($r1, $r2);
    }

    public function testWithRequestTarget(): void
    {
        $r1 = new Request('GET', '/');
        $r2 = $r1->withRequestTarget('*');
        self::assertSame('*', $r2->getRequestTarget());
        self::assertSame('/', $r1->getRequestTarget());
    }

    public function testRequestTargetDoesNotAllowSpaces(): void
    {
        $r1 = new Request('GET', '/');
        $this->expectException(\InvalidArgumentException::class);
        $r1->withRequestTarget('/foo bar');
    }

    public function testRequestTargetDefaultsToSlash(): void
    {
        $r1 = new Request('GET', '');
        self::assertSame('/', $r1->getRequestTarget());
        $r2 = new Request('GET', '*');
        self::assertSame('*', $r2->getRequestTarget());
        $r3 = new Request('GET', 'http://foo.com/bar baz/');
        self::assertSame('/bar%20baz/', $r3->getRequestTarget());
    }

    public function testBuildsRequestTarget(): void
    {
        $r1 = new Request('GET', 'http://foo.com/baz?bar=bam');
        self::assertSame('/baz?bar=bam', $r1->getRequestTarget());
    }

    public function testBuildsRequestTargetWithFalseyQuery(): void
    {
        $r1 = new Request('GET', 'http://foo.com/baz?0');
        self::assertSame('/baz?0', $r1->getRequestTarget());
    }

    public function testHostIsAddedFirst(): void
    {
        $r = new Request('GET', 'http://foo.com/baz?bar=bam', ['Foo' => 'Bar']);
        self::assertSame([
            'Host' => ['foo.com'],
            'Foo' => ['Bar'],
        ], $r->getHeaders());
    }

    public function testHeaderValueWithWhitespace(): void
    {
        $r = new Request('GET', 'https://example.com/', [
            'User-Agent' => 'Linux f0f489981e90 5.10.104-linuxkit 1 SMP Wed Mar 9 19:05:23 UTC 2022 x86_64',
        ]);
        self::assertSame([
            'Host' => ['example.com'],
            'User-Agent' => ['Linux f0f489981e90 5.10.104-linuxkit 1 SMP Wed Mar 9 19:05:23 UTC 2022 x86_64'],
        ], $r->getHeaders());
    }

    public function testEmptyListHeaderValue(): void
    {
        $r = new Request('GET', 'https://example.com/', ['Foo' => []]);
        self::assertSame('', $r->getHeaderLine('Foo'));
        self::assertSame([], $r->getHeader('Foo'));
        self::assertSame(['Host' => ['example.com'], 'Foo' => []], $r->getHeaders());
    }

    public function testCanGetHeaderAsCsv(): void
    {
        $r = new Request('GET', 'http://foo.com/baz?bar=bam', [
            'Foo' => ['a', 'b', 'c'],
        ]);
        self::assertSame('a, b, c', $r->getHeaderLine('Foo'));
        self::assertSame('', $r->getHeaderLine('Bar'));
    }

    /**
     * @dataProvider provideHeadersContainingNotAllowedChars
     */
    public function testContainsNotAllowedCharsOnHeaderField($header): void
    {
        $this->expectExceptionMessage(
            sprintf(
                '"%s" is not valid header name',
                $header
            )
        );
        $r = new Request(
            'GET',
            'http://foo.com/baz?bar=bam',
            [
                $header => 'value',
            ]
        );
    }

    public static function provideHeadersContainingNotAllowedChars(): iterable
    {
        return [[' key '], ['key '], [' key'], ['key/'], ['key('], ['key\\'], [' ']];
    }

    /**
     * @dataProvider provideHeadersContainsAllowedChar
     */
    public function testContainsAllowedCharsOnHeaderField($header): void
    {
        $r = new Request(
            'GET',
            'http://foo.com/baz?bar=bam',
            [
                $header => 'value',
            ]
        );
        self::assertArrayHasKey($header, $r->getHeaders());
    }

    public static function provideHeadersContainsAllowedChar(): iterable
    {
        return [
            ['key'],
            ['key#'],
            ['key$'],
            ['key%'],
            ['key&'],
            ['key*'],
            ['key+'],
            ['key.'],
            ['key^'],
            ['key_'],
            ['key|'],
            ['key~'],
            ['key!'],
            ['key-'],
            ["key'"],
            ['key`'],
        ];
    }

    public function testHostIsNotOverwrittenWhenPreservingHost(): void
    {
        $r = new Request('GET', 'http://foo.com/baz?bar=bam', ['Host' => 'a.com']);
        self::assertSame(['Host' => ['a.com']], $r->getHeaders());
        $r2 = $r->withUri(new Uri('http://www.foo.com/bar'), true);
        self::assertSame('a.com', $r2->getHeaderLine('Host'));
    }

    public function testWithUriSetsHostIfNotSet(): void
    {
        $r = (new Request('GET', 'http://foo.com/baz?bar=bam'))->withoutHeader('Host');
        self::assertSame([], $r->getHeaders());
        $r2 = $r->withUri(new Uri('http://www.baz.com/bar'), true);
        self::assertSame('www.baz.com', $r2->getHeaderLine('Host'));
    }

    public function testOverridesHostWithUri(): void
    {
        $r = new Request('GET', 'http://foo.com/baz?bar=bam');
        self::assertSame(['Host' => ['foo.com']], $r->getHeaders());
        $r2 = $r->withUri(new Uri('http://www.baz.com/bar'));
        self::assertSame('www.baz.com', $r2->getHeaderLine('Host'));
    }

    public function testAggregatesHeaders(): void
    {
        $r = new Request('GET', '', [
            'ZOO' => 'zoobar',
            'zoo' => ['foobar', 'zoobar'],
        ]);
        self::assertSame(['ZOO' => ['zoobar', 'foobar', 'zoobar']], $r->getHeaders());
        self::assertSame('zoobar, foobar, zoobar', $r->getHeaderLine('zoo'));
    }

    public function testAddsPortToHeader(): void
    {
        $r = new Request('GET', 'http://foo.com:8124/bar');
        self::assertSame('foo.com:8124', $r->getHeaderLine('host'));
    }

    public function testAddsPortToHeaderAndReplacePreviousPort(): void
    {
        $r = new Request('GET', 'http://foo.com:8124/bar');
        $r = $r->withUri(new Uri('http://foo.com:8125/bar'));
        self::assertSame('foo.com:8125', $r->getHeaderLine('host'));
    }

    /**
     * @dataProvider provideHeaderValuesContainingNotAllowedChars
     */
    public function testContainsNotAllowedCharsOnHeaderValue(string $value): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(sprintf('"%s" is not valid header value', $value));

        $r = new Request(
            'GET',
            'http://foo.com/baz?bar=bam',
            [
                'testing' => $value,
            ]
        );
    }

    public static function provideHeaderValuesContainingNotAllowedChars(): iterable
    {
        // Explicit tests for newlines as the most common exploit vector.
        $tests = [
            ["new\nline"],
            ["new\r\nline"],
            ["new\rline"],
            // Line folding is technically allowed, but deprecated.
            // We don't support it.
            ["new\r\n line"],
            ["newline\n"],
            ["\nnewline"],
            ["newline\r\n"],
            ["\r\nnewline"],
        ];

        for ($i = 0; $i <= 0xFF; ++$i) {
            if (\chr($i) == "\t") {
                continue;
            }
            if (\chr($i) == ' ') {
                continue;
            }
            if ($i >= 0x21 && $i <= 0x7E) {
                continue;
            }
            if ($i >= 0x80) {
                continue;
            }

            $tests[] = ['foo'.\chr($i).'bar'];
            $tests[] = ['foo'.\chr($i)];
        }

        return $tests;
    }
}
