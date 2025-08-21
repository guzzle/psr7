<?php

declare(strict_types=1);

namespace GuzzleHttp\Tests\Psr7;

use GuzzleHttp\Psr7;
use PHPUnit\Framework\TestCase;

class HeaderTest extends TestCase
{
    public static function parseParamsProvider(): array
    {
        $res1 = [
            [
                '<http:/.../front.jpeg>',
                'rel' => 'front',
                'type' => 'image/jpeg',
            ],
            [
                '<http://.../back.jpeg>',
                'rel' => 'back',
                'type' => 'image/jpeg',
            ],
        ];

        return [
            [
                '<http:/.../front.jpeg>; rel="front"; type="image/jpeg", <http://.../back.jpeg>; rel=back; type="image/jpeg"',
                $res1,
            ],
            [
                '<http:/.../front.jpeg>; rel="front"; type="image/jpeg",<http://.../back.jpeg>; rel=back; type="image/jpeg"',
                $res1,
            ],
            [
                'foo="baz"; bar=123, boo, test="123", foobar="foo;bar"',
                [
                    ['foo' => 'baz', 'bar' => '123'],
                    ['boo'],
                    ['test' => '123'],
                    ['foobar' => 'foo;bar'],
                ],
            ],
            [
                '<http://.../side.jpeg?test=1>; rel="side"; type="image/jpeg",<http://.../side.jpeg?test=2>; rel=side; type="image/jpeg"',
                [
                    ['<http://.../side.jpeg?test=1>', 'rel' => 'side', 'type' => 'image/jpeg'],
                    ['<http://.../side.jpeg?test=2>', 'rel' => 'side', 'type' => 'image/jpeg'],
                ],
            ],
            [
                '',
                [],
            ],
        ];
    }

    /**
     * @dataProvider parseParamsProvider
     */
    public function testParseParams($header, $result): void
    {
        self::assertSame($result, Psr7\Header::parse($header));
    }

    public static function normalizeProvider(): array
    {
        return [
            [
                '',
                [],
            ],
            [
                ['a, b', 'c', 'd, e'],
                ['a', 'b', 'c', 'd', 'e'],
            ],
            // Example 'accept-encoding'
            [
                'gzip, br',
                ['gzip', 'br'],
            ],
            // https://httpwg.org/specs/rfc7231.html#rfc.section.5.3.2
            [
                'text/plain; q=0.5, text/html, text/x-dvi; q=0.8, text/x-c',
                ['text/plain; q=0.5', 'text/html', 'text/x-dvi; q=0.8', 'text/x-c'],
            ],
            // Example 'If-None-Match' with comma within an ETag
            [
                '"foo", "foo,bar", "bar"',
                ['"foo"', '"foo,bar"', '"bar"'],
            ],
            // https://httpwg.org/specs/rfc7234.html#cache.control.extensions
            [
                'private, community="UCI"',
                ['private', 'community="UCI"'],
            ],
            // The Cache-Control example with a comma within a community
            [
                'private, community="Guzzle,Psr7"',
                ['private', 'community="Guzzle,Psr7"'],
            ],
            // The Cache-Control example with an escaped space (quoted-pair) within a community
            [
                'private, community="Guzzle\\ Psr7"',
                ['private', 'community="Guzzle\\ Psr7"'],
            ],
            // The Cache-Control example with an escaped quote (quoted-pair) within a community
            [
                'private, community="Guzzle\\"Psr7"',
                ['private', 'community="Guzzle\\"Psr7"'],
            ],
            // The Cache-Control example with an escaped quote (quoted-pair) and a comma within a community
            [
                'private, community="Guzzle\\",Psr7"',
                ['private', 'community="Guzzle\\",Psr7"'],
            ],
            // The Cache-Control example with an escaped backslash (quoted-pair) within a community
            [
                'private, community="Guzzle\\\\Psr7"',
                ['private', 'community="Guzzle\\\\Psr7"'],
            ],
            // The Cache-Control example with an escaped backslash (quoted-pair) within a community
            [
                'private, community="Guzzle\\\\", Psr7',
                ['private', 'community="Guzzle\\\\"', 'Psr7'],
            ],
            // https://httpwg.org/specs/rfc7230.html#rfc.section.7
            [
                'foo ,bar,',
                ['foo', 'bar'],
            ],
            // https://httpwg.org/specs/rfc7230.html#rfc.section.7
            [
                'foo , ,bar,charlie   ',
                ['foo', 'bar', 'charlie'],
            ],
            [
                "<https://example.gitlab.com>; rel=\"first\",\n<https://example.gitlab.com>; rel=\"next\",\n<https://example.gitlab.com>; rel=\"prev\",\n<https://example.gitlab.com>; rel=\"last\",",
                ['<https://example.gitlab.com>; rel="first"', '<https://example.gitlab.com>; rel="next"', '<https://example.gitlab.com>; rel="prev"', '<https://example.gitlab.com>; rel="last"'],
            ],
        ];
    }

    /**
     * @dataProvider normalizeProvider
     */
    public function testNormalize($header, $result): void
    {
        self::assertSame($result, Psr7\Header::normalize([$header]));
        self::assertSame($result, Psr7\Header::normalize($header));
    }

    /**
     * @dataProvider normalizeProvider
     */
    public function testSplitList($header, $result): void
    {
        self::assertSame($result, Psr7\Header::splitList($header));
    }

    public function testSplitListRejectsNestedArrays(): void
    {
        $this->expectException(\TypeError::class);

        Psr7\Header::splitList([['foo']]);
    }

    public function testSplitListArrayContainingNonStrings(): void
    {
        $this->expectException(\TypeError::class);

        Psr7\Header::splitList(['foo', 'bar', 1, false]);
    }

    public function testSplitListRejectsNonStrings(): void
    {
        $this->expectException(\TypeError::class);

        Psr7\Header::splitList(false);
    }
}
