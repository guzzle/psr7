<?php

declare(strict_types=1);

namespace GuzzleHttp\Tests\Psr7;

use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriNormalizer;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\UriInterface;

/**
 * @covers \GuzzleHttp\Psr7\UriNormalizer
 */
class UriNormalizerTest extends TestCase
{
    public function testCapitalizePercentEncoding(): void
    {
        $actualEncoding = 'a%c2%7A%5eb%25%fa%fA%Fa';
        $expectEncoding = 'a%C2%7A%5Eb%25%FA%FA%FA';
        $uri = (new Uri())
            ->withPath("/$actualEncoding")
            ->withQuery($actualEncoding)
            ->withFragment($actualEncoding);

        self::assertSame("/$actualEncoding?$actualEncoding#$actualEncoding", (string) $uri, 'Not normalized automatically beforehand');

        $normalizedUri = UriNormalizer::normalize($uri, UriNormalizer::CAPITALIZE_PERCENT_ENCODING);

        self::assertInstanceOf(UriInterface::class, $normalizedUri);
        self::assertSame("/$expectEncoding?$expectEncoding#$expectEncoding", (string) $normalizedUri);
    }

    /**
     * @dataProvider getUnreservedCharacters
     */
    public function testDecodeUnreservedCharacters(string $char): void
    {
        $percentEncoded = '%'.bin2hex($char);
        // Add encoded reserved characters to test that those are not decoded and include the percent-encoded
        // unreserved character both in lower and upper case to test the decoding is case-insensitive.
        $encodedChars = $percentEncoded.'%2F%5B'.strtoupper($percentEncoded);
        $uri = (new Uri())
            ->withPath("/$encodedChars")
            ->withQuery($encodedChars)
            ->withFragment($encodedChars);

        self::assertSame("/$encodedChars?$encodedChars#$encodedChars", (string) $uri, 'Not normalized automatically beforehand');

        $normalizedUri = UriNormalizer::normalize($uri, UriNormalizer::DECODE_UNRESERVED_CHARACTERS);

        self::assertInstanceOf(UriInterface::class, $normalizedUri);
        self::assertSame("/$char%2F%5B$char?$char%2F%5B$char#$char%2F%5B$char", (string) $normalizedUri);
    }

    public static function getUnreservedCharacters(): iterable
    {
        $unreservedChars = array_merge(range('a', 'z'), range('A', 'Z'), range(0, 9), ['-', '.', '_', '~']);

        return array_map(function ($char) {
            return [(string) $char];
        }, $unreservedChars);
    }

    /**
     * @dataProvider getEmptyPathTestCases
     */
    public function testConvertEmptyPath(string $uri, string $expected): void
    {
        $normalizedUri = UriNormalizer::normalize(new Uri($uri), UriNormalizer::CONVERT_EMPTY_PATH);

        self::assertInstanceOf(UriInterface::class, $normalizedUri);
        self::assertSame($expected, (string) $normalizedUri);
    }

    public static function getEmptyPathTestCases(): iterable
    {
        return [
            ['http://example.org', 'http://example.org/'],
            ['https://example.org', 'https://example.org/'],
            ['urn://example.org', 'urn://example.org'],
        ];
    }

    public function testRemoveDefaultHost(): void
    {
        $uri = new Uri('file://localhost/myfile');
        $normalizedUri = UriNormalizer::normalize($uri, UriNormalizer::REMOVE_DEFAULT_HOST);

        self::assertInstanceOf(UriInterface::class, $normalizedUri);
        self::assertSame('file:///myfile', (string) $normalizedUri);
    }

    public function testRemoveDefaultHostWithEmptyPath(): void
    {
        $uri = new Uri('file://localhost');
        $normalizedUri = UriNormalizer::normalize($uri, UriNormalizer::REMOVE_DEFAULT_HOST);

        self::assertInstanceOf(UriInterface::class, $normalizedUri);
        self::assertSame('file:', (string) $normalizedUri);
    }

    public function testRemoveDefaultPort(): void
    {
        $uri = $this->createMock(UriInterface::class);
        $uri->expects(self::any())->method('getScheme')->willReturn('http');
        $uri->expects(self::any())->method('getPort')->willReturn(80);
        $uri->expects(self::once())->method('withPort')->with(null)->willReturn(new Uri('http://example.org'));

        $normalizedUri = UriNormalizer::normalize($uri, UriNormalizer::REMOVE_DEFAULT_PORT);

        self::assertInstanceOf(UriInterface::class, $normalizedUri);
        self::assertNull($normalizedUri->getPort());
    }

