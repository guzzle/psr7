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
            ['custom://example.com/', 'custom://example.com:80/', true],
            ['custom://example.com/', 'custom://example.com/other', false],
            ['ftp://example.com/', 'ftp://example.com:80/', true],
            ['ws://example.com/', 'ws://example.com:80/', true],
            ['wss://example.com/', 'wss://example.com:443/', true],
        ];
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
}
