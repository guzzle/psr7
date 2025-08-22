<?php

declare(strict_types=1);

namespace GuzzleHttp\Tests\Psr7;

use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriResolver;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\UriInterface;

/**
 * @covers \GuzzleHttp\Psr7\UriResolver
 */
class UriResolverTest extends TestCase
{
    private const RFC3986_BASE = 'http://a/b/c/d;p?q';

    /**
     * @dataProvider getResolveTestCases
     */
    public function testResolveUri(string $base, string $rel, string $expectedTarget): void
    {
        $baseUri = new Uri($base);
        $targetUri = UriResolver::resolve($baseUri, new Uri($rel));

        self::assertInstanceOf(UriInterface::class, $targetUri);
        self::assertSame($expectedTarget, (string) $targetUri);
        // This ensures there are no test cases that only work in the resolve() direction but not the
        // opposite via relativize(). This can happen when both base and rel URI are relative-path
        // references resulting in another relative-path URI.
        self::assertSame($expectedTarget, (string) UriResolver::resolve($baseUri, $targetUri));
    }

    /**
     * @dataProvider getResolveTestCases
     */
    public function testRelativizeUri(string $base, string $expectedRelativeReference, string $target): void
    {
        $baseUri = new Uri($base);
        $relativeUri = UriResolver::relativize($baseUri, new Uri($target));

        self::assertInstanceOf(UriInterface::class, $relativeUri);
        // There are test-cases with too many dot-segments and relative references that are equal like "." == "./".
        // So apart from the same-as condition, this alternative success condition is necessary.
        self::assertTrue(
            $expectedRelativeReference === (string) $relativeUri
            || $target === (string) UriResolver::resolve($baseUri, $relativeUri),
            sprintf(
                '"%s" is not the correct relative reference as it does not resolve to the target URI from the base URI',
                (string) $relativeUri
            )
        );
    }

    /**
     * @dataProvider getRelativizeTestCases
     */
    public function testRelativizeUriWithUniqueTests(string $base, string $target, string $expectedRelativeReference): void
    {
        $baseUri = new Uri($base);
        $targetUri = new Uri($target);
        $relativeUri = UriResolver::relativize($baseUri, $targetUri);

        self::assertInstanceOf(UriInterface::class, $relativeUri);
        self::assertSame($expectedRelativeReference, (string) $relativeUri);

        self::assertSame((string) UriResolver::resolve($baseUri, $targetUri), (string) UriResolver::resolve($baseUri, $relativeUri));
    }