    public function testRemoveDotSegments(): void
    {
        $uri = new Uri('http://example.org/../a/b/../c/./d.html');
        $normalizedUri = UriNormalizer::normalize($uri, UriNormalizer::REMOVE_DOT_SEGMENTS);

        self::assertInstanceOf(UriInterface::class, $normalizedUri);
        self::assertSame('http://example.org/a/c/d.html', (string) $normalizedUri);
    }

    public function testRemoveDotSegmentsOfAbsolutePathReference(): void
    {
        $uri = new Uri('/../a/b/../c/./d.html');
        $normalizedUri = UriNormalizer::normalize($uri, UriNormalizer::REMOVE_DOT_SEGMENTS);

        self::assertInstanceOf(UriInterface::class, $normalizedUri);
        self::assertSame('/a/c/d.html', (string) $normalizedUri);
    }

    public function testRemoveDotSegmentsOfRelativePathReference(): void
    {
        $uri = new Uri('../c/./d.html');
        $normalizedUri = UriNormalizer::normalize($uri, UriNormalizer::REMOVE_DOT_SEGMENTS);

        self::assertInstanceOf(UriInterface::class, $normalizedUri);
        self::assertSame('../c/./d.html', (string) $normalizedUri);
    }

    public function testRemoveDuplicateSlashes(): void
    {
        $uri = new Uri('http://example.org//foo///bar/bam.html');
        $normalizedUri = UriNormalizer::normalize($uri, UriNormalizer::REMOVE_DUPLICATE_SLASHES);

        self::assertInstanceOf(UriInterface::class, $normalizedUri);
        self::assertSame('http://example.org/foo/bar/bam.html', (string) $normalizedUri);
    }

    public function testRemoveDotSegmentsRetainsDuplicateSlashes(): void
    {
        $uri = new Uri('http://example.org//a/b/../c/./d.html');
        $normalizedUri = UriNormalizer::normalize($uri, UriNormalizer::REMOVE_DOT_SEGMENTS);

        self::assertInstanceOf(UriInterface::class, $normalizedUri);
        self::assertSame('http://example.org//a/c/d.html', (string) $normalizedUri);
    }

    public function testRemoveDotSegmentsAboveRootRetainsDuplicateSlashes(): void
    {
        $uri = new Uri('http://example.org/..//a/../..//b.html');
        $normalizedUri = UriNormalizer::normalize($uri, UriNormalizer::REMOVE_DOT_SEGMENTS);

        self::assertInstanceOf(UriInterface::class, $normalizedUri);
        self::assertSame('http://example.org//b.html', (string) $normalizedUri);
    }

    public function testRemoveDotSegmentsGuardsPathOfAuthorityLessUri(): void
    {
        $uri = new Uri('urn:/..//x');
        $normalizedUri = UriNormalizer::normalize($uri, UriNormalizer::REMOVE_DOT_SEGMENTS);

        self::assertInstanceOf(UriInterface::class, $normalizedUri);
        self::assertSame('urn:/.//x', (string) $normalizedUri);
        // the "/." prefix is stable under repeated normalization
        self::assertSame('urn:/.//x', (string) UriNormalizer::normalize($normalizedUri, UriNormalizer::REMOVE_DOT_SEGMENTS));
    }

    public function testRemoveDotSegmentsDoesNotGuardPathOfHostlessHttpUri(): void
    {
        $uri = new Uri('http:/a/..//b');
        $normalizedUri = UriNormalizer::normalize($uri, UriNormalizer::REMOVE_DOT_SEGMENTS);

        self::assertInstanceOf(UriInterface::class, $normalizedUri);
        self::assertSame('http://localhost//b', (string) $normalizedUri);
        // the default host makes the path unambiguous, keeping normalization idempotent
        self::assertSame('http://localhost//b', (string) UriNormalizer::normalize($normalizedUri, UriNormalizer::REMOVE_DOT_SEGMENTS));
    }

    public function testRemoveDotSegmentsAndDuplicateSlashesOnAuthorityLessUri(): void
    {
        $uri = new Uri('urn:/..//x');
        $normalizedUri = UriNormalizer::normalize($uri, UriNormalizer::REMOVE_DOT_SEGMENTS | UriNormalizer::REMOVE_DUPLICATE_SLASHES);

        self::assertInstanceOf(UriInterface::class, $normalizedUri);
        self::assertSame('urn:/x', (string) $normalizedUri);
    }

    public function testPreservingNormalizationsRetainDuplicateSlashes(): void
    {
        $uri = new Uri('http://example.org//a%c2%b1b/./p%61th');
        $normalizedUri = UriNormalizer::normalize($uri);

        self::assertInstanceOf(UriInterface::class, $normalizedUri);
        self::assertSame('http://example.org//a%C2%B1b/path', (string) $normalizedUri);
    }

