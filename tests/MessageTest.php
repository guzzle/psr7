<?php

declare(strict_types=1);

namespace GuzzleHttp\Tests\Psr7;

use GuzzleHttp\Psr7;
use GuzzleHttp\Psr7\FnStream;
use PHPUnit\Framework\TestCase;

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

    public function testRequestParsingFailsWithFoldedHeadersOnHttp11(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid header syntax: Obsolete line folding');

        Psr7\Message::parseResponse("GET_DATA / HTTP/1.1\r\nFoo: Bar\r\n Biz: Bam\r\n\r\n");
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
        yield 'invalid protocol text' => ['GET / HTTP/foo'];
        yield 'invalid protocol segments' => ['GET / HTTP/1.1.1'];
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
        yield 'reason nul' => ["HTTP/1.1 200 OK\0"];
        yield 'reason delete' => ["HTTP/1.1 200 OK\x7F"];
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

    public function testGetResponseBodySummaryOfNonReadableStream(): void
    {
        $message = new Psr7\Response(500, [], new ReadSeekOnlyStream());
        self::assertNull(Psr7\Message::bodySummary($message));
    }
}
