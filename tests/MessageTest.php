<?php

declare(strict_types=1);

namespace GuzzleHttp\Tests\Psr7;

use GuzzleHttp\Psr7;
use GuzzleHttp\Psr7\FnStream;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;

/**
 * @covers \GuzzleHttp\Psr7\Message
 * @covers \GuzzleHttp\Psr7\MessageParser
 */
class MessageTest extends TestCase
{
    public function testConvertsRequestsToStrings(): void
    {
        $request = new Psr7\Request('PUT', 'http://foo.com/hi?123', [
            'Baz' => 'bar',
            'Qux' => 'ipsum',
        ], 'hello', '1.0');
        self::assertSame(
            "PUT /hi?123 HTTP/1.0\r\nHost: foo.com\r\nBaz: bar\r\nQux: ipsum\r\n\r\nhello",
            Psr7\Message::toString($request)
        );
    }

    public function testConvertsRequestWithoutHostHeaderToStringWithUriPort(): void
    {
        $request = (new Psr7\Request('GET', 'http://foo.com:8124/hi'))->withoutHeader('Host');

        self::assertSame(
            "GET /hi HTTP/1.1\r\nHost: foo.com:8124\r\n\r\n",
            Psr7\Message::toString($request)
        );
    }

    public function testConvertsRequestWithoutHostHeaderToStringWithIpv6UriPort(): void
    {
        $request = (new Psr7\Request('GET', 'http://[::1]:8124/'))->withoutHeader('Host');

        self::assertSame(
            "GET / HTTP/1.1\r\nHost: [::1]:8124\r\n\r\n",
            Psr7\Message::toString($request)
        );
    }

    public function testConvertsRequestWithoutHostHeaderToStringWithoutUriUserInfo(): void
    {
        $request = (new Psr7\Request('GET', 'http://user:pass@foo.com:8124/hi'))->withoutHeader('Host');

        self::assertSame(
            "GET /hi HTTP/1.1\r\nHost: foo.com:8124\r\n\r\n",
            Psr7\Message::toString($request)
        );
    }

    public function testConvertsRequestWithHostHeaderToStringWithoutOverwritingHost(): void
    {
        $request = new Psr7\Request('GET', 'http://foo.com:8124/hi', ['Host' => 'custom.example']);

        self::assertSame(
            "GET /hi HTTP/1.1\r\nHost: custom.example\r\n\r\n",
            Psr7\Message::toString($request)
        );
    }

    public function testToStringRejectsCrlfHostSynthesizedFromUri(): void
    {
        $uri = new class('http://safe.example/') extends Psr7\Uri {
            public function getHost(): string
            {
                return "a\r\nX-Injected: yes";
            }
        };
        $request = (new Psr7\Request('GET', 'http://safe.example/', ['Host' => 'safe.example']))
            ->withUri($uri, true)
            ->withoutHeader('Host');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid host');

        Psr7\Message::toString($request);
    }

    public function testToStringRejectsDelimiterHostSynthesizedFromUri(): void
    {
        $uri = new class('http://safe.example/') extends Psr7\Uri {
            public function getHost(): string
            {
                return 'ex%2Fample.com';
            }
        };
        $request = (new Psr7\Request('GET', 'http://safe.example/', ['Host' => 'safe.example']))
            ->withUri($uri, true)
            ->withoutHeader('Host');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid host');

        Psr7\Message::toString($request);
    }

    public function testConvertsResponsesToStrings(): void
    {
        $response = new Psr7\Response(200, [
            'Baz' => 'bar',
            'Qux' => 'ipsum',
        ], 'hello', '1.0', 'FOO');
        self::assertSame(
            "HTTP/1.0 200 FOO\r\nBaz: bar\r\nQux: ipsum\r\n\r\nhello",
            Psr7\Message::toString($response)
        );
    }

    public function testCorrectlyRendersSetCookieHeadersToString(): void
    {
        $response = new Psr7\Response(200, [
            'Set-Cookie' => ['bar', 'baz', 'qux'],
        ], 'hello', '1.0', 'FOO');
        self::assertSame(
            "HTTP/1.0 200 FOO\r\nSet-Cookie: bar\r\nSet-Cookie: baz\r\nSet-Cookie: qux\r\n\r\nhello",
            Psr7\Message::toString($response)
        );
    }

    public function testRewindsBody(): void
    {
        $body = Psr7\Utils::streamFor('abc');
        $res = new Psr7\Response(200, [], $body);
        Psr7\Message::rewindBody($res);
        self::assertSame(0, $body->tell());
        $body->rewind();
        Psr7\Message::rewindBody($res);
        self::assertSame(0, $body->tell());
    }

    public function testThrowsWhenBodyCannotBeRewound(): void
    {
        $body = Psr7\Utils::streamFor('abc');
        $body->read(1);
        $body = FnStream::decorate($body, [
            'rewind' => function (): void {
                throw new \RuntimeException('a');
            },
        ]);
        $res = new Psr7\Response(200, [], $body);

        $this->expectException(\RuntimeException::class);

        Psr7\Message::rewindBody($res);
    }

    public function testParseMessagePreservesRawMessageShape(): void
    {
        $parsed = Psr7\Message::parseMessage(
            "\r\nGET /raw HTTP/1.1\nX-Thing: one\nx-thing: two\nEmpty:\n\nbody\n\nrest"
        );

        self::assertSame('GET /raw HTTP/1.1', $parsed['start-line']);
        self::assertSame([
            'X-Thing' => ['one'],
            'x-thing' => ['two'],
            'Empty' => [''],
        ], $parsed['headers']);
        self::assertSame("body\n\nrest", $parsed['body']);
    }

    public function testParsesRequestMessages(): void
    {
        $req = "GET /abc HTTP/1.0\r\nHost: foo.com\r\nFoo: Bar\r\nBaz: Bam\r\nBaz: Qux\r\n\r\nTest";
        $request = Psr7\Message::parseRequest($req);
        self::assertSame('GET', $request->getMethod());
        self::assertSame('/abc', $request->getRequestTarget());
        self::assertSame('1.0', $request->getProtocolVersion());
        self::assertSame('foo.com', $request->getHeaderLine('Host'));
        self::assertSame('Bar', $request->getHeaderLine('Foo'));
        self::assertSame('Bam, Qux', $request->getHeaderLine('Baz'));
        self::assertSame('Test', (string) $request->getBody());
        self::assertSame('http://foo.com/abc', (string) $request->getUri());
    }

    public function testParsesRequestMessagesWithHttpsScheme(): void
    {
        $req = "PUT /abc?baz=bar HTTP/1.1\r\nHost: foo.com:443\r\n\r\n";
        $request = Psr7\Message::parseRequest($req);
        self::assertSame('PUT', $request->getMethod());
        self::assertSame('/abc?baz=bar', $request->getRequestTarget());
        self::assertSame('1.1', $request->getProtocolVersion());
        self::assertSame('foo.com:443', $request->getHeaderLine('Host'));
        self::assertSame('', (string) $request->getBody());
        self::assertSame('https://foo.com/abc?baz=bar', (string) $request->getUri());
    }

    public function testParsesRequestMessagesWithUriWhenHostIsNotFirst(): void
    {
        $req = "PUT / HTTP/1.1\r\nFoo: Bar\r\nHost: foo.com\r\n\r\n";
        $request = Psr7\Message::parseRequest($req);
        self::assertSame('PUT', $request->getMethod());
        self::assertSame('/', $request->getRequestTarget());
        self::assertSame('http://foo.com/', (string) $request->getUri());
    }