    public function testPreservingNormalizationsGuardPathOfAuthorityLessUri(): void
    {
        $uri = new Uri('urn:a/..///x');
        $normalizedUri = UriNormalizer::normalize($uri);

        self::assertInstanceOf(UriInterface::class, $normalizedUri);
        self::assertSame('urn:/.//x', (string) $normalizedUri);
    }

    public function testNormalizePreservesRootlessFileUriFromExtendedInstances(): void
    {
        $uri = new class('file:foo/bar') extends Uri {
        };

        $normalizedUri = UriNormalizer::normalize($uri);

        self::assertInstanceOf(UriInterface::class, $normalizedUri);
        self::assertSame('file:foo/bar', (string) $normalizedUri);
    }

    public function testSortQueryParameters(): void
    {
        $uri = new Uri('?lang=en&article=fred');
        $normalizedUri = UriNormalizer::normalize($uri, UriNormalizer::SORT_QUERY_PARAMETERS);

        self::assertInstanceOf(UriInterface::class, $normalizedUri);
        self::assertSame('?article=fred&lang=en', (string) $normalizedUri);
    }

    public function testSortQueryParametersWithSameKeys(): void
    {
        $uri = new Uri('?a=b&b=c&a=a&a&b=a&b=b&a=d&a=c');
        $normalizedUri = UriNormalizer::normalize($uri, UriNormalizer::SORT_QUERY_PARAMETERS);

        self::assertInstanceOf(UriInterface::class, $normalizedUri);
        self::assertSame('?a&a=a&a=b&a=c&a=d&b=a&b=b&b=c', (string) $normalizedUri);
    }

    public function testCanonicalizeIpv6Host(): void
    {
        $uri = $this->createMock(UriInterface::class);
        $uri->expects(self::any())->method('getHost')->willReturn('[::0:0A]');
        $uri->expects(self::once())->method('withHost')->with('[::a]')->willReturn(new Uri('http://[::a]'));

        $normalizedUri = UriNormalizer::normalize($uri, UriNormalizer::CANONICALIZE_IPV6_HOST);

        self::assertInstanceOf(UriInterface::class, $normalizedUri);
        self::assertSame('[::a]', $normalizedUri->getHost());
    }

    public function testCanonicalizeIpv6HostLowercasesForeignHosts(): void
    {
        $uri = $this->createMock(UriInterface::class);
        $uri->expects(self::any())->method('getHost')->willReturn('[FE80::1]');
        $uri->expects(self::once())->method('withHost')->with('[fe80::1]')->willReturn(new Uri('http://[fe80::1]'));

        $normalizedUri = UriNormalizer::normalize($uri, UriNormalizer::CANONICALIZE_IPV6_HOST);

        self::assertInstanceOf(UriInterface::class, $normalizedUri);
        self::assertSame('[fe80::1]', $normalizedUri->getHost());
    }

    public function testCanonicalizeIpv6HostKeepsHostsTheImplementationCannotRetain(): void
    {
        $result = $this->createMock(UriInterface::class);
        $result->expects(self::any())->method('getHost')->willReturn('[::0:0a]');

        $uri = $this->createMock(UriInterface::class);
        $uri->expects(self::any())->method('getHost')->willReturn('[::0:0A]');
        $uri->expects(self::once())->method('withHost')->with('[::a]')->willReturn($result);

        $normalizedUri = UriNormalizer::normalize($uri, UriNormalizer::CANONICALIZE_IPV6_HOST);

        self::assertSame($uri, $normalizedUri);
    }

    public function testCanonicalizeIpv6HostDiscardsUnrelatedSetterResults(): void
    {
        $result = $this->createMock(UriInterface::class);
        $result->expects(self::any())->method('getHost')->willReturn('attacker.example');

        $uri = $this->createMock(UriInterface::class);
        $uri->expects(self::any())->method('getHost')->willReturn('[::0:0A]');
        $uri->expects(self::once())->method('withHost')->with('[::a]')->willReturn($result);

        $normalizedUri = UriNormalizer::normalize($uri, UriNormalizer::CANONICALIZE_IPV6_HOST);

        self::assertSame($uri, $normalizedUri);
    }