    public static function getResolveTestCases(): iterable
    {
        return [
            [self::RFC3986_BASE, 'g:h',           'g:h'],
            [self::RFC3986_BASE, 'g',             'http://a/b/c/g'],
            [self::RFC3986_BASE, './g',           'http://a/b/c/g'],
            [self::RFC3986_BASE, 'g/',            'http://a/b/c/g/'],
            [self::RFC3986_BASE, '/g',            'http://a/g'],
            [self::RFC3986_BASE, '//g',           'http://g'],
            [self::RFC3986_BASE, '?y',            'http://a/b/c/d;p?y'],
            [self::RFC3986_BASE, 'g?y',           'http://a/b/c/g?y'],
            [self::RFC3986_BASE, '#s',            'http://a/b/c/d;p?q#s'],
            [self::RFC3986_BASE, 'g#s',           'http://a/b/c/g#s'],
            [self::RFC3986_BASE, 'g?y#s',         'http://a/b/c/g?y#s'],
            [self::RFC3986_BASE, ';x',            'http://a/b/c/;x'],
            [self::RFC3986_BASE, 'g;x',           'http://a/b/c/g;x'],
            [self::RFC3986_BASE, 'g;x?y#s',       'http://a/b/c/g;x?y#s'],
            [self::RFC3986_BASE, '',              self::RFC3986_BASE],
            [self::RFC3986_BASE, '.',             'http://a/b/c/'],
            [self::RFC3986_BASE, './',            'http://a/b/c/'],
            [self::RFC3986_BASE, '..',            'http://a/b/'],
            [self::RFC3986_BASE, '../',           'http://a/b/'],
            [self::RFC3986_BASE, '../g',          'http://a/b/g'],
            [self::RFC3986_BASE, '../..',         'http://a/'],
            [self::RFC3986_BASE, '../../',        'http://a/'],
            [self::RFC3986_BASE, '../../g',       'http://a/g'],
            [self::RFC3986_BASE, '../../../g',    'http://a/g'],
            [self::RFC3986_BASE, '../../../../g', 'http://a/g'],
            [self::RFC3986_BASE, '/./g',          'http://a/g'],
            [self::RFC3986_BASE, '/../g',         'http://a/g'],
            [self::RFC3986_BASE, 'g.',            'http://a/b/c/g.'],
            [self::RFC3986_BASE, '.g',            'http://a/b/c/.g'],
            [self::RFC3986_BASE, 'g..',           'http://a/b/c/g..'],
            [self::RFC3986_BASE, '..g',           'http://a/b/c/..g'],
            [self::RFC3986_BASE, './../g',        'http://a/b/g'],
            [self::RFC3986_BASE, 'foo////g',      'http://a/b/c/foo////g'],
            [self::RFC3986_BASE, './g/.',         'http://a/b/c/g/'],
            [self::RFC3986_BASE, 'g/./h',         'http://a/b/c/g/h'],
            [self::RFC3986_BASE, 'g/../h',        'http://a/b/c/h'],
            [self::RFC3986_BASE, 'g;x=1/./y',     'http://a/b/c/g;x=1/y'],
            [self::RFC3986_BASE, 'g;x=1/../y',    'http://a/b/c/y'],
            // dot-segments in the query or fragment
            [self::RFC3986_BASE, 'g?y/./x',       'http://a/b/c/g?y/./x'],
            [self::RFC3986_BASE, 'g?y/../x',      'http://a/b/c/g?y/../x'],
            [self::RFC3986_BASE, 'g#s/./x',       'http://a/b/c/g#s/./x'],
            [self::RFC3986_BASE, 'g#s/../x',      'http://a/b/c/g#s/../x'],
            [self::RFC3986_BASE, 'g#s/../x',      'http://a/b/c/g#s/../x'],
            [self::RFC3986_BASE, '?y#s',          'http://a/b/c/d;p?y#s'],
            // base with fragment
            ['http://a/b/c?q#s', '?y',            'http://a/b/c?y'],
            // base with user info
            ['http://u@a/b/c/d;p?q', '.',         'http://u@a/b/c/'],
            ['http://u:p@a/b/c/d;p?q', '.',       'http://u:p@a/b/c/'],
            // path ending with slash or no slash at all
            ['http://a/b/c/d/',  'e',             'http://a/b/c/d/e'],
            ['urn:no-slash',     'e',             'urn:e'],
            // path ending without slash and multi-segment relative part
            ['http://a/b/c',     'd/e',           'http://a/b/d/e'],
            // falsey relative parts
            [self::RFC3986_BASE, '//0',           'http://0'],
            [self::RFC3986_BASE, '0',             'http://a/b/c/0'],
            [self::RFC3986_BASE, '?0',            'http://a/b/c/d;p?0'],
            [self::RFC3986_BASE, '#0',            'http://a/b/c/d;p?q#0'],
            // absolute path base URI
            ['/a/b/',            '',              '/a/b/'],
            ['/a/b',             '',              '/a/b'],
            ['/',                'a',             '/a'],
            ['/',                'a/b',           '/a/b'],
            ['/a/b',             'g',             '/a/g'],
            ['/a/b/c',           './',            '/a/b/'],
            ['/a/b/',            '../',           '/a/'],
            ['/a/b/c',           '../',           '/a/'],
            ['/a/b/',            '../../x/y/z/',  '/x/y/z/'],
            ['/a/b/c/d/e',       '../../../c/d',  '/a/c/d'],
            ['/a/b/c//',         '../',           '/a/b/c/'],
            ['/a/b/c/',          './/',           '/a/b/c//'],
            ['/a/b/c',           '../../../../a', '/a'],
            ['/a/b/c',           '../../../..',   '/'],
            // not actually a dot-segment
            ['/a/b/c',           '..a/b..',           '/a/b/..a/b..'],
            // '' cannot be used as relative reference as it would inherit the base query component
            ['/a/b?q',           'b',             '/a/b'],
            ['/a/b/?q',          './',            '/a/b/'],
            // path with colon: "with:colon" would be the wrong relative reference
            ['/a/',              './with:colon',  '/a/with:colon'],
            ['/a/',              'b/with:colon',  '/a/b/with:colon'],
            ['/a/',              './:b/',         '/a/:b/'],
            // relative path references
            ['a',               'a/b',            'a/b'],
            ['',                 '',              ''],
            ['',                 '..',            ''],
            ['/',                '..',            '/'],
            ['urn:a/b',          '..//a/b',       'urn:/a/b'],
            // network path references
            // empty base path and relative-path reference
            ['//example.com',    'a',             '//example.com/a'],
            // path starting with two slashes
            ['//example.com//two-slashes', './',  '//example.com//'],
            ['//example.com',    './/',           '//example.com//'],
            ['//example.com/',   './/',           '//example.com//'],
            // base URI has less components than relative URI
            ['/',                '//a/b?q#h',     '//a/b?q#h'],
            ['/',                'urn:/',         'urn:/'],
        ];
    }

    /**
     * Some additional tests to getResolveTestCases() that only make sense for relativize.
     */
    public static function getRelativizeTestCases(): iterable
    {
        return [
            // targets that are relative-path references are returned as-is
            ['a/b',             'b/c',          'b/c'],
            ['a/b/c',           '../b/c',       '../b/c'],
            ['a',               '',             ''],
            ['a',               './',           './'],
            ['a',               'a/..',         'a/..'],
            ['/a/b/?q',         '?q#h',         '?q#h'],
            ['/a/b/?q',         '#h',           '#h'],
            ['/a/b/?q',         'c#h',          'c#h'],
            // If the base URI has a query but the target has none, we cannot return an empty path reference as it would
            // inherit the base query component when resolving.
            ['/a/b/?q',         '/a/b/#h',      './#h'],
            ['/',               '/#h',          '#h'],
            ['/',               '/',            ''],
            ['http://a',        'http://a/',    './'],
            ['urn:a/b?q',       'urn:x/y?q',    '../x/y?q'],
            ['urn:',            'urn:/',        './/'],
            ['urn:a/b?q',       'urn:',         '../'],
            // target URI has less components than base URI
            ['http://a/b/',     '//a/b/c',      'c'],
            ['http://a/b/',     '/b/c',         'c'],
            ['http://a/b/',     '/x/y',         '../x/y'],
            ['http://a/b/',     '/',            '../'],
            // absolute target URI without authority but base URI has one
            ['urn://a/b/',      'urn:/b/',      'urn:/b/'],
        ];
    }
}
