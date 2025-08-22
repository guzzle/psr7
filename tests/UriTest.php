<?php

declare(strict_types=1);

namespace GuzzleHttp\Tests\Psr7;

use GuzzleHttp\Psr7\Exception\MalformedUriException;
use GuzzleHttp\Psr7\Uri;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\UriInterface;

/**
 * @covers \GuzzleHttp\Psr7\Uri
 */
class UriTest extends TestCase
{
    public function testParsesProvidedUri(): void
    {
        $uri = new Uri('https://user:pass@example.com:8080/path/123?q=abc#test');

        self::assertSame('https', $uri->getScheme());
        self::assertSame('user:pass@example.com:8080', $uri->getAuthority());
        self::assertSame('user:pass', $uri->getUserInfo());
        self::assertSame('example.com', $uri->getHost());
        self::assertSame(8080, $uri->getPort());
        self::assertSame('/path/123', $uri->getPath());
        self::assertSame('q=abc', $uri->getQuery());
        self::assertSame('test', $uri->getFragment());
        self::assertSame('https://user:pass@example.com:8080/path/123?q=abc#test', (string) $uri);
    }

    public function testCanTransformAndRetrievePartsIndividually(): void
    {
        $uri = (new Uri())
            ->withScheme('https')
            ->withUserInfo('user', 'pass')
            ->withHost('example.com')
            ->withPort(8080)
            ->withPath('/path/123')
            ->withQuery('q=abc')
            ->withFragment('test');

        self::assertSame('https', $uri->getScheme());
        self::assertSame('user:pass@example.com:8080', $uri->getAuthority());
        self::assertSame('user:pass', $uri->getUserInfo());
        self::assertSame('example.com', $uri->getHost());
        self::assertSame(8080, $uri->getPort());
        self::assertSame('/path/123', $uri->getPath());
        self::assertSame('q=abc', $uri->getQuery());
        self::assertSame('test', $uri->getFragment());
        self::assertSame('https://user:pass@example.com:8080/path/123?q=abc#test', (string) $uri);
    }

    /**
     * @dataProvider getValidUris
     */
    public function testValidUrisStayValid(string $input): void
    {
        $uri = new Uri($input);

        self::assertSame($input, (string) $uri);
    }

    /**
     * @dataProvider getValidUris
     */
    public function testFromParts(string $input): void
    {
        $uri = Uri::fromParts(parse_url($input));

        self::assertSame($input, (string) $uri);
    }

    public static function getValidUris(): iterable
    {
        return [
            ['urn:path-rootless'],
            ['urn:path:with:colon'],
            ['urn:/path-absolute'],
            ['urn:/'],
            // only scheme with empty path
            ['urn:'],
            // only path
            ['/'],
            ['relative/'],
            ['0'],
            // same document reference
            [''],
            // network path without scheme
            ['//example.org'],
            ['//example.org/'],
            ['//example.org?q#h'],
            // only query
            ['?q'],
            ['?q=abc&foo=bar'],
            // only fragment
            ['#fragment'],
            // dot segments are not removed automatically
            ['./foo/../bar'],
        ];
    }

    /**
     * @dataProvider getInvalidUris
     */
    public function testInvalidUrisThrowException(string $invalidUri): void
    {
        $this->expectException(MalformedUriException::class);
        new Uri($invalidUri);
    }

    public static function getInvalidUris(): iterable
    {
        return [
            // parse_url() requires the host component which makes sense for http(s)
            // but not when the scheme is not known or different. So '//' or '///' is
            // currently invalid as well but should not according to RFC 3986.
            ['http://'],
            ['urn://host:with:colon'], // host cannot contain ":"
        ];
    }

    public function testPortMustBeValid(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid port: 100000. Must be between 0 and 65535');
        (new Uri())->withPort(100000);
    }

    public function testWithPortCannotBeNegative(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid port: -1. Must be between 0 and 65535');
        (new Uri())->withPort(-1);
    }