    public function testCanonicalizeIpv6HostPropagatesSetterExceptions(): void
    {
        $uri = $this->createMock(UriInterface::class);
        $uri->expects(self::any())->method('getHost')->willReturn('[::0:0A]');
        $uri->expects(self::once())->method('withHost')->with('[::a]')->willThrowException(new \RuntimeException('setter failed'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('setter failed');

        UriNormalizer::normalize($uri, UriNormalizer::CANONICALIZE_IPV6_HOST);
    }

    public function testCanonicalizeIpv6HostFallbackPreservesEarlierNormalizations(): void
    {
        // CONVERT_EMPTY_PATH succeeds first; the host step is then discarded
        // because the implementation does not retain the canonical spelling.
        $withPath = $this->createMock(UriInterface::class);
        $withPath->expects(self::any())->method('getScheme')->willReturn('http');
        $withPath->expects(self::any())->method('getPath')->willReturn('/');
        $withPath->expects(self::any())->method('getHost')->willReturn('[::0:0A]');
        $withPath->expects(self::once())->method('withHost')->with('[::a]')->willReturnSelf();

        $uri = $this->createMock(UriInterface::class);
        $uri->expects(self::any())->method('getScheme')->willReturn('http');
        $uri->expects(self::any())->method('getPath')->willReturn('');
        $uri->expects(self::once())->method('withPath')->with('/')->willReturn($withPath);

        $normalizedUri = UriNormalizer::normalize($uri, UriNormalizer::CONVERT_EMPTY_PATH | UriNormalizer::CANONICALIZE_IPV6_HOST);

        self::assertSame($withPath, $normalizedUri);
        self::assertSame('/', $normalizedUri->getPath());
        self::assertSame('[::0:0A]', $normalizedUri->getHost());
    }

    /**
     * @dataProvider getNonCanonicalizableHosts
     */
    public function testCanonicalizeIpv6HostLeavesOtherHostsUntouched(string $host): void
    {
        $uri = $this->createMock(UriInterface::class);
        $uri->expects(self::any())->method('getHost')->willReturn($host);
        $uri->expects(self::never())->method('withHost');

        $normalizedUri = UriNormalizer::normalize($uri, UriNormalizer::CANONICALIZE_IPV6_HOST);

        self::assertSame($uri, $normalizedUri);
    }

    public static function getNonCanonicalizableHosts(): iterable
    {
        return [
            ['example.com'],
            ['[::1]'],
            ['[v1.fe]'],
            ['[fe80::1%25eth0]'],
            ['::0:1'],
            ['[gggg::1]'],
            ['[::ffff:192.168.001.001]'],
        ];
    }

    /**
     * @dataProvider getEquivalentTestCases
     */
    public function testIsEquivalent(string $uri1, string $uri2, bool $expected): void
    {
        $equivalent = UriNormalizer::isEquivalent(new Uri($uri1), new Uri($uri2));

        self::assertSame($expected, $equivalent);
    }

    public static function getEquivalentTestCases(): iterable
    {
        return [
            ['http://example.org', 'http://example.org', true],
            ['hTTp://eXaMpLe.org', 'http://example.org', true],
            ['http://example.org/path?#', 'http://example.org/path', true],
            ['http://example.org:80', 'http://example.org/', true],
            ['http://example.org/../a/.././p%61th?%7a=%5e', 'http://example.org/path?z=%5E', true],
            ['http://example.org/..//a', 'http://example.org//a', true],
            ['http://example.org/..//a', 'http://example.org/a', false],
            ['urn:/..//x', 'urn:/.//x', true],
            ['http:/a/..//b', 'http:/%61/..//b', true],
            ['http://example.org/path#fr%61g%c2%b1', 'http://example.org/path#frag%C2%B1', true],
            ['http://[::0:1]/', 'http://[::1]/', true],
            ['http://[0:0:0:0:0:0:0:1]/', 'http://[::1]/', true],
            ['http://[::1]/', 'http://[::2]/', false],
            ['https://example.org/', 'http://example.org/', false],
            ['https://example.org/', '//example.org/', false],
            ['//example.org/', '//example.org/', true],
            ['file:/myfile', 'file:///myfile', true],
            ['file:///myfile', 'file://localhost/myfile', true],
            ['file:foo', 'file:bar', false],
            ['file:foo', 'file://foo', false],
        ];
    }

    public function testIsEquivalentWithRemoveDuplicateSlashes(): void
    {
        $uri1 = new Uri('http://example.org//foo');
        $uri2 = new Uri('http://example.org/foo');

        self::assertFalse(UriNormalizer::isEquivalent($uri1, $uri2));
        self::assertTrue(UriNormalizer::isEquivalent($uri1, $uri2, UriNormalizer::PRESERVING_NORMALIZATIONS | UriNormalizer::REMOVE_DUPLICATE_SLASHES));
    }
}