    /**
     * @dataProvider invalidHostHeaderProvider
     */
    public function testParseRequestRejectsInvalidHostHeader(string $host): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Psr7\Message::parseRequest("GET / HTTP/1.1\r\nHost: {$host}\r\n\r\n");
    }

    public static function invalidHostHeaderProvider(): iterable
    {
        yield 'empty' => [''];
        yield 'userinfo delimiter' => ['trusted.example@evil.example'];
        yield 'percent-encoded userinfo delimiters' => ['user%3Apass%40example.com'];
        yield 'percent-encoded slash' => ['ex%2Fample.com'];
        yield 'path delimiter' => ['example.com/path'];
        yield 'query delimiter' => ['example.com?query'];
        yield 'fragment delimiter' => ['example.com#fragment'];
        yield 'backslash delimiter' => ['example.com\\evil'];
        yield 'space' => ['bad host'];
        yield 'tab' => ["bad\thost"];
        yield 'control character' => ['example'.chr(1).'com'];
        yield 'delete' => ['example'.chr(0x7F).'com'];
        yield 'zero port' => ['foo.com:0'];
        yield 'zero padded zero port' => ['foo.com:0000'];
        yield 'empty port' => ['example.com:'];
        yield 'non numeric port' => ['example.com:abc'];
        yield 'leading plus port' => ['example.com:+443'];
        yield 'negative port' => ['example.com:-1'];
        yield 'out of range port' => ['example.com:65536'];
        yield 'multiple ports' => ['example.com:443:8443'];
        yield 'missing closing bracket' => ['[::1'];
        yield 'unexpected bracket suffix' => ['[::1]x'];
        yield 'invalid ip literal' => ['[bad]'];
        yield 'ipv6 zero port' => ['[::1]:0'];
        yield 'ipv6 zero padded zero port' => ['[::1]:0000'];
        yield 'ipv6 empty port' => ['[::1]:'];
        yield 'ipv6 non numeric port' => ['[::1]:abc'];
        yield 'ipv6 out of range port' => ['[::1]:65536'];
        yield 'empty ipvfuture address' => ['[v7.]'];
        yield 'unexpected opening bracket' => ['foo[bar'];
        yield 'unexpected closing bracket' => ['foo]bar'];
    }

    /**
     * @dataProvider duplicateHostHeaderProvider
     */
    public function testParseRequestRejectsDuplicateHostHeaders(string $message): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Psr7\Message::parseRequest($message);
    }

    public static function duplicateHostHeaderProvider(): iterable
    {
        yield 'duplicate same case' => [
            "GET / HTTP/1.1\r\nHost: one.example\r\nHost: two.example\r\n\r\n",
        ];

        yield 'duplicate different case' => [
            "GET / HTTP/1.1\r\nHost: one.example\r\nhost: two.example\r\n\r\n",
        ];

        yield 'duplicate on options asterisk' => [
            "OPTIONS * HTTP/1.1\r\nHost: one.example\r\nHost: two.example\r\n\r\n",
        ];

        yield 'duplicate on absolute form' => [
            "GET https://up.example/ HTTP/1.1\r\nHost: one.example\r\nHost: two.example\r\n\r\n",
        ];

        yield 'duplicate on connect authority form' => [
            "CONNECT up.example:443 HTTP/1.1\r\nHost: one.example\r\nHost: two.example\r\n\r\n",
        ];
    }

    public function testParseRequestUriRejectsMultipleHostValues(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Psr7\Message::parseRequestUri('/', ['Host' => ['one.example', 'two.example']]);
    }

    public function testParseRequestUriCollapsesHostlessMultiSlashPath(): void
    {
        self::assertSame('/evil.example/x', Psr7\Message::parseRequestUri('//evil.example/x', []));
    }

    /**
     * @dataProvider invalidHostHeaderProvider
     */
    public function testParseOptionsAsteriskRejectsInvalidHostHeader(string $host): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Psr7\Message::parseRequest("OPTIONS * HTTP/1.1\r\nHost: {$host}\r\n\r\n");
    }

    /**
     * @dataProvider validHostHeaderProvider
     */
    public function testParseRequestAcceptsValidHostHeader(string $host, string $expectedUri): void
    {
        $request = Psr7\Message::parseRequest("GET / HTTP/1.1\r\nHost: {$host}\r\n\r\n");

        self::assertSame($host, $request->getHeaderLine('Host'));
        self::assertSame($expectedUri, (string) $request->getUri());
    }

    public static function validHostHeaderProvider(): iterable
    {
        yield 'host' => ['foo.com', 'http://foo.com/'];
        yield 'https default port' => ['foo.com:443', 'https://foo.com/'];
        yield 'non-default port' => ['foo.com:8080', 'http://foo.com:8080/'];
        yield 'https leading zero default port' => ['foo.com:000443', 'https://foo.com/'];
        yield 'http leading zero default port' => ['foo.com:000080', 'http://foo.com/'];
        yield 'leading zero non-default port' => ['foo.com:0008080', 'http://foo.com:8080/'];
        yield 'maximum port' => ['foo.com:65535', 'http://foo.com:65535/'];
        yield 'percent-encoded host' => ['ex%61mple.com', 'http://ex%61mple.com/'];
        yield 'ipv6' => ['[::1]', 'http://[::1]/'];
        yield 'ipv6 port' => ['[::1]:443', 'https://[::1]/'];
        yield 'ipv6 https leading zero default port' => ['[::1]:000443', 'https://[::1]/'];
        yield 'ipv6 leading zero non-default port' => ['[::1]:0008080', 'http://[::1]:8080/'];
    }

    public function testParseRequestAcceptsMissingHostHeader(): void
    {
        $request = Psr7\Message::parseRequest("GET /abc HTTP/1.1\r\nFoo: bar\r\n\r\n");

        self::assertSame('/abc', (string) $request->getUri());
    }

    /**
     * @dataProvider hostlessMultiSlashTargetProvider
     */
    public function testParseRequestTreatsHostlessMultiSlashTargetAsPath(string $target, string $expected): void
    {
        $request = Psr7\Message::parseRequest("GET {$target} HTTP/1.1\r\nFoo: bar\r\n\r\n");

        self::assertSame($expected, (string) $request->getUri());
        self::assertSame('', $request->getUri()->getHost());
        self::assertFalse($request->hasHeader('Host'));
        self::assertSame($expected, $request->getRequestTarget());
    }

    public static function hostlessMultiSlashTargetProvider(): iterable
    {
        yield 'authority-like target' => ['//evil.example/x', '/evil.example/x'];
        yield 'authority-like target with query' => ['//evil.example/x?q=1', '/evil.example/x?q=1'];
        yield 'double slash only' => ['//', '/'];
        yield 'triple slash' => ['///x', '/x'];
        yield 'internal double slash preserved' => ['//evil.example//x', '/evil.example//x'];
    }

    public function testParseRequestCollapsesMultiSlashTargetWithHostHeader(): void
    {
        $request = Psr7\Message::parseRequest("GET //evil.example/x HTTP/1.1\r\nHost: good.example\r\n\r\n");

        self::assertSame('http://good.example/evil.example/x', (string) $request->getUri());
        self::assertSame('good.example', $request->getHeaderLine('Host'));
        self::assertSame('/evil.example/x', $request->getRequestTarget());
    }

    public function testParsesRequestMessagesWithFullUri(): void
    {
        $req = "GET https://www.google.com:443/search?q=foobar HTTP/1.1\r\nHost: www.google.com\r\n\r\n";
        $request = Psr7\Message::parseRequest($req);
        self::assertSame('GET', $request->getMethod());
        self::assertSame('https://www.google.com:443/search?q=foobar', $request->getRequestTarget());
        self::assertSame('1.1', $request->getProtocolVersion());
        self::assertSame('www.google.com', $request->getHeaderLine('Host'));
        self::assertSame('', (string) $request->getBody());
        self::assertSame('https://www.google.com/search?q=foobar', (string) $request->getUri());
    }

    /**
     * @dataProvider invalidHostHeaderProvider
     */
    public function testParseAbsoluteFormRejectsInvalidHostHeader(string $host): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Psr7\Message::parseRequest("GET https://good.example/admin HTTP/1.1\r\nHost: {$host}\r\n\r\n");
    }

    public function testParseAbsoluteFormAllowsValidHostDifferentFromTargetAuthority(): void
    {
        $request = Psr7\Message::parseRequest("GET https://up.example/admin HTTP/1.1\r\nHost: good.example\r\n\r\n");

        self::assertSame('https://up.example/admin', $request->getRequestTarget());
        self::assertSame('good.example', $request->getHeaderLine('Host'));
        self::assertSame('https://up.example/admin', (string) $request->getUri());
    }

    public function testParseAbsoluteFormAllowsMissingHostHeader(): void
    {
        $request = Psr7\Message::parseRequest("GET https://up.example/admin HTTP/1.1\r\n\r\n");

        self::assertSame('https://up.example/admin', $request->getRequestTarget());
        self::assertSame('up.example', $request->getHeaderLine('Host'));
        self::assertSame('https://up.example/admin', (string) $request->getUri());
    }

    public function testParseAbsoluteFormPreservesMultiSlashPath(): void
    {
        $request = Psr7\Message::parseRequest("GET https://up.example//admin HTTP/1.1\r\n\r\n");

        self::assertSame('https://up.example//admin', $request->getRequestTarget());
        self::assertSame('up.example', $request->getUri()->getHost());
        self::assertSame('https://up.example//admin', (string) $request->getUri());
    }

    /**
     * @dataProvider validAbsoluteFormTargetProvider
     */
    public function testParseRequestAcceptsAbsoluteFormTarget(string $target, string $expectedHost, string $expectedUri): void
    {
        $request = Psr7\Message::parseRequest("GET {$target} HTTP/1.1\r\n\r\n");

        self::assertSame($target, $request->getRequestTarget());
        self::assertSame($expectedHost, $request->getHeaderLine('Host'));
        self::assertSame($expectedUri, (string) $request->getUri());
    }

    public static function validAbsoluteFormTargetProvider(): iterable
    {
        yield 'non-default port' => ['http://up.example:8080/admin', 'up.example:8080', 'http://up.example:8080/admin'];
        yield 'ipv6 non-default port' => ['http://[::1]:8080/admin?x=1', '[::1]:8080', 'http://[::1]:8080/admin?x=1'];
        yield 'path at-sign' => ['http://up.example/admin@v1', 'up.example', 'http://up.example/admin@v1'];
        yield 'query at-sign' => ['http://up.example?email=user@example.com', 'up.example', 'http://up.example?email=user@example.com'];
        yield 'percent-encoded host' => ['http://ex%61mple.com/admin', 'ex%61mple.com', 'http://ex%61mple.com/admin'];
        yield 'empty port' => ['http://up.example:/admin', 'up.example', 'http://up.example/admin'];
    }

    public function testParsesOptionsAsteriskFormRequestTarget(): void
    {
        $req = "OPTIONS * HTTP/1.1\r\nHost: foo.com\r\n\r\n";
        $request = Psr7\Message::parseRequest($req);

        self::assertSame('OPTIONS', $request->getMethod());
        self::assertSame('*', $request->getRequestTarget());
        self::assertSame('1.1', $request->getProtocolVersion());
        self::assertSame('foo.com', $request->getHeaderLine('Host'));
        self::assertSame('', (string) $request->getBody());
        self::assertSame('http://foo.com', (string) $request->getUri());
    }

    public function testParsesOptionsAsteriskFormRequestTargetWithLeadingZeroHttpsPort(): void
    {
        $request = Psr7\Message::parseRequest("OPTIONS * HTTP/1.1\r\nHost: foo.com:000443\r\n\r\n");

        self::assertSame('*', $request->getRequestTarget());
        self::assertSame('foo.com:000443', $request->getHeaderLine('Host'));
        self::assertSame('https://foo.com', (string) $request->getUri());
    }

    public function testParsesOptionsAsteriskFormRequestTargetWithIpv6HttpsPort(): void
    {
        $request = Psr7\Message::parseRequest("OPTIONS * HTTP/1.1\r\nHost: [::1]:443\r\n\r\n");

        self::assertSame('*', $request->getRequestTarget());
        self::assertSame('[::1]:443', $request->getHeaderLine('Host'));
        self::assertSame('https://[::1]', (string) $request->getUri());
    }

    public function testParsesOptionsAsteriskFormRequestTargetWithIpv6LeadingZeroHttpsPort(): void
    {
        $request = Psr7\Message::parseRequest("OPTIONS * HTTP/1.1\r\nHost: [::1]:000443\r\n\r\n");

        self::assertSame('*', $request->getRequestTarget());
        self::assertSame('[::1]:000443', $request->getHeaderLine('Host'));
        self::assertSame('https://[::1]', (string) $request->getUri());
    }

    public function testParsesOptionsAsteriskFormRequestTargetWithNonCanonicalIpv6Host(): void
    {
        $request = Psr7\Message::parseRequest("OPTIONS * HTTP/1.1\r\nHost: [0:0::1]:443\r\n\r\n");

        self::assertSame('*', $request->getRequestTarget());
        self::assertSame('[0:0::1]:443', $request->getHeaderLine('Host'));
        self::assertSame('https://[::1]', (string) $request->getUri());
    }

    public function testParsesOptionsAsteriskFormRequestTargetWithoutHost(): void
    {
        $req = "OPTIONS * HTTP/1.1\r\n\r\n";
        $request = Psr7\Message::parseRequest($req);

        self::assertSame('OPTIONS', $request->getMethod());
        self::assertSame('*', $request->getRequestTarget());
        self::assertSame('', $request->getHeaderLine('Host'));
        self::assertSame('', (string) $request->getUri());
    }

    public function testParsesConnectAuthorityFormRequestTarget(): void
    {
        $req = "CONNECT up.example:443 HTTP/1.1\r\nHost: up.example:443\r\n\r\n";
        $request = Psr7\Message::parseRequest($req);

        self::assertSame('CONNECT', $request->getMethod());
        self::assertSame('up.example:443', $request->getRequestTarget());
        self::assertSame('1.1', $request->getProtocolVersion());
        self::assertSame('up.example:443', $request->getHeaderLine('Host'));
        self::assertSame('', (string) $request->getBody());
        self::assertSame('//up.example:443', (string) $request->getUri());
    }

    public function testParsesConnectAuthorityFormRequestTargetWithIpv6(): void
    {
        $req = "CONNECT [::1]:443 HTTP/1.1\r\nHost: [::1]:443\r\n\r\n";
        $request = Psr7\Message::parseRequest($req);

        self::assertSame('CONNECT', $request->getMethod());
        self::assertSame('[::1]:443', $request->getRequestTarget());
        self::assertSame('[::1]:443', $request->getHeaderLine('Host'));
        self::assertSame('//[::1]:443', (string) $request->getUri());
    }

    public function testParsesConnectAuthorityFormRequestTargetWithLeadingZeroPort(): void
    {
        $req = "CONNECT up.example:000443 HTTP/1.1\r\nHost: up.example:000443\r\n\r\n";
        $request = Psr7\Message::parseRequest($req);

        self::assertSame('CONNECT', $request->getMethod());
        self::assertSame('up.example:000443', $request->getRequestTarget());
        self::assertSame('up.example:000443', $request->getHeaderLine('Host'));
        self::assertSame('//up.example:443', (string) $request->getUri());
    }

    public function testParsesConnectAuthorityFormRequestTargetWithIpv6LeadingZeroPort(): void
    {
        $req = "CONNECT [::1]:000443 HTTP/1.1\r\nHost: [::1]:000443\r\n\r\n";
        $request = Psr7\Message::parseRequest($req);

        self::assertSame('CONNECT', $request->getMethod());
        self::assertSame('[::1]:000443', $request->getRequestTarget());
        self::assertSame('[::1]:000443', $request->getHeaderLine('Host'));
        self::assertSame('//[::1]:443', (string) $request->getUri());
    }

    /**
     * @dataProvider invalidHostHeaderProvider
     */
    public function testParseConnectRejectsInvalidHostHeader(string $host): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Psr7\Message::parseRequest("CONNECT up.example:443 HTTP/1.1\r\nHost: {$host}\r\n\r\n");
    }

    public function testParseConnectAllowsValidHostDifferentFromTargetAuthority(): void
    {
        $request = Psr7\Message::parseRequest("CONNECT up.example:443 HTTP/1.1\r\nHost: good.example\r\n\r\n");

        self::assertSame('CONNECT', $request->getMethod());
        self::assertSame('up.example:443', $request->getRequestTarget());
        self::assertSame('good.example', $request->getHeaderLine('Host'));
        self::assertSame('//up.example:443', (string) $request->getUri());
    }

    public function testParseConnectAllowsMissingHostHeader(): void
    {
        $request = Psr7\Message::parseRequest("CONNECT up.example:443 HTTP/1.1\r\n\r\n");

        self::assertSame('CONNECT', $request->getMethod());
        self::assertSame('up.example:443', $request->getRequestTarget());
        self::assertSame('up.example:443', $request->getHeaderLine('Host'));
        self::assertSame('//up.example:443', (string) $request->getUri());
    }

    public function testParsesRequestMessagesWithCustomMethod(): void
    {
        $req = "GET_DATA / HTTP/1.1\r\nFoo: Bar\r\nHost: foo.com\r\n\r\n";
        $request = Psr7\Message::parseRequest($req);
        self::assertSame('GET_DATA', $request->getMethod());
    }

    public function testParsesRequestMessagesWithNumericHeader(): void
    {
        $req = "GET /abc HTTP/1.0\r\nHost: foo.com\r\nFoo: Bar\r\nBaz: Bam\r\nBaz: Qux\r\n123: 456\r\n\r\nTest";
        $request = Psr7\Message::parseRequest($req);
        self::assertSame('GET', $request->getMethod());
        self::assertSame('/abc', $request->getRequestTarget());
        self::assertSame('1.0', $request->getProtocolVersion());
        self::assertSame('foo.com', $request->getHeaderLine('Host'));
        self::assertSame('Bar', $request->getHeaderLine('Foo'));
        self::assertSame('Bam, Qux', $request->getHeaderLine('Baz'));
        self::assertSame('456', $request->getHeaderLine('123'));
        self::assertSame('Test', (string) $request->getBody());
        self::assertSame('http://foo.com/abc', (string) $request->getUri());
    }

    public function testParsesRequestMessagesWithFoldedHeadersOnHttp10(): void
    {
        $req = "PUT / HTTP/1.0\r\nFoo: Bar\r\n Bam\r\n\r\n";
        $request = Psr7\Message::parseRequest($req);
        self::assertSame('PUT', $request->getMethod());
        self::assertSame('/', $request->getRequestTarget());
        self::assertSame('Bar Bam', $request->getHeaderLine('Foo'));
    }

    /**
     * @dataProvider foldedTokenMethodProvider
     */
    public function testParsesRequestMessagesWithFoldedHeadersAndTokenMethodOnHttp10(string $method): void
    {
        $request = Psr7\Message::parseRequest("{$method} / HTTP/1.0\r\nFoo: Bar\r\n Bam\r\n\r\n");

        self::assertSame($method, $request->getMethod());
        self::assertSame('Bar Bam', $request->getHeaderLine('Foo'));
    }

    public static function foldedTokenMethodProvider(): iterable
    {
        yield 'underscore' => ['GET_DATA'];
        yield 'hyphen' => ['M-SEARCH'];
    }

    public function testParseMessageRejectsFoldedHeadersWhenTargetHasControlBytes(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid header syntax: Obsolete line folding');

        Psr7\Message::parseMessage("GET /a\x7Fb HTTP/1.0\r\nFoo: Bar\r\n Bam\r\n\r\n");
    }

    public function testParseMessageUnfoldsFoldedHeadersWithLowercaseHttp10StartLine(): void
    {
        $parsed = Psr7\Message::parseMessage("get / http/1.0\r\nFoo: Bar\r\n Bam\r\n\r\n");

        self::assertSame('get / http/1.0', $parsed['start-line']);
        self::assertSame(['Bar Bam'], $parsed['headers']['Foo']);
    }

    public function testRequestParsingFailsWithFoldedHeadersOnHttp11(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid header syntax: Obsolete line folding');

        Psr7\Message::parseRequest("GET_DATA / HTTP/1.1\r\nFoo: Bar\r\n Biz: Bam\r\n\r\n");
    }

    public function testParsesRequestMessagesWhenHeaderDelimiterIsOnlyALineFeed(): void
    {
        $req = "PUT / HTTP/1.0\nFoo: Bar\nBaz: Bam\n\n";
        $request = Psr7\Message::parseRequest($req);
        self::assertSame('PUT', $request->getMethod());
        self::assertSame('/', $request->getRequestTarget());
        self::assertSame('Bar', $request->getHeaderLine('Foo'));
        self::assertSame('Bam', $request->getHeaderLine('Baz'));
    }

    public function testValidatesRequestMessages(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Psr7\Message::parseRequest("HTTP/1.1 200 OK\r\n\r\n");
    }

    /**
     * @dataProvider invalidRequestStartLineProvider
     */
    public function testParseRequestRejectsInvalidStartLine(string $startLine): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Psr7\Message::parseRequest($startLine."\r\nHost: foo.com\r\n\r\n");
    }

    public static function invalidRequestStartLineProvider(): iterable
    {
        yield 'invalid method' => ['GET/ / HTTP/1.1'];
        yield 'target space' => ['GET /foo bar HTTP/1.1'];
        yield 'target tab' => ["GET /foo\tbar HTTP/1.1"];
        yield 'target nul' => ["GET /foo\0bar HTTP/1.1"];
        yield 'target delete' => ["GET /foo\x7Fbar HTTP/1.1"];
        yield 'asterisk non-options' => ['GET * HTTP/1.1'];
        yield 'lowercase options asterisk' => ['options * HTTP/1.1'];
        yield 'mixed-case options asterisk' => ['OpTiOnS * HTTP/1.1'];
        yield 'authority-form non-connect' => ['GET up.example:443 HTTP/1.1'];
        yield 'lowercase connect authority-form' => ['connect up.example:443 HTTP/1.1'];
        yield 'mixed-case connect authority-form' => ['CoNnEcT up.example:443 HTTP/1.1'];
        yield 'connect missing port' => ['CONNECT up.example HTTP/1.1'];
        yield 'connect zero port' => ['CONNECT up.example:0 HTTP/1.1'];
        yield 'connect ipv6 zero port' => ['CONNECT [::1]:0 HTTP/1.1'];
        yield 'connect user info' => ['CONNECT user@up.example:443 HTTP/1.1'];
        yield 'connect path' => ['CONNECT up.example:443/ HTTP/1.1'];
        yield 'connect query' => ['CONNECT up.example:443?x=1 HTTP/1.1'];
        yield 'absolute-form zero port' => ['GET http://up.example:0/admin HTTP/1.1'];
        yield 'absolute-form ipv6 zero port' => ['GET http://[::1]:0/admin HTTP/1.1'];
        yield 'absolute-form zero padded zero port' => ['GET http://up.example:0000/admin HTTP/1.1'];
        yield 'absolute-form missing host' => ['GET file:///etc/passwd HTTP/1.1'];
        yield 'absolute-form user info' => ['GET http://user:pass@up.example/admin HTTP/1.1'];
        yield 'absolute-form authority-obscuring user info' => ['GET http://trusted.example@evil.example/admin HTTP/1.1'];
        yield 'absolute-form empty user info' => ['GET http://@up.example/admin HTTP/1.1'];
        yield 'absolute-form double at user info' => ['GET http://a@b@up.example/admin HTTP/1.1'];
        yield 'absolute-form ipv6 user info' => ['GET http://u@[::1]:8080/admin HTTP/1.1'];
        yield 'absolute-form user info zero port' => ['GET http://u@up.example:0/admin HTTP/1.1'];
        yield 'absolute-form non-http user info' => ['GET ftp://u:p@files.example/x HTTP/1.1'];
        yield 'absolute-form percent-encoded host delimiters' => ['GET http://user%3Apass%40example.com/admin HTTP/1.1'];
        yield 'invalid protocol text' => ['GET / HTTP/foo'];
        yield 'invalid protocol segments' => ['GET / HTTP/1.1.1'];
        yield 'bare carriage return after version' => ["GET / HTTP/1.1\rX-Injected: yes"];
        yield 'missing version' => ['GET /'];
    }

    public function testParsesResponseMessages(): void
    {
        $res = "HTTP/1.0 200 OK\r\nFoo: Bar\r\nBaz: Bam\r\nBaz: Qux\r\n\r\nTest";
        $response = Psr7\Message::parseResponse($res);
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('OK', $response->getReasonPhrase());
        self::assertSame('1.0', $response->getProtocolVersion());
        self::assertSame('Bar', $response->getHeaderLine('Foo'));
        self::assertSame('Bam, Qux', $response->getHeaderLine('Baz'));
        self::assertSame('Test', (string) $response->getBody());
    }

    public function testParsesResponseWithoutReason(): void
    {
        $res = "HTTP/1.0 200\r\nFoo: Bar\r\nBaz: Bam\r\nBaz: Qux\r\n\r\nTest";
        $response = Psr7\Message::parseResponse($res);
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('OK', $response->getReasonPhrase());
        self::assertSame('1.0', $response->getProtocolVersion());
        self::assertSame('Bar', $response->getHeaderLine('Foo'));
        self::assertSame('Bam, Qux', $response->getHeaderLine('Baz'));
        self::assertSame('Test', (string) $response->getBody());
    }

    public function testParsesResponseWithLeadingDelimiter(): void
    {
        $res = "\r\nHTTP/1.0 200\r\nFoo: Bar\r\n\r\nTest";
        $response = Psr7\Message::parseResponse($res);
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('OK', $response->getReasonPhrase());
        self::assertSame('1.0', $response->getProtocolVersion());
        self::assertSame('Bar', $response->getHeaderLine('Foo'));
        self::assertSame('Test', (string) $response->getBody());
    }

    public function testParsesResponseWithFoldedHeadersOnHttp10(): void
    {
        $res = "HTTP/1.0 200\r\nFoo: Bar\r\n Bam\r\n\r\nTest";
        $response = Psr7\Message::parseResponse($res);
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('OK', $response->getReasonPhrase());
        self::assertSame('1.0', $response->getProtocolVersion());
        self::assertSame('Bar Bam', $response->getHeaderLine('Foo'));
        self::assertSame('Test', (string) $response->getBody());
    }

    public function testResponseParsingFailsWithFoldedHeadersOnHttp11(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid header syntax: Obsolete line folding');
        Psr7\Message::parseResponse("HTTP/1.1 200\r\nFoo: Bar\r\n Biz: Bam\r\nBaz: Qux\r\n\r\nTest");
    }

    public function testParsesResponseWhenHeaderDelimiterIsOnlyALineFeed(): void
    {
        $res = "HTTP/1.0 200\nFoo: Bar\nBaz: Bam\n\nTest\n\nOtherTest";
        $response = Psr7\Message::parseResponse($res);
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('OK', $response->getReasonPhrase());
        self::assertSame('1.0', $response->getProtocolVersion());
        self::assertSame('Bar', $response->getHeaderLine('Foo'));
        self::assertSame('Bam', $response->getHeaderLine('Baz'));
        self::assertSame("Test\n\nOtherTest", (string) $response->getBody());
    }

    public function testResponseParsingFailsWithoutHeaderDelimiter(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid message: Missing header delimiter');
        Psr7\Message::parseResponse("HTTP/1.0 200\r\nFoo: Bar\r\n Baz: Bam\r\nBaz: Qux\r\n");
    }

    public function testValidatesResponseMessages(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Psr7\Message::parseResponse("GET / HTTP/1.1\r\n\r\n");
    }

    public function testParsesResponseWithAllowedCustomReasonPhraseCharacters(): void
    {
        $response = Psr7\Message::parseResponse("HTTP/1.1 200 OK\tFine\x80\r\n\r\n");

        self::assertSame("OK\tFine\x80", $response->getReasonPhrase());
    }

    public function testParsesBoundaryResponseStatusCodes(): void
    {
        $informationalResponse = Psr7\Message::parseResponse("HTTP/1.1 100 Continue\r\n\r\n");
        $customServerErrorResponse = Psr7\Message::parseResponse("HTTP/1.1 599 Custom\r\n\r\n");

        self::assertSame(100, $informationalResponse->getStatusCode());
        self::assertSame(599, $customServerErrorResponse->getStatusCode());
    }

    /**
     * @dataProvider invalidResponseStartLineProvider
     */
    public function testParseResponseRejectsInvalidStartLine(string $startLine): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Psr7\Message::parseResponse($startLine."\r\n\r\n");
    }

    public static function invalidResponseStartLineProvider(): iterable
    {
        yield 'invalid protocol text' => ['HTTP/foo 200 OK'];
        yield 'invalid protocol segments' => ['HTTP/1.1.1 200 OK'];
        yield 'status below range' => ['HTTP/1.1 099 OK'];
        yield 'status above range' => ['HTTP/1.1 600 OK'];
        yield 'status 700' => ['HTTP/1.1 700 OK'];
        yield 'status 999' => ['HTTP/1.1 999 OK'];
        yield 'non-numeric status' => ['HTTP/1.1 20x OK'];
        yield 'tab before reason' => ["HTTP/1.1 200\tOK"];
        yield 'bare carriage return in reason' => ["HTTP/1.1 200 OK\rX-Injected: yes"];
        yield 'reason nul' => ["HTTP/1.1 200 OK\0"];
        yield 'reason delete' => ["HTTP/1.1 200 OK\x7F"];
    }

    public function testParseResponseEscapesControlsInInvalidStartLineDiagnostic(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid response string: HTTP/1.1 200 OK\\x00');

        Psr7\Message::parseResponse("HTTP/1.1 200 OK\0\r\n\r\n");
    }

    public function testCanModifyRequestWithUri(): void
    {
        $r1 = new Psr7\Request('GET', 'http://foo.com');
        $r2 = Psr7\Message::modifyRequest($r1, [
            'uri' => new Psr7\Uri('http://www.foo.com'),
        ]);
        self::assertSame('http://www.foo.com', (string) $r2->getUri());
        self::assertSame('www.foo.com', (string) $r2->getHeaderLine('host'));
    }

    public function testCanModifyRequestWithUriAndPort(): void
    {
        $r1 = new Psr7\Request('GET', 'http://foo.com:8000');
        $r2 = Psr7\Message::modifyRequest($r1, [
            'uri' => new Psr7\Uri('http://www.foo.com:8000'),
        ]);
        self::assertSame('http://www.foo.com:8000', (string) $r2->getUri());
        self::assertSame('www.foo.com:8000', (string) $r2->getHeaderLine('host'));
    }

    public function testCanModifyRequestWithUriAndZeroPort(): void
    {
        $r1 = new Psr7\Request('GET', 'http://foo.com');
        $r2 = Psr7\Message::modifyRequest($r1, [
            'uri' => (new Psr7\Uri('http://www.foo.com'))->withPort(0),
        ]);
        self::assertSame('http://www.foo.com:0', (string) $r2->getUri());
        self::assertSame('www.foo.com:0', (string) $r2->getHeaderLine('host'));
    }

    public function testCanModifyRequestWithUriAndNonHttpSchemePort(): void
    {
        $r1 = new Psr7\Request('GET', 'http://foo.com');
        $r2 = Psr7\Message::modifyRequest($r1, [
            'uri' => new Psr7\Uri('ws://www.foo.com:8080'),
        ]);
        self::assertSame('ws://www.foo.com:8080', (string) $r2->getUri());
        self::assertSame('www.foo.com:8080', (string) $r2->getHeaderLine('host'));
    }

    public function testModifyRequestRejectsInvalidUriHostFromCustomUri(): void
    {
        $uri = $this->createMock(UriInterface::class);
        $uri->method('getHost')->willReturn("foo\nbar");
        $uri->method('getPort')->willReturn(null);

        $this->expectException(\InvalidArgumentException::class);

        Psr7\Message::modifyRequest(new Psr7\Request('GET', '/'), ['uri' => $uri]);
    }

    public function testModifyRequestOmitsDefaultPortFromCustomUri(): void
    {
        $uri = $this->createMock(UriInterface::class);
        $uri->method('getHost')->willReturn('www.foo.com');
        $uri->method('getPort')->willReturn(80);
        $uri->method('getScheme')->willReturn('http');

        $r2 = Psr7\Message::modifyRequest(new Psr7\Request('GET', '/'), ['uri' => $uri]);

        self::assertSame('www.foo.com', $r2->getHeaderLine('host'));
    }

    public function testCanModifyRequestWithFalseyUriHost(): void
    {
        $r1 = new Psr7\Request('GET', 'http://foo.com');
        $r2 = Psr7\Message::modifyRequest($r1, [
            'uri' => new Psr7\Uri('http://0'),
        ]);
        self::assertSame('http://0', (string) $r2->getUri());
        self::assertSame('0', (string) $r2->getHeaderLine('host'));
    }

    public function testCanModifyRequestWithCaseInsensitiveHeader(): void
    {
        $r1 = new Psr7\Request('GET', 'http://foo.com', ['User-Agent' => 'foo']);
        $r2 = Psr7\Message::modifyRequest($r1, ['set_headers' => ['User-agent' => 'bar']]);
        self::assertSame('bar', $r2->getHeaderLine('User-Agent'));
        self::assertSame('bar', $r2->getHeaderLine('User-agent'));
    }

    public function testReturnsAsIsWhenNoChanges(): void
    {
        $r1 = new Psr7\Request('GET', 'http://foo.com');
        $r2 = Psr7\Message::modifyRequest($r1, []);
        self::assertSame($r1, $r2);

        $r1 = new Psr7\ServerRequest('GET', 'http://foo.com');
        $r2 = Psr7\Message::modifyRequest($r1, []);
        self::assertSame($r1, $r2);
    }

    public function testReturnsUriAsIsWhenNoChanges(): void
    {
        $r1 = new Psr7\Request('GET', 'http://foo.com');
        $r2 = Psr7\Message::modifyRequest($r1, ['set_headers' => ['foo' => 'bar']]);
        self::assertNotSame($r1, $r2);
        self::assertSame('bar', $r2->getHeaderLine('foo'));
    }

    public function testRemovesHeadersFromMessage(): void
    {
        $r1 = new Psr7\Request('GET', 'http://foo.com', ['foo' => 'bar']);
        $r2 = Psr7\Message::modifyRequest($r1, ['remove_headers' => ['foo']]);
        self::assertNotSame($r1, $r2);
        self::assertFalse($r2->hasHeader('foo'));
    }

    public function testAddsQueryToUri(): void
    {
        $r1 = new Psr7\Request('GET', 'http://foo.com');
        $r2 = Psr7\Message::modifyRequest($r1, ['query' => 'foo=bar']);
        self::assertNotSame($r1, $r2);
        self::assertSame('foo=bar', $r2->getUri()->getQuery());
    }

    public function testModifyRequestKeepInstanceOfRequest(): void
    {
        $r1 = new Psr7\Request('GET', 'http://foo.com');
        $r2 = Psr7\Message::modifyRequest($r1, ['remove_headers' => ['non-existent']]);
        self::assertInstanceOf('GuzzleHttp\Psr7\Request', $r2);

        $r1 = new Psr7\ServerRequest('GET', 'http://foo.com');
        $r2 = Psr7\Message::modifyRequest($r1, ['remove_headers' => ['non-existent']]);
        self::assertInstanceOf('Psr\Http\Message\ServerRequestInterface', $r2);
    }

    public function testModifyRequestPreservesConcreteRequestSubclass(): void
    {
        $request = new class('GET', 'http://example.com', 'user-123') extends Psr7\Request {
            private string $userId;

            public function __construct(string $method, $uri, string $userId)
            {
                $this->userId = $userId;

                parent::__construct($method, $uri);
            }

            public function userId(): string
            {
                return $this->userId;
            }
        };

        $modified = Psr7\Message::modifyRequest($request, [
            'method' => 'POST',
            'uri' => new Psr7\Uri('http://www.example.com/path'),
            'query' => 'a=b',
            'set_headers' => ['X-Test' => '1'],
            'body' => 'payload',
            'version' => '2',
        ]);

        self::assertSame(get_class($request), get_class($modified));
        self::assertSame('user-123', $modified->userId());
        self::assertSame('POST', $modified->getMethod());
        self::assertSame('http://www.example.com/path?a=b', (string) $modified->getUri());
        self::assertSame('www.example.com', $modified->getHeaderLine('Host'));
        self::assertSame('1', $modified->getHeaderLine('X-Test'));
        self::assertSame('payload', (string) $modified->getBody());
        self::assertSame('2', $modified->getProtocolVersion());

        self::assertSame('GET', $request->getMethod());
        self::assertFalse($request->hasHeader('X-Test'));
    }

    public function testModifyRequestPreservesConcreteServerRequestSubclass(): void
    {
        $request = new class('GET', 'http://example.com', [], null, '1.1', ['server' => 'value'], 'ctx') extends Psr7\ServerRequest {
            private string $context;

            public function __construct(
                string $method,
                $uri,
                array $headers,
                $body,
                string $version,
                array $serverParams,
                string $context
            ) {
                $this->context = $context;

                parent::__construct($method, $uri, $headers, $body, $version, $serverParams);
            }

            public function context(): string
            {
                return $this->context;
            }
        };

        $file = new Psr7\UploadedFile('Test', 100, \UPLOAD_ERR_OK);
        $request = $request
            ->withCookieParams(['cookie' => 'value'])
            ->withQueryParams(['query' => 'value'])
            ->withParsedBody(['body' => 'value'])
            ->withUploadedFiles([$file])
            ->withAttribute('attribute', 'value');

        $modified = Psr7\Message::modifyRequest($request, [
            'set_headers' => ['X-Test' => '1'],
        ]);

        self::assertSame(get_class($request), get_class($modified));
        self::assertSame('ctx', $modified->context());
        self::assertSame(['server' => 'value'], $modified->getServerParams());
        self::assertSame(['cookie' => 'value'], $modified->getCookieParams());
        self::assertSame(['query' => 'value'], $modified->getQueryParams());
        self::assertSame(['body' => 'value'], $modified->getParsedBody());
        self::assertSame([$file], $modified->getUploadedFiles());
        self::assertSame(['attribute' => 'value'], $modified->getAttributes());
        self::assertSame('1', $modified->getHeaderLine('X-Test'));
    }

    public function testModifyRequestPreservesProvidedUriImplementation(): void
    {
        $uri = self::customUri('http://www.example.com/path');
        $request = new Psr7\Request('GET', 'http://example.com');

        $modified = Psr7\Message::modifyRequest($request, [
            'uri' => $uri,
        ]);

        self::assertSame($uri, $modified->getUri());
        self::assertSame('www.example.com', $modified->getHeaderLine('Host'));
        self::assertSame('http://www.example.com/path', (string) $modified->getUri());
    }

    public function testModifyRequestPreservesProvidedUriImplementationWhenChangingQuery(): void
    {
        $uri = self::customUri('http://www.example.com/path');
        $request = new Psr7\Request('GET', 'http://example.com');

        $modified = Psr7\Message::modifyRequest($request, [
            'uri' => $uri,
            'query' => 'a=b',
        ]);

        self::assertSame(get_class($uri), get_class($modified->getUri()));
        self::assertSame('www.example.com', $modified->getHeaderLine('Host'));
        self::assertSame('http://www.example.com/path?a=b', (string) $modified->getUri());
    }

    public function testModifyRequestPreservesExistingUriImplementationWhenChangingQuery(): void
    {
        $uri = self::customUri('http://example.com/path');
        $request = new Psr7\Request('GET', $uri);

        $modified = Psr7\Message::modifyRequest($request, [
            'query' => 'a=b',
        ]);

        self::assertSame(get_class($uri), get_class($modified->getUri()));
        self::assertSame('http://example.com/path?a=b', (string) $modified->getUri());
        self::assertSame('', $uri->getQuery());
    }

    public function testModifyRequestPreservesProvidedStreamImplementationForBody(): void
    {
        $body = self::customStream('payload');
        $request = new Psr7\Request('GET', 'http://example.com');

        $modified = Psr7\Message::modifyRequest($request, [
            'body' => $body,
        ]);

        self::assertSame($body, $modified->getBody());
        self::assertSame('payload', (string) $modified->getBody());
    }

    public function testModifyRequestPreservesRequestUriAndBodyImplementationsTogether(): void
    {
        $request = self::customRequest('GET', self::customUri('http://example.com/original'));
        $uri = self::customUri('http://www.example.com/path');
        $body = self::customStream('payload');

        $modified = Psr7\Message::modifyRequest($request, [
            'method' => 'POST',
            'uri' => $uri,
            'query' => 'a=b',
            'set_headers' => ['X-Test' => '1'],
            'body' => $body,
            'version' => '2',
        ]);

        self::assertSame(get_class($request), get_class($modified));
        self::assertSame(get_class($uri), get_class($modified->getUri()));
        self::assertSame('POST', $modified->getMethod());
        self::assertSame('http://www.example.com/path?a=b', (string) $modified->getUri());
        self::assertSame('www.example.com', $modified->getHeaderLine('Host'));
        self::assertSame('1', $modified->getHeaderLine('X-Test'));
        self::assertSame($body, $modified->getBody());
        self::assertSame('payload', (string) $modified->getBody());
        self::assertSame('2', $modified->getProtocolVersion());

        self::assertSame('GET', $request->getMethod());
        self::assertSame('http://example.com/original', (string) $request->getUri());
        self::assertFalse($request->hasHeader('X-Test'));
    }

    public function testModifyRequestConvertsBodyWithStreamFor(): void
    {
        $request = new Psr7\Request('GET', 'http://example.com');

        $modified = Psr7\Message::modifyRequest($request, [
            'body' => 'payload',
        ]);

        self::assertInstanceOf(StreamInterface::class, $modified->getBody());
        self::assertSame('payload', (string) $modified->getBody());
    }

    public function testModifyRequestConvertsCallableArrayBodyWithStreamFor(): void
    {
        $source = new class {
            /** @var list<string|false> */
            private array $chunks = ['payload', false];

            /**
             * @return string|false
             */
            public function read(int $length)
            {
                if ($this->chunks === []) {
                    return false;
                }

                return array_shift($this->chunks);
            }
        };

        $request = new Psr7\Request('GET', 'http://example.com');

        $modified = Psr7\Message::modifyRequest($request, [
            'body' => [$source, 'read'],
        ]);

        self::assertInstanceOf(Psr7\PumpStream::class, $modified->getBody());
        self::assertSame('payload', $modified->getBody()->getContents());
    }

    public function testModifyRequestConvertsStringableBodyWithStreamFor(): void
    {
        $request = new Psr7\Request('GET', 'http://example.com');

        $modified = Psr7\Message::modifyRequest($request, [
            'body' => new HasToString(),
        ]);

        self::assertSame('foo', $modified->getBody()->getContents());
    }

    public function testModifyRequestTreatsCallableStringBodyAsString(): void
    {
        $request = new Psr7\Request('GET', 'http://example.com');

        $modified = Psr7\Message::modifyRequest($request, [
            'body' => 'strlen',
        ]);

        self::assertNotInstanceOf(Psr7\PumpStream::class, $modified->getBody());
        self::assertSame('strlen', $modified->getBody()->getContents());
    }

    public function testModifyRequestReaddsHostHeaderWhenFinalHeadersDoNotContainHost(): void
    {
        $request = (new Psr7\Request('GET', 'http://example.com'))->withoutHeader('Host');

        $modified = Psr7\Message::modifyRequest($request, [
            'set_headers' => ['X-Test' => '1'],
        ]);

        self::assertSame('example.com', $modified->getHeaderLine('Host'));
        self::assertSame('1', $modified->getHeaderLine('X-Test'));
    }

    public function testModifyRequestValidatesReaddedHostFromCustomUri(): void
    {
        $uri = new class('http://safe.example/') extends Psr7\Uri {
            public function getHost(): string
            {
                return 'ex%2Fample.com';
            }
        };
        $request = (new Psr7\Request('GET', 'http://safe.example/', ['Host' => 'safe.example']))
            ->withUri($uri, true)
            ->withoutHeader('Host');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid host');

        Psr7\Message::modifyRequest($request, ['set_headers' => ['X-Test' => '1']]);
    }

    /**
     * @dataProvider hostHeaderCaseProvider
     */
    public function testModifyRequestRejectsUriAndExplicitHostHeader(string $hostHeader): void
    {
        $request = new Psr7\Request('GET', 'http://foo.com');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot modify request with both a URI containing a host and an explicit Host header.');

        Psr7\Message::modifyRequest($request, [
            'uri' => new Psr7\Uri('http://bar.com'),
            'set_headers' => [$hostHeader => 'custom'],
        ]);
    }

    public function testModifyRequestRejectsFalseyUriHostAndExplicitHostHeader(): void
    {
        $request = new Psr7\Request('GET', 'http://foo.com');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot modify request with both a URI containing a host and an explicit Host header.');

        Psr7\Message::modifyRequest($request, [
            'uri' => new Psr7\Uri('http://0'),
            'set_headers' => ['host' => 'custom'],
        ]);
    }

    public function testModifyRequestCanSetExplicitHostHeaderWithoutUriChange(): void
    {
        $request = new Psr7\Request('GET', 'http://foo.com');

        $modified = Psr7\Message::modifyRequest($request, [
            'set_headers' => ['host' => 'custom'],
        ]);

        self::assertSame('custom', $modified->getHeaderLine('Host'));
    }

    public function testModifyRequestCanSetExplicitHostHeaderWithRelativeUriChange(): void
    {
        $request = new Psr7\Request('GET', 'http://foo.com');

        $modified = Psr7\Message::modifyRequest($request, [
            'uri' => new Psr7\Uri('/relative'),
            'set_headers' => ['host' => 'custom'],
        ]);

        self::assertSame('custom', $modified->getHeaderLine('Host'));
        self::assertSame('/relative', (string) $modified->getUri());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function hostHeaderCaseProvider(): iterable
    {
        yield 'canonical' => ['Host'];
        yield 'lowercase' => ['host'];
        yield 'uppercase' => ['HOST'];
        yield 'mixed case' => ['HoSt'];
    }

    public function testModifyRequestPreservesNumericHeaderNames(): void
    {
        $request = new Psr7\Request('GET', 'http://example.com', ['123' => 'old']);

        $modified = Psr7\Message::modifyRequest($request, [
            'set_headers' => ['X-Test' => '1'],
        ]);

        self::assertSame('old', $modified->getHeaderLine('123'));
        self::assertSame('1', $modified->getHeaderLine('X-Test'));
    }

    public function testModifyRequestReplacesNumericHeaderNames(): void
    {
        $request = new Psr7\Request('GET', 'http://example.com', ['123' => 'old']);

        $modified = Psr7\Message::modifyRequest($request, [
            'set_headers' => ['123' => 'new'],
        ]);

        self::assertSame('new', $modified->getHeaderLine('123'));
    }

    public function testModifyRequestRemovesNumericHeaderNames(): void
    {
        $request = new Psr7\Request('GET', 'http://example.com', ['123' => 'old']);

        $modified = Psr7\Message::modifyRequest($request, [
            'remove_headers' => ['123'],
        ]);

        self::assertFalse($modified->hasHeader('123'));
    }

    /**
     * @dataProvider providesInvalidModifyRequestChanges
     */
    public function testModifyRequestRejectsInvalidChangeValues(array $changes, string $expectedMessage): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage($expectedMessage);

        Psr7\Message::modifyRequest(new Psr7\Request('GET', 'http://example.com'), $changes);
    }

    public function testModifyRequestIgnoresUnknownChangeKeys(): void
    {
        $request = new Psr7\Request('GET', 'http://example.com');

        self::assertSame($request, Psr7\Message::modifyRequest($request, ['unknown' => new \stdClass()]));
    }

    public function testModifyServerRequestWithUploadedFiles(): void
    {
        $request = new Psr7\ServerRequest('GET', 'http://example.com/bla');
        $file = new Psr7\UploadedFile('Test', 100, \UPLOAD_ERR_OK);
        $request = $request->withUploadedFiles([$file]);

        /** @var Psr7\ServerRequest $modifiedRequest */
        $modifiedRequest = Psr7\Message::modifyRequest($request, ['set_headers' => ['foo' => 'bar']]);

        self::assertCount(1, $modifiedRequest->getUploadedFiles());

        $files = $modifiedRequest->getUploadedFiles();
        self::assertInstanceOf('GuzzleHttp\Psr7\UploadedFile', $files[0]);
    }

    public function testModifyServerRequestWithCookies(): void
    {
        $request = (new Psr7\ServerRequest('GET', 'http://example.com/bla'))
            ->withCookieParams(['name' => 'value']);

        /** @var Psr7\ServerRequest $modifiedRequest */
        $modifiedRequest = Psr7\Message::modifyRequest($request, ['set_headers' => ['foo' => 'bar']]);

        self::assertSame(['name' => 'value'], $modifiedRequest->getCookieParams());
    }

    public function testModifyServerRequestParsedBody(): void
    {
        $request = (new Psr7\ServerRequest('GET', 'http://example.com/bla'))
            ->withParsedBody(['name' => 'value']);

        /** @var Psr7\ServerRequest $modifiedRequest */
        $modifiedRequest = Psr7\Message::modifyRequest($request, ['set_headers' => ['foo' => 'bar']]);

        self::assertSame(['name' => 'value'], $modifiedRequest->getParsedBody());
    }

    public function testModifyServerRequestQueryParams(): void
    {
        $request = (new Psr7\ServerRequest('GET', 'http://example.com/bla'))
            ->withQueryParams(['name' => 'value']);

        /** @var Psr7\ServerRequest $modifiedRequest */
        $modifiedRequest = Psr7\Message::modifyRequest($request, ['set_headers' => ['foo' => 'bar']]);

        self::assertSame(['name' => 'value'], $modifiedRequest->getQueryParams());
    }

    public function testModifyServerRequestRetainsAttributes(): void
    {
        $request = (new Psr7\ServerRequest('GET', 'http://example.com/bla'))
            ->withAttribute('foo', 'bar');

        /** @var Psr7\ServerRequest $modifiedRequest */
        $modifiedRequest = Psr7\Message::modifyRequest($request, ['set_headers' => ['baz' => 'qux']]);

        self::assertSame(['foo' => 'bar'], $modifiedRequest->getAttributes());
    }

    /**
     * @return array<string, array{0: array, 1: string}>
     */
    public static function providesInvalidModifyRequestChanges(): array
    {
        return [
            'method null' => [
                ['method' => null],
                'Message::modifyRequest() change "method" must be string; null provided.',
            ],
            'method int' => [
                ['method' => 123],
                'Message::modifyRequest() change "method" must be string; int provided.',
            ],
            'uri null' => [
                ['uri' => null],
                'Message::modifyRequest() change "uri" must be UriInterface; null provided.',
            ],
            'uri string' => [
                ['uri' => 'http://example.com'],
                'Message::modifyRequest() change "uri" must be UriInterface; string provided.',
            ],
            'query null' => [
                ['query' => null],
                'Message::modifyRequest() change "query" must be string; null provided.',
            ],
            'query int' => [
                ['query' => 123],
                'Message::modifyRequest() change "query" must be string; int provided.',
            ],
            'version null' => [
                ['version' => null],
                'Message::modifyRequest() change "version" must be string; null provided.',
            ],
            'version int' => [
                ['version' => 2],
                'Message::modifyRequest() change "version" must be string; int provided.',
            ],
            'body null' => [
                ['body' => null],
                'Message::modifyRequest() change "body" must be resource|string|StreamInterface|callable|\Iterator|\Stringable; null provided.',
            ],
            'set_headers null' => [
                ['set_headers' => null],
                'Message::modifyRequest() change "set_headers" must be array<array-key, string|non-empty-array<array-key, string>>; null provided.',
            ],
            'set_headers string' => [
                ['set_headers' => 'X-Test: value'],
                'Message::modifyRequest() change "set_headers" must be array<array-key, string|non-empty-array<array-key, string>>; string provided.',
            ],
            'set_headers value bool' => [
                ['set_headers' => ['X-Test' => false]],
                'Message::modifyRequest() change "set_headers.X-Test" must be string|non-empty-array<array-key, string>; bool provided.',
            ],
            'set_headers control in key' => [
                ['set_headers' => ["X\nTest" => false]],
                'Message::modifyRequest() change "set_headers.X\\x0ATest" must be string|non-empty-array<array-key, string>; bool provided.',
            ],
            'set_headers value empty array' => [
                ['set_headers' => ['X-Test' => []]],
                'Message::modifyRequest() change "set_headers.X-Test" must be string|non-empty-array<array-key, string>; array provided.',
            ],
            'set_headers value array non-string item' => [
                ['set_headers' => ['X-Test' => [false]]],
                'Message::modifyRequest() change "set_headers.X-Test.0" must be string; bool provided.',
            ],
            'remove_headers null' => [
                ['remove_headers' => null],
                'Message::modifyRequest() change "remove_headers" must be array<array-key, string|int>; null provided.',
            ],
            'remove_headers string' => [
                ['remove_headers' => 'Host'],
                'Message::modifyRequest() change "remove_headers" must be array<array-key, string|int>; string provided.',
            ],
            'remove_headers bool value' => [
                ['remove_headers' => [false]],
                'Message::modifyRequest() change "remove_headers.0" must be string|int; bool provided.',
            ],
        ];
    }

    private static function customRequest(string $method, UriInterface $uri): Psr7\Request
    {
        return new class($method, $uri) extends Psr7\Request {
        };
    }

    private static function customUri(string $uri): UriInterface
    {
        return new class($uri) extends Psr7\Uri {
        };
    }

    private static function customStream(string $contents): StreamInterface
    {
        $resource = Psr7\Utils::tryFopen('php://temp', 'r+');
        \fwrite($resource, $contents);
        \rewind($resource);

        return new class($resource) extends Psr7\Stream {
        };
    }

    public function testMessageBodySummaryWithSmallBody(): void
    {
        $message = new Psr7\Response(200, [], 'Lorem ipsum dolor sit amet, consectetur adipiscing elit.');
        self::assertSame('Lorem ipsum dolor sit amet, consectetur adipiscing elit.', Psr7\Message::bodySummary($message));
    }

    public function testMessageBodySummaryWithLargeBody(): void
    {
        $message = new Psr7\Response(200, [], 'Lorem ipsum dolor sit amet, consectetur adipiscing elit.');
        self::assertSame('Lorem ipsu (truncated...)', Psr7\Message::bodySummary($message, 10));
    }

    public function testMessageBodySummaryAcceptsNullTruncationLength(): void
    {
        $message = new Psr7\Response(200, [], str_repeat('a', 121));

        self::assertSame(
            Psr7\Message::bodySummary($message, 120),
            Psr7\Message::bodySummary($message, null)
        );
    }

    public function testMessageBodySummaryWithSpecialUTF8Characters(): void
    {
        $message = new Psr7\Response(200, [], '’é€௵ဪ‱');
        self::assertSame('’é€௵ဪ‱', Psr7\Message::bodySummary($message));
    }

    public function testMessageBodySummaryWithSpecialUTF8CharactersAndLargeBody(): void
    {
        $message = new Psr7\Response(200, [], '🤦🏾‍♀️');
        // The first Unicode codepoint of the body has four bytes.
        self::assertSame(' (truncated...)', Psr7\Message::bodySummary($message, 3));
    }

    public function testMessageBodySummaryTrimsIncompleteUTF8Character(): void
    {
        $message = new Psr7\Response(200, [], '必填性规则校验失败，此字段为必填项');
        $expected = '必填性规则校验失 (truncated...)';

        self::assertSame($expected, Psr7\Message::bodySummary($message, 24));
        self::assertSame($expected, Psr7\Message::bodySummary($message, 25));
        self::assertSame($expected, Psr7\Message::bodySummary($message, 26));
    }

    public function testMessageBodySummaryTrimsIncompleteUTF8CharacterForIssue588Payload(): void
    {
        $message = new Psr7\Response(200, [], '{"code":"PARAM_ERROR","detail":{"location":"body","value":""},"message":"输入源“/body/sub_mchid”映射到字段“子商户号/二级商户号”必填性规则校验失败，此字段为必填项"}');

        self::assertSame(
            '{"code":"PARAM_ERROR","detail":{"location":"body","value":""},"message":"输入源“/body/sub_mchid”映射到字段 (truncated...)',
            Psr7\Message::bodySummary($message, 120)
        );
    }

    public function testMessageBodySummaryRejectsBinaryBody(): void
    {
        $message = new Psr7\Response(200, [], "abc\0def");

        self::assertNull(Psr7\Message::bodySummary($message));
    }

    public function testMessageBodySummaryRejectsInvalidUTF8Body(): void
    {
        self::assertNull(Psr7\Message::bodySummary(new Psr7\Response(200, [], "abc\xFFdef"), 4));
        self::assertNull(Psr7\Message::bodySummary(new Psr7\Response(200, [], "abc\xE2xy"), 4));
    }

    public function testMessageBodySummaryWithEmptyBody(): void
    {
        $message = new Psr7\Response(200, [], '');
        self::assertNull(Psr7\Message::bodySummary($message));
    }

    public function testMessageBodySummaryNotInitiallyRewound(): void
    {
        $message = new Psr7\Response(200, [], 'Lorem ipsum dolor sit amet, consectetur adipiscing elit.');
        $message->getBody()->read(10);

        self::assertSame('Lorem ipsu (truncated...)', Psr7\Message::bodySummary($message, 10));
        self::assertSame(10, $message->getBody()->tell());
    }

    public function testMessageBodySummaryRestoresOriginalPosition(): void
    {
        $body = Psr7\Utils::streamFor('abcdef');
        $body->seek(3);
        $message = new Psr7\Response(200, [], $body);

        self::assertSame('abcdef', Psr7\Message::bodySummary($message));
        self::assertSame(3, $body->tell());
    }

    public function testMessageBodySummaryRestoresOriginalPositionAfterUtf8Lookahead(): void
    {
        $body = Psr7\Utils::streamFor('必填性规则校验失败，此字段为必填项');
        $body->seek(6);
        $message = new Psr7\Response(200, [], $body);

        self::assertSame('必填性规则校验失 (truncated...)', Psr7\Message::bodySummary($message, 25));
        self::assertSame(6, $body->tell());
    }

    public function testMessageBodySummaryRestoresOriginalPositionWhenReturningNull(): void
    {
        $body = Psr7\Utils::streamFor("abc\0def");
        $body->seek(3);
        $message = new Psr7\Response(200, [], $body);

        self::assertNull(Psr7\Message::bodySummary($message));
        self::assertSame(3, $body->tell());

        $body = Psr7\Utils::streamFor("abc\xFFdef");
        $body->seek(2);
        $message = new Psr7\Response(200, [], $body);

        self::assertNull(Psr7\Message::bodySummary($message, 4));
        self::assertSame(2, $body->tell());
    }

    public function testMessageBodySummaryThrowsWhenOriginalPositionCannotBeDetermined(): void
    {
        $body = Psr7\Utils::streamFor('abc');
        $body = FnStream::decorate($body, [
            'tell' => static function (): int {
                throw new \RuntimeException('tell failed');
            },
        ]);
        $message = new Psr7\Response(200, [], $body);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('tell failed');

        Psr7\Message::bodySummary($message);
    }

    public function testMessageBodySummaryPropagatesOverflow(): void
    {
        $body = new FnStream([
            'isSeekable' => static function (): bool {
                return true;
            },
            'isReadable' => static function (): bool {
                return true;
            },
            'getSize' => static function (): int {
                throw new \OverflowException('size overflow');
            },
        ]);
        $message = new Psr7\Response(200, [], $body);

        $this->expectException(\OverflowException::class);
        $this->expectExceptionMessage('size overflow');

        Psr7\Message::bodySummary($message);
    }

    public function testGetResponseBodySummaryOfNonReadableStream(): void
    {
        $message = new Psr7\Response(500, [], new ReadSeekOnlyStream());
        self::assertNull(Psr7\Message::bodySummary($message));
    }
}