    public function testParseUriPortCannotBeNegative(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unable to parse URI');
        new Uri('//example.com:-1');
    }

    public function testSchemeMustHaveCorrectType(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new Uri())->withScheme([]);
    }

    public function testHostMustHaveCorrectType(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new Uri())->withHost([]);
    }

    public function testPathMustHaveCorrectType(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new Uri())->withPath([]);
    }

    public function testQueryMustHaveCorrectType(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new Uri())->withQuery([]);
    }

    public function testFragmentMustHaveCorrectType(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new Uri())->withFragment([]);
    }

    public function testCanParseFalseyUriParts(): void
    {
        $uri = new Uri('0://0:0@0/0?0#0');

        self::assertSame('0', $uri->getScheme());
        self::assertSame('0:0@0', $uri->getAuthority());
        self::assertSame('0:0', $uri->getUserInfo());
        self::assertSame('0', $uri->getHost());
        self::assertSame('/0', $uri->getPath());
        self::assertSame('0', $uri->getQuery());
        self::assertSame('0', $uri->getFragment());
        self::assertSame('0://0:0@0/0?0#0', (string) $uri);
    }

    public function testCanConstructFalseyUriParts(): void
    {
        $uri = (new Uri())
            ->withScheme('0')
            ->withUserInfo('0', '0')
            ->withHost('0')
            ->withPath('/0')
            ->withQuery('0')
            ->withFragment('0');

        self::assertSame('0', $uri->getScheme());
        self::assertSame('0:0@0', $uri->getAuthority());
        self::assertSame('0:0', $uri->getUserInfo());
        self::assertSame('0', $uri->getHost());
        self::assertSame('/0', $uri->getPath());
        self::assertSame('0', $uri->getQuery());
        self::assertSame('0', $uri->getFragment());
        self::assertSame('0://0:0@0/0?0#0', (string) $uri);
    }

    /**
     * @dataProvider getPortTestCases
     */
    public function testIsDefaultPort(string $scheme, ?int $port, bool $isDefaultPort): void
    {
        $uri = $this->createMock(UriInterface::class);
        $uri->expects(self::any())->method('getScheme')->willReturn($scheme);
        $uri->expects(self::any())->method('getPort')->willReturn($port);

        self::assertSame($isDefaultPort, Uri::isDefaultPort($uri));
    }

    public static function getPortTestCases(): iterable
    {
        return [
            ['http', null, true],
            ['http', 80, true],
            ['http', 8080, false],
            ['https', null, true],
            ['https', 443, true],
            ['https', 444, false],
            ['ftp', 21, true],
            ['gopher', 70, true],
            ['nntp', 119, true],
            ['news', 119, true],
            ['telnet', 23, true],
            ['tn3270', 23, true],
            ['imap', 143, true],
            ['pop', 110, true],
            ['ldap', 389, true],
        ];
    }

    public function testIsAbsolute(): void
    {
        self::assertTrue(Uri::isAbsolute(new Uri('http://example.org')));
        self::assertFalse(Uri::isAbsolute(new Uri('//example.org')));
        self::assertFalse(Uri::isAbsolute(new Uri('/abs-path')));
        self::assertFalse(Uri::isAbsolute(new Uri('rel-path')));
    }

    public function testIsNetworkPathReference(): void
    {
        self::assertFalse(Uri::isNetworkPathReference(new Uri('http://example.org')));
        self::assertTrue(Uri::isNetworkPathReference(new Uri('//example.org')));
        self::assertFalse(Uri::isNetworkPathReference(new Uri('/abs-path')));
        self::assertFalse(Uri::isNetworkPathReference(new Uri('rel-path')));
    }

    public function testIsAbsolutePathReference(): void
    {
        self::assertFalse(Uri::isAbsolutePathReference(new Uri('http://example.org')));
        self::assertFalse(Uri::isAbsolutePathReference(new Uri('//example.org')));
        self::assertTrue(Uri::isAbsolutePathReference(new Uri('/abs-path')));
        self::assertTrue(Uri::isAbsolutePathReference(new Uri('/')));
        self::assertFalse(Uri::isAbsolutePathReference(new Uri('rel-path')));
    }

    public function testIsRelativePathReference(): void
    {
        self::assertFalse(Uri::isRelativePathReference(new Uri('http://example.org')));
        self::assertFalse(Uri::isRelativePathReference(new Uri('//example.org')));
        self::assertFalse(Uri::isRelativePathReference(new Uri('/abs-path')));
        self::assertTrue(Uri::isRelativePathReference(new Uri('rel-path')));
        self::assertTrue(Uri::isRelativePathReference(new Uri('')));
    }

    public function testIsSameDocumentReference(): void
    {
        self::assertFalse(Uri::isSameDocumentReference(new Uri('http://example.org')));
        self::assertFalse(Uri::isSameDocumentReference(new Uri('//example.org')));
        self::assertFalse(Uri::isSameDocumentReference(new Uri('/abs-path')));
        self::assertFalse(Uri::isSameDocumentReference(new Uri('rel-path')));
        self::assertFalse(Uri::isSameDocumentReference(new Uri('?query')));
        self::assertTrue(Uri::isSameDocumentReference(new Uri('')));
        self::assertTrue(Uri::isSameDocumentReference(new Uri('#fragment')));

        $baseUri = new Uri('http://example.org/path?foo=bar');

        self::assertTrue(Uri::isSameDocumentReference(new Uri('#fragment'), $baseUri));
        self::assertTrue(Uri::isSameDocumentReference(new Uri('?foo=bar#fragment'), $baseUri));
        self::assertTrue(Uri::isSameDocumentReference(new Uri('/path?foo=bar#fragment'), $baseUri));
        self::assertTrue(Uri::isSameDocumentReference(new Uri('path?foo=bar#fragment'), $baseUri));
        self::assertTrue(Uri::isSameDocumentReference(new Uri('//example.org/path?foo=bar#fragment'), $baseUri));
        self::assertTrue(Uri::isSameDocumentReference(new Uri('http://example.org/path?foo=bar#fragment'), $baseUri));

        self::assertFalse(Uri::isSameDocumentReference(new Uri('https://example.org/path?foo=bar'), $baseUri));
        self::assertFalse(Uri::isSameDocumentReference(new Uri('http://example.com/path?foo=bar'), $baseUri));
        self::assertFalse(Uri::isSameDocumentReference(new Uri('http://example.org/'), $baseUri));
        self::assertFalse(Uri::isSameDocumentReference(new Uri('http://example.org'), $baseUri));

        self::assertFalse(Uri::isSameDocumentReference(new Uri('urn:/path'), new Uri('urn://example.com/path')));
    }

    public function testAddAndRemoveQueryValues(): void
    {
        $uri = new Uri();
        $uri = Uri::withQueryValue($uri, 'a', 'b');
        $uri = Uri::withQueryValue($uri, 'c', 'd');
        $uri = Uri::withQueryValue($uri, 'e', null);
        self::assertSame('a=b&c=d&e', $uri->getQuery());

        $uri = Uri::withoutQueryValue($uri, 'c');
        self::assertSame('a=b&e', $uri->getQuery());
        $uri = Uri::withoutQueryValue($uri, 'e');
        self::assertSame('a=b', $uri->getQuery());
        $uri = Uri::withoutQueryValue($uri, 'a');
        self::assertSame('', $uri->getQuery());
    }

    public function testScalarQueryValues(): void
    {
        $uri = new Uri();
        $uri = Uri::withQueryValues($uri, [
            2 => 2,
            1 => true,
            'false' => false,
            'float' => 3.1,
        ]);

        self::assertSame('2=2&1=1&false=&float=3.1', $uri->getQuery());
    }

    public function testWithQueryValues(): void
    {
        $uri = new Uri();
        $uri = Uri::withQueryValues($uri, [
            'key1' => 'value1',
            'key2' => 'value2',
        ]);

        self::assertSame('key1=value1&key2=value2', $uri->getQuery());
    }

    public function testWithQueryValuesReplacesSameKeys(): void
    {
        $uri = new Uri();

        $uri = Uri::withQueryValues($uri, [
            'key1' => 'value1',
            'key2' => 'value2',
        ]);

        $uri = Uri::withQueryValues($uri, [
            'key2' => 'newvalue',
        ]);

        self::assertSame('key1=value1&key2=newvalue', $uri->getQuery());
    }

    public function testWithQueryValueReplacesSameKeys(): void
    {
        $uri = new Uri();
        $uri = Uri::withQueryValue($uri, 'a', 'b');
        $uri = Uri::withQueryValue($uri, 'c', 'd');
        $uri = Uri::withQueryValue($uri, 'a', 'e');
        self::assertSame('c=d&a=e', $uri->getQuery());
    }

    public function testWithoutQueryValueRemovesAllSameKeys(): void
    {
        $uri = (new Uri())->withQuery('a=b&c=d&a=e');
        $uri = Uri::withoutQueryValue($uri, 'a');
        self::assertSame('c=d', $uri->getQuery());
    }

    public function testRemoveNonExistingQueryValue(): void
    {
        $uri = new Uri();
        $uri = Uri::withQueryValue($uri, 'a', 'b');
        $uri = Uri::withoutQueryValue($uri, 'c');
        self::assertSame('a=b', $uri->getQuery());
    }

    public function testWithQueryValueHandlesEncoding(): void
    {
        $uri = new Uri();
        $uri = Uri::withQueryValue($uri, 'E=mc^2', 'ein&stein');
        self::assertSame('E%3Dmc%5E2=ein%26stein', $uri->getQuery(), 'Decoded key/value get encoded');

        $uri = new Uri();
        $uri = Uri::withQueryValue($uri, 'E%3Dmc%5e2', 'ein%26stein');
        self::assertSame('E%3Dmc%5e2=ein%26stein', $uri->getQuery(), 'Encoded key/value do not get double-encoded');
    }

    public function testWithoutQueryValueHandlesEncoding(): void
    {
        // It also tests that the case of the percent-encoding does not matter,
        // i.e. both lowercase "%3d" and uppercase "%5E" can be removed.
        $uri = (new Uri())->withQuery('E%3dmc%5E2=einstein&foo=bar');
        $uri = Uri::withoutQueryValue($uri, 'E=mc^2');
        self::assertSame('foo=bar', $uri->getQuery(), 'Handles key in decoded form');

        $uri = (new Uri())->withQuery('E%3dmc%5E2=einstein&foo=bar');
        $uri = Uri::withoutQueryValue($uri, 'E%3Dmc%5e2');
        self::assertSame('foo=bar', $uri->getQuery(), 'Handles key in encoded form');
    }

    public function testSchemeIsNormalizedToLowercase(): void
    {
        $uri = new Uri('HTTP://example.com');

        self::assertSame('http', $uri->getScheme());
        self::assertSame('http://example.com', (string) $uri);

        $uri = (new Uri('//example.com'))->withScheme('HTTP');

        self::assertSame('http', $uri->getScheme());
        self::assertSame('http://example.com', (string) $uri);
    }

    public function testHostIsNormalizedToLowercase(): void
    {
        $uri = new Uri('//eXaMpLe.CoM');

        self::assertSame('example.com', $uri->getHost());
        self::assertSame('//example.com', (string) $uri);

        $uri = (new Uri())->withHost('eXaMpLe.CoM');

        self::assertSame('example.com', $uri->getHost());
        self::assertSame('//example.com', (string) $uri);
    }

    public function testPortIsNullIfStandardPortForScheme(): void
    {
        // HTTPS standard port
        $uri = new Uri('https://example.com:443');
        self::assertNull($uri->getPort());
        self::assertSame('example.com', $uri->getAuthority());

        $uri = (new Uri('https://example.com'))->withPort(443);
        self::assertNull($uri->getPort());
        self::assertSame('example.com', $uri->getAuthority());

        // HTTP standard port
        $uri = new Uri('http://example.com:80');
        self::assertNull($uri->getPort());
        self::assertSame('example.com', $uri->getAuthority());

        $uri = (new Uri('http://example.com'))->withPort(80);
        self::assertNull($uri->getPort());
        self::assertSame('example.com', $uri->getAuthority());
    }

    public function testPortIsReturnedIfSchemeUnknown(): void
    {
        $uri = (new Uri('//example.com'))->withPort(80);

        self::assertSame(80, $uri->getPort());
        self::assertSame('example.com:80', $uri->getAuthority());
    }

    public function testStandardPortIsNullIfSchemeChanges(): void
    {
        $uri = new Uri('http://example.com:443');
        self::assertSame('http', $uri->getScheme());
        self::assertSame(443, $uri->getPort());

        $uri = $uri->withScheme('https');
        self::assertNull($uri->getPort());
    }

    public function testPortPassedAsStringIsCastedToInt(): void
    {
        $uri = (new Uri('//example.com'))->withPort('8080');

        self::assertSame(8080, $uri->getPort(), 'Port is returned as integer');
        self::assertSame('example.com:8080', $uri->getAuthority());
    }

    public function testPortCanBeRemoved(): void
    {
        $uri = (new Uri('http://example.com:8080'))->withPort(null);

        self::assertNull($uri->getPort());
        self::assertSame('http://example.com', (string) $uri);
    }

    /**
     * In RFC 8986 the host is optional and the authority can only
     * consist of the user info and port.
     */
    public function testAuthorityWithUserInfoOrPortButWithoutHost(): void
    {
        $uri = (new Uri())->withUserInfo('user', 'pass');

        self::assertSame('user:pass', $uri->getUserInfo());
        self::assertSame('user:pass@', $uri->getAuthority());

        $uri = $uri->withPort(8080);
        self::assertSame(8080, $uri->getPort());
        self::assertSame('user:pass@:8080', $uri->getAuthority());
        self::assertSame('//user:pass@:8080', (string) $uri);

        $uri = $uri->withUserInfo('');
        self::assertSame(':8080', $uri->getAuthority());
    }

    public function testHostInHttpUriDefaultsToLocalhost(): void
    {
        $uri = (new Uri())->withScheme('http');

        self::assertSame('localhost', $uri->getHost());
        self::assertSame('localhost', $uri->getAuthority());
        self::assertSame('http://localhost', (string) $uri);
    }

    public function testHostInHttpsUriDefaultsToLocalhost(): void
    {
        $uri = (new Uri())->withScheme('https');

        self::assertSame('localhost', $uri->getHost());
        self::assertSame('localhost', $uri->getAuthority());
        self::assertSame('https://localhost', (string) $uri);
    }

    public function testFileSchemeWithEmptyHostReconstruction(): void
    {
        $uri = new Uri('file:///tmp/filename.ext');

        self::assertSame('', $uri->getHost());
        self::assertSame('', $uri->getAuthority());
        self::assertSame('file:///tmp/filename.ext', (string) $uri);
    }

    public static function uriComponentsEncodingProvider(): iterable
    {
        $unreserved = 'a-zA-Z0-9.-_~!$&\'()*+,;=:@';

        return [
            // Percent encode spaces
            ['/pa th?q=va lue#frag ment', '/pa%20th', 'q=va%20lue', 'frag%20ment', '/pa%20th?q=va%20lue#frag%20ment'],
            // Percent encode multibyte
            ['/€?€#€', '/%E2%82%AC', '%E2%82%AC', '%E2%82%AC', '/%E2%82%AC?%E2%82%AC#%E2%82%AC'],
            // Don't encode something that's already encoded
            ['/pa%20th?q=va%20lue#frag%20ment', '/pa%20th', 'q=va%20lue', 'frag%20ment', '/pa%20th?q=va%20lue#frag%20ment'],
            // Percent encode invalid percent encodings
            ['/pa%2-th?q=va%2-lue#frag%2-ment', '/pa%252-th', 'q=va%252-lue', 'frag%252-ment', '/pa%252-th?q=va%252-lue#frag%252-ment'],
            // Don't encode path segments
            ['/pa/th//two?q=va/lue#frag/ment', '/pa/th//two', 'q=va/lue', 'frag/ment', '/pa/th//two?q=va/lue#frag/ment'],
            // Don't encode unreserved chars or sub-delimiters
            ["/$unreserved?$unreserved#$unreserved", "/$unreserved", $unreserved, $unreserved, "/$unreserved?$unreserved#$unreserved"],
            // Encoded unreserved chars are not decoded
            ['/p%61th?q=v%61lue#fr%61gment', '/p%61th', 'q=v%61lue', 'fr%61gment', '/p%61th?q=v%61lue#fr%61gment'],
        ];
    }

    /**
     * @dataProvider uriComponentsEncodingProvider
     */
    public function testUriComponentsGetEncodedProperly(string $input, string $path, string $query, string $fragment, string $output): void
    {
        $uri = new Uri($input);
        self::assertSame($path, $uri->getPath());
        self::assertSame($query, $uri->getQuery());
        self::assertSame($fragment, $uri->getFragment());
        self::assertSame($output, (string) $uri);
    }

    public function testWithPathEncodesProperly(): void
    {
        $uri = (new Uri())->withPath('/baz?#€/b%61r');
        // Query and fragment delimiters and multibyte chars are encoded.
        self::assertSame('/baz%3F%23%E2%82%AC/b%61r', $uri->getPath());
        self::assertSame('/baz%3F%23%E2%82%AC/b%61r', (string) $uri);
    }

    public function testWithQueryEncodesProperly(): void
    {
        $uri = (new Uri())->withQuery('?=#&€=/&b%61r');
        // A query starting with a "?" is valid and must not be magically removed. Otherwise it would be impossible to
        // construct such an URI. Also the "?" and "/" does not need to be encoded in the query.
        self::assertSame('?=%23&%E2%82%AC=/&b%61r', $uri->getQuery());
        self::assertSame('??=%23&%E2%82%AC=/&b%61r', (string) $uri);
    }

    public function testWithFragmentEncodesProperly(): void
    {
        $uri = (new Uri())->withFragment('#€?/b%61r');
        // A fragment starting with a "#" is valid and must not be magically removed. Otherwise it would be impossible to
        // construct such an URI. Also the "?" and "/" does not need to be encoded in the fragment.
        self::assertSame('%23%E2%82%AC?/b%61r', $uri->getFragment());
        self::assertSame('#%23%E2%82%AC?/b%61r', (string) $uri);
    }

    public function testAllowsForRelativeUri(): void
    {
        $uri = (new Uri())->withPath('foo');
        self::assertSame('foo', $uri->getPath());
        self::assertSame('foo', (string) $uri);
    }

    public function testRelativePathAndAuthority(): void
    {
        $uri = (new Uri())->withHost('example.com')->withPath('foo');
        self::assertSame('foo', $uri->getPath());
        self::assertSame('//example.com/foo', $uri->__toString());
    }

    public function testPathStartingWithTwoSlashesAndNoAuthorityIsInvalid(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The path of a URI without an authority must not start with two slashes "//"');
        // URI "//foo" would be interpreted as network reference and thus change the original path to the host
        (new Uri())->withPath('//foo');
    }

    public function testPathStartingWithTwoSlashes(): void
    {
        $uri = new Uri('http://example.org//path-not-host.com');
        self::assertSame('//path-not-host.com', $uri->getPath());

        $uri = $uri->withScheme('');
        self::assertSame('//example.org//path-not-host.com', (string) $uri); // This is still valid
        $this->expectException(\InvalidArgumentException::class);
        $uri->withHost(''); // Now it becomes invalid
    }

    public function testRelativeUriWithPathBeginngWithColonSegmentIsInvalid(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('A relative URI must not have a path beginning with a segment containing a colon');
        (new Uri())->withPath('mailto:foo');
    }

    public function testRelativeUriWithPathHavingColonSegment(): void
    {
        $uri = (new Uri('urn:/mailto:foo'))->withScheme('');
        self::assertSame('/mailto:foo', $uri->getPath());

        $this->expectException(\InvalidArgumentException::class);
        (new Uri('urn:mailto:foo'))->withScheme('');
    }

    public function testDefaultReturnValuesOfGetters(): void
    {
        $uri = new Uri();

        self::assertSame('', $uri->getScheme());
        self::assertSame('', $uri->getAuthority());
        self::assertSame('', $uri->getUserInfo());
        self::assertSame('', $uri->getHost());
        self::assertNull($uri->getPort());
        self::assertSame('', $uri->getPath());
        self::assertSame('', $uri->getQuery());
        self::assertSame('', $uri->getFragment());
    }

    public function testImmutability(): void
    {
        $uri = new Uri();

        self::assertNotSame($uri, $uri->withScheme('https'));
        self::assertNotSame($uri, $uri->withUserInfo('user', 'pass'));
        self::assertNotSame($uri, $uri->withHost('example.com'));
        self::assertNotSame($uri, $uri->withPort(8080));
        self::assertNotSame($uri, $uri->withPath('/path/123'));
        self::assertNotSame($uri, $uri->withQuery('q=abc'));
        self::assertNotSame($uri, $uri->withFragment('test'));
    }

    public function testExtendingClassesInstantiates(): void
    {
        // The non-standard port triggers a cascade of private methods which
        // should not use late static binding to access private static members.
        // If they do, this will fatal.
        self::assertInstanceOf(
            ExtendedUriTest::class,
            new ExtendedUriTest('http://h:9/')
        );
    }

    public function testSpecialCharsOfUserInfo(): void
    {
        // The `userInfo` must always be URL-encoded.
        $uri = (new Uri())->withUserInfo('foo@bar.com', 'pass#word');
        self::assertSame('foo%40bar.com:pass%23word', $uri->getUserInfo());

        // The `userInfo` can already be URL-encoded: it should not be encoded twice.
        $uri = (new Uri())->withUserInfo('foo%40bar.com', 'pass%23word');
        self::assertSame('foo%40bar.com:pass%23word', $uri->getUserInfo());
    }

    public function testInternationalizedDomainName(): void
    {
        $uri = new Uri('https://яндекс.рф');
        self::assertSame('яндекс.рф', $uri->getHost());

        $uri = new Uri('https://яндекAс.рф');
        self::assertSame('яндекaс.рф', $uri->getHost());
    }

    public function testIPv6Host(): void
    {
        $uri = new Uri('https://[2a00:f48:1008::212:183:10]');
        self::assertSame('[2a00:f48:1008::212:183:10]', $uri->getHost());

        $uri = new Uri('http://[2a00:f48:1008::212:183:10]:56?foo=bar');
        self::assertSame('[2a00:f48:1008::212:183:10]', $uri->getHost());
        self::assertSame(56, $uri->getPort());
        self::assertSame('foo=bar', $uri->getQuery());
    }

    public function testJsonSerializable(): void
    {
        $uri = new Uri('https://example.com');

        self::assertSame('{"uri":"https:\/\/example.com"}', \json_encode(['uri' => $uri]));
    }
}

class ExtendedUriTest extends Uri
{
}
