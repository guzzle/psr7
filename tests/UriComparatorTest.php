<?php

declare(strict_types=1);

namespace GuzzleHttp\Tests\Psr7;

use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriComparator;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\UriInterface;

/**
 * @covers \GuzzleHttp\Psr7\UriComparator
 */
class UriComparatorTest extends TestCase
{
    /**
     * @dataProvider getCrossOriginExamples
     */
    public function testIsCrossOrigin(string $original, string $modified, bool $expected): void
    {
        self::assertSame($expected, UriComparator::isCrossOrigin(new Uri($original), new Uri($modified)));
    }

    public static function getCrossOriginExamples(): array
    {
        return [
            ['http://example.com/123', 'http://example.com/', false],
            ['http://example.com/123', 'http://example.com:80/', false],
            ['http://example.com:80/123', 'http://example.com/', false],
            ['http://example.com:80/123', 'http://example.com:80/', false],
            ['http://example.com/123', 'https://example.com/', true],
            ['http://example.com/123', 'http://www.example.com/', true],
            ['http://example.com/123', 'http://example.com:81/', true],
            ['http://example.com:80/123', 'http://example.com:81/', true],
            ['https://example.com/123', 'https://example.com/', false],
            ['https://example.com/123', 'https://example.com:443/', false],
            ['https://example.com:443/123', 'https://example.com/', false],
            ['https://example.com:443/123', 'https://example.com:443/', false],
            ['https://example.com/123', 'http://example.com/', true],
            ['https://example.com/123', 'https://www.example.com/', true],
            ['https://example.com/123', 'https://example.com:444/', true],
            ['https://example.com:443/123', 'https://example.com:444/', true],
            ['http://[::1]/123', 'http://[0:0:0:0:0:0:0:1]/', false],
            ['http://[::1]/123', 'http://[::2]/', true],
            ['custom://example.com/', 'custom://example.com:80/', true],
            ['custom://example.com/', 'custom://example.com/other', false],
            ['ftp://example.com/', 'ftp://example.com:80/', true],
            ['ws://example.com/', 'ws://example.com:80/', false],
            ['wss://example.com/', 'wss://example.com:443/', false],
        ];
    }

    public function testForeignNonCanonicalIpv6HostIsSameOrigin(): void
    {
        $foreign = $this->createMock(UriInterface::class);
        $foreign->method('getHost')->willReturn('[0:0:0:0:0:0:0:1]');
        $foreign->method('getScheme')->willReturn('http');
        $foreign->method('getPort')->willReturn(null);

        self::assertFalse(UriComparator::isCrossOrigin($foreign, new Uri('http://[::1]/')));
        self::assertFalse(UriComparator::isCrossOrigin(new Uri('http://[::1]/'), $foreign));
    }

    public function testForeignUppercaseIpv6HostIsSameOrigin(): void
    {
        $foreign = $this->createMock(UriInterface::class);
        $foreign->method('getHost')->willReturn('[FE80::1]');
        $foreign->method('getScheme')->willReturn('http');
        $foreign->method('getPort')->willReturn(null);

        self::assertFalse(UriComparator::isCrossOrigin($foreign, new Uri('http://[fe80::1]/')));
    }

    public function testForeignIpv6HostWithZoneIdComparesTextually(): void
    {
        $foreign = $this->createMock(UriInterface::class);
        $foreign->method('getHost')->willReturn('[fe80::1%25eth0]');
        $foreign->method('getScheme')->willReturn('http');
        $foreign->method('getPort')->willReturn(null);

        self::assertTrue(UriComparator::isCrossOrigin($foreign, new Uri('http://[fe80::1]/')));
    }

    public function testForeignIpv6HostWithZeroPaddedDottedOctetsComparesTextually(): void
    {
        $foreign = $this->createMock(UriInterface::class);
        $foreign->method('getHost')->willReturn('[::ffff:192.168.001.001]');
        $foreign->method('getScheme')->willReturn('http');
        $foreign->method('getPort')->willReturn(null);

        self::assertTrue(UriComparator::isCrossOrigin($foreign, new Uri('http://[::ffff:192.168.1.1]/')));
        self::assertTrue(UriComparator::isCrossOrigin(new Uri('http://[::ffff:192.168.1.1]/'), $foreign));
    }

    public function testForeignIpvFutureHostsCompareCaselessly(): void
    {
        $original = $this->createMock(UriInterface::class);
        $original->method('getHost')->willReturn('[v1.ab]');
        $original->method('getScheme')->willReturn('http');
        $original->method('getPort')->willReturn(null);

        $modified = $this->createMock(UriInterface::class);
        $modified->method('getHost')->willReturn('[V1.AB]');
        $modified->method('getScheme')->willReturn('http');
        $modified->method('getPort')->willReturn(null);

        self::assertFalse(UriComparator::isCrossOrigin($original, $modified));
    }

    public function testNonHttpSchemeMissingPortDoesNotUseSchemeDefault(): void
    {
        $original = $this->createMock(UriInterface::class);
        $original->method('getHost')->willReturn('example.com');
        $original->method('getScheme')->willReturn('ftp');
        $original->method('getPort')->willReturn(null);

        $modified = $this->createMock(UriInterface::class);
        $modified->method('getHost')->willReturn('example.com');
        $modified->method('getScheme')->willReturn('ftp');
        $modified->method('getPort')->willReturn(21);

        self::assertTrue(UriComparator::isCrossOrigin($original, $modified));
    }

    public function testWsSchemeMissingPortUsesSchemeDefault(): void
    {
        $original = $this->createMock(UriInterface::class);
        $original->method('getHost')->willReturn('example.com');
        $original->method('getScheme')->willReturn('ws');
        $original->method('getPort')->willReturn(null);

        $modified = $this->createMock(UriInterface::class);
        $modified->method('getHost')->willReturn('example.com');
        $modified->method('getScheme')->willReturn('ws');
        $modified->method('getPort')->willReturn(80);

        self::assertFalse(UriComparator::isCrossOrigin($original, $modified));
    }

    public function testWssSchemeMissingPortUsesSchemeDefault(): void
    {
        $original = $this->createMock(UriInterface::class);
        $original->method('getHost')->willReturn('example.com');
        $original->method('getScheme')->willReturn('wss');
        $original->method('getPort')->willReturn(null);

        $modified = $this->createMock(UriInterface::class);
        $modified->method('getHost')->willReturn('example.com');
        $modified->method('getScheme')->willReturn('wss');
        $modified->method('getPort')->willReturn(443);

        self::assertFalse(UriComparator::isCrossOrigin($original, $modified));
    }
}
