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

    public function testMessageBodySummaryWithComfortableUTF8Characters(): void
    {
        $message = new Psr7\Response(200, [], '必填性规则校验失败，此字段为必填项');
        $wellformed = '必填性规则校验失 (truncated...)';
        // One chinese character is three bytes in the utf8 character range.
        self::assertSame($wellformed, Psr7\Message::bodySummary($message, 24));
        self::assertSame($wellformed, Psr7\Message::bodySummary($message, 25));
        self::assertSame($wellformed, Psr7\Message::bodySummary($message, 26));
    }

    public function testMessageBodySummaryWithSpecialUTF8CharactersAndLargeBody(): void
    {
        $message = new Psr7\Response(200, [], '🤦🏾‍♀️');
        // The first Unicode codepoint of the body has four bytes.
        self::assertNull(Psr7\Message::bodySummary($message, 3));
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
    }

    public function testGetResponseBodySummaryOfNonReadableStream(): void
    {
        $message = new Psr7\Response(500, [], new ReadSeekOnlyStream());
        self::assertNull(Psr7\Message::bodySummary($message));
    }
}
