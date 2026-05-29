<?php

declare(strict_types=1);

namespace GuzzleHttp\Tests\Psr7;

use GuzzleHttp\Psr7;
use GuzzleHttp\Psr7\FnStream;
use GuzzleHttp\Psr7\NoSeekStream;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;

class UtilsTest extends TestCase
{
    public function testCopiesToString(): void
    {
        $s = Psr7\Utils::streamFor('foobaz');
        self::assertSame('foobaz', Psr7\Utils::copyToString($s));
        $s->seek(0);
        self::assertSame('foo', Psr7\Utils::copyToString($s, 3));
        self::assertSame('baz', Psr7\Utils::copyToString($s, 3));
        self::assertSame('', Psr7\Utils::copyToString($s));
    }

    public function testCopiesToStringStopsWhenReadFails(): void
    {
        $s1 = Psr7\Utils::streamFor('foobaz');
        $s1 = FnStream::decorate($s1, [
            'read' => function () {
                return '';
            },
        ]);
        $result = Psr7\Utils::copyToString($s1);
        self::assertSame('', $result);
    }

    public function testCopiesToStream(): void
    {
        $s1 = Psr7\Utils::streamFor('foobaz');
        $s2 = Psr7\Utils::streamFor('');
        Psr7\Utils::copyToStream($s1, $s2);
        self::assertSame('foobaz', (string) $s2);
        $s2 = Psr7\Utils::streamFor('');
        $s1->seek(0);
        Psr7\Utils::copyToStream($s1, $s2, 3);
        self::assertSame('foo', (string) $s2);
        Psr7\Utils::copyToStream($s1, $s2, 3);
        self::assertSame('foobaz', (string) $s2);
    }

    public function testCopyToStreamRetriesShortWrites(): void
    {
        $s1 = Psr7\Utils::streamFor('foobaz');
        $sink = Psr7\Utils::streamFor('');
        $writes = 0;

        $s2 = FnStream::decorate($sink, [
            'write' => function (string $string) use ($sink, &$writes): int {
                ++$writes;

                return $sink->write(substr($string, 0, 1));
            },
        ]);

        Psr7\Utils::copyToStream($s1, $s2);

        self::assertSame('foobaz', (string) $sink);
        self::assertSame(6, $writes);
    }

    public function testCopyToStreamRetriesShortWritesWithMaxLen(): void
    {
        $s1 = Psr7\Utils::streamFor('foobaz');
        $sink = Psr7\Utils::streamFor('');
        $writes = 0;

        $s2 = FnStream::decorate($sink, [
            'write' => function (string $string) use ($sink, &$writes): int {
                ++$writes;

                return $sink->write(substr($string, 0, 1));
            },
        ]);

        Psr7\Utils::copyToStream($s1, $s2, 3);

        self::assertSame('foo', (string) $sink);
        self::assertSame(3, $writes);
    }

    public function testCopyToStreamStopsWhenDestinationMakesNoProgress(): void
    {
        $s1 = Psr7\Utils::streamFor('foobaz');
        $sink = Psr7\Utils::streamFor('');
        $s2 = FnStream::decorate($sink, [
            'write' => function () {
                return 0;
            },
        ]);

        Psr7\Utils::copyToStream($s1, $s2);

        self::assertSame('', (string) $sink);
    }

    public function testCopyToStreamStopsWhenDestinationMakesNoProgressWithMaxLen(): void
    {
        $s1 = Psr7\Utils::streamFor('foobaz');
        $sink = Psr7\Utils::streamFor('');
        $s2 = FnStream::decorate($sink, [
            'write' => function () {
                return 0;
            },
        ]);

        Psr7\Utils::copyToStream($s1, $s2, 10);

        self::assertSame('', (string) $sink);
    }

    public function testCopyToStreamStopsWithoutThrowingWhenDestinationBufferStreamReachesHighWaterMark(): void
    {
        $dest = new Psr7\BufferStream(3);

        Psr7\Utils::copyToStream(Psr7\Utils::streamFor('foobaz'), $dest);

        self::assertSame('foobaz', (string) $dest);
    }

    public function testCopyToStreamStopsWithoutThrowingWhenDestinationDroppingStreamIsFull(): void
    {
        $underlying = new Psr7\BufferStream();
        $dest = new Psr7\DroppingStream($underlying, 3);

        Psr7\Utils::copyToStream(Psr7\Utils::streamFor('foobaz'), $dest);

        self::assertSame('foo', (string) $underlying);
    }

    public function testCopyToStreamReadsInChunksInsteadOfAllInMemory(): void
    {
        $sizes = [];
        $s1 = new FnStream([
            'eof' => function () {
                return false;
            },
            'read' => function ($size) use (&$sizes) {
                $sizes[] = $size;

                return str_repeat('.', $size);
            },
        ]);
        $s2 = Psr7\Utils::streamFor('');
        Psr7\Utils::copyToStream($s1, $s2, 16394);
        $s2->seek(0);
        self::assertSame(16394, strlen($s2->getContents()));
        self::assertSame(8192, $sizes[0]);
        self::assertSame(8192, $sizes[1]);
        self::assertSame(10, $sizes[2]);
    }

    public function testStopsCopyToSteamWhenReadFailsWithMaxLen(): void
    {
        $s1 = Psr7\Utils::streamFor('foobaz');
        $s1 = FnStream::decorate($s1, [
            'read' => function () {
                return '';
            },
        ]);
        $s2 = Psr7\Utils::streamFor('');
        Psr7\Utils::copyToStream($s1, $s2, 10);
        self::assertSame('', (string) $s2);
    }

    public function testReadsLines(): void
    {
        $s = Psr7\Utils::streamFor("foo\nbaz\nbar");
        self::assertSame("foo\n", Psr7\Utils::readLine($s));
        self::assertSame("baz\n", Psr7\Utils::readLine($s));
        self::assertSame('bar', Psr7\Utils::readLine($s));
    }

    public function testReadsLinesUpToMaxLength(): void
    {
        $s = Psr7\Utils::streamFor("12345\n");
        self::assertSame('123', Psr7\Utils::readLine($s, 4));
        self::assertSame("45\n", Psr7\Utils::readLine($s));
    }

    public function testReadLinesEof(): void
    {
        // Should return empty string on EOF
        $s = Psr7\Utils::streamFor("foo\nbar");
        while (!$s->eof()) {
            Psr7\Utils::readLine($s);
        }
        self::assertSame('', Psr7\Utils::readLine($s));
    }

    public function testReadsLineUntilEmptyStringReturnedFromRead(): void
    {
        $s = $this->createMock(StreamInterface::class);
        $s->expects(self::exactly(2))
            ->method('read')
            ->willReturnCallback(function () {
                static $called = false;
                if ($called) {
                    return '';
                }
                $called = true;

                return 'h';
            });
        $s->expects(self::exactly(2))
            ->method('eof')
            ->willReturn(false);
        self::assertSame('h', Psr7\Utils::readLine($s));
    }

    public function testRedactUserInfo(): void
    {
        $uri = new Psr7\Uri('http://my_user:secretPass@localhost/');

        $redactedUri = Psr7\Utils::redactUserInfo($uri);

        self::assertSame('http://my_user:***@localhost/', (string) $redactedUri);
    }

    public function testCalculatesHash(): void
    {
        $s = Psr7\Utils::streamFor('foobazbar');
        self::assertSame(md5('foobazbar'), Psr7\Utils::hash($s, 'md5'));
    }

    public function testCalculatesHashThrowsWhenSeekFails(): void
    {
        $s = new NoSeekStream(Psr7\Utils::streamFor('foobazbar'));
        $s->read(2);

        $this->expectException(\RuntimeException::class);

        Psr7\Utils::hash($s, 'md5');
    }

    public function testCalculatesHashSeeksToOriginalPosition(): void
    {
        $s = Psr7\Utils::streamFor('foobazbar');
        $s->seek(4);
        self::assertSame(md5('foobazbar'), Psr7\Utils::hash($s, 'md5'));
        self::assertSame(4, $s->tell());
    }

    public function testOpensFilesSuccessfully(): void
    {
        $r = Psr7\Utils::tryFopen(__FILE__, 'r');
        self::assertIsResource($r);
        fclose($r);
    }

    public function testThrowsExceptionNotWarning(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unable to open "/path/to/does/not/exist" using mode "r"');

        Psr7\Utils::tryFopen('/path/to/does/not/exist', 'r');
    }

    public function testThrowsExceptionNotValueError(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unable to open "" using mode "r"');

        Psr7\Utils::tryFopen('', 'r');
    }

    /**
     * @requires PHP 7.4
     */
    public function testGetsContentsThrowExceptionWhenNotReadable(): void
    {
        $r = fopen(tempnam(sys_get_temp_dir(), 'guzzle-psr7-'), 'w');
        fwrite($r, 'hello world!');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unable to read stream contents');

        try {
            Psr7\Utils::tryGetContents($r);
        } finally {
            fclose($r);
        }
    }

    public function testGetsContentsThrowExceptionWhenCLosed(): void
    {
        $r = fopen(tempnam(sys_get_temp_dir(), 'guzzle-psr7-'), 'r+');
        fclose($r);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unable to read stream contents');

        Psr7\Utils::tryGetContents($r);
    }

    public function testCreatesUriForValue(): void
    {
        self::assertInstanceOf('GuzzleHttp\Psr7\Uri', Psr7\Utils::uriFor('/foo'));
        self::assertInstanceOf(
            'GuzzleHttp\Psr7\Uri',
            Psr7\Utils::uriFor(new Psr7\Uri('/foo'))
        );
    }

    public function testValidatesUri(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Psr7\Utils::uriFor([]);
    }

    public function testKeepsPositionOfResource(): void
    {
        $h = fopen(__FILE__, 'r');
        fseek($h, 10);
        $stream = Psr7\Utils::streamFor($h);
        self::assertSame(10, $stream->tell());
        $stream->close();
    }

    public function testCreatesWithFactory(): void
    {
        $stream = Psr7\Utils::streamFor('foo');
        self::assertInstanceOf('GuzzleHttp\Psr7\Stream', $stream);
        self::assertSame('foo', $stream->getContents());
        $stream->close();
    }

    public function testFactoryCreatesFromEmptyString(): void
    {
        $s = Psr7\Utils::streamFor();
        self::assertInstanceOf('GuzzleHttp\Psr7\Stream', $s);
    }

    public function testFactoryCreatesFromNull(): void
    {
        $s = Psr7\Utils::streamFor(null);
        self::assertInstanceOf('GuzzleHttp\Psr7\Stream', $s);
    }

    public function testFactoryCreatesFromResource(): void
    {
        $r = fopen(__FILE__, 'r');
        $s = Psr7\Utils::streamFor($r);
        self::assertInstanceOf('GuzzleHttp\Psr7\Stream', $s);
        self::assertSame(file_get_contents(__FILE__), (string) $s);
    }

    public function testFactoryCreatesFromObjectWithToString(): void
    {
        $r = new HasToString();
        $s = Psr7\Utils::streamFor($r);
        self::assertInstanceOf('GuzzleHttp\Psr7\Stream', $s);
        self::assertSame('foo', (string) $s);
    }

    public function testCreatePassesThrough(): void
    {
        $s = Psr7\Utils::streamFor('foo');
        self::assertSame($s, Psr7\Utils::streamFor($s));
    }

    public function testThrowsExceptionForUnknown(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Psr7\Utils::streamFor(new \stdClass());
    }

    public function testReturnsCustomMetadata(): void
    {
        $s = Psr7\Utils::streamFor('foo', ['metadata' => ['hwm' => 3]]);
        self::assertSame(3, $s->getMetadata('hwm'));
        self::assertArrayHasKey('hwm', $s->getMetadata());
    }

    public function testCustomUriMetadataDoesNotAffectStreamSize(): void
    {
        $s = Psr7\Utils::streamFor('foo', ['metadata' => ['uri' => []]]);

        self::assertSame([], $s->getMetadata('uri'));
        self::assertSame(3, $s->getSize());
    }

    public function testCanSetSize(): void
    {
        $s = Psr7\Utils::streamFor('', ['size' => 10]);
        self::assertSame(10, $s->getSize());
    }

    public function testCanCreateIteratorBasedStream(): void
    {
        $a = new \ArrayIterator(['foo', 'bar', '123']);
        $p = Psr7\Utils::streamFor($a);
        self::assertInstanceOf('GuzzleHttp\Psr7\PumpStream', $p);
        self::assertSame('foo', $p->read(3));
        self::assertFalse($p->eof());
        self::assertSame('b', $p->read(1));
        self::assertSame('a', $p->read(1));
        self::assertSame('r12', $p->read(3));
        self::assertFalse($p->eof());
        self::assertSame('3', $p->getContents());
        self::assertTrue($p->eof());
        self::assertSame(9, $p->tell());
    }

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

    public function testCanModifyRequestWithUri(): void
    {
        $r1 = new Psr7\Request('GET', 'http://foo.com');
        $r2 = Psr7\Utils::modifyRequest($r1, [
            'uri' => new Psr7\Uri('http://www.foo.com'),
        ]);
        self::assertSame('http://www.foo.com', (string) $r2->getUri());
        self::assertSame('www.foo.com', (string) $r2->getHeaderLine('host'));
    }

    public function testCanModifyRequestWithUriAndPort(): void
    {
        $r1 = new Psr7\Request('GET', 'http://foo.com:8000');
        $r2 = Psr7\Utils::modifyRequest($r1, [
            'uri' => new Psr7\Uri('http://www.foo.com:8000'),
        ]);
        self::assertSame('http://www.foo.com:8000', (string) $r2->getUri());
        self::assertSame('www.foo.com:8000', (string) $r2->getHeaderLine('host'));
    }

    public function testCanModifyRequestWithFalseyUriHost(): void
    {
        $r1 = new Psr7\Request('GET', 'http://foo.com');
        $r2 = Psr7\Utils::modifyRequest($r1, [
            'uri' => new Psr7\Uri('http://0'),
        ]);
        self::assertSame('http://0', (string) $r2->getUri());
        self::assertSame('0', (string) $r2->getHeaderLine('host'));
    }

    public function testCanModifyRequestWithCaseInsensitiveHeader(): void
    {
        $r1 = new Psr7\Request('GET', 'http://foo.com', ['User-Agent' => 'foo']);
        $r2 = Psr7\Utils::modifyRequest($r1, ['set_headers' => ['User-agent' => 'bar']]);
        self::assertSame('bar', $r2->getHeaderLine('User-Agent'));
        self::assertSame('bar', $r2->getHeaderLine('User-agent'));
    }

    public function testReturnsAsIsWhenNoChanges(): void
    {
        $r1 = new Psr7\Request('GET', 'http://foo.com');
        $r2 = Psr7\Utils::modifyRequest($r1, []);
        self::assertSame($r1, $r2);

        $r1 = new Psr7\ServerRequest('GET', 'http://foo.com');
        $r2 = Psr7\Utils::modifyRequest($r1, []);
        self::assertSame($r1, $r2);
    }

    public function testReturnsUriAsIsWhenNoChanges(): void
    {
        $r1 = new Psr7\Request('GET', 'http://foo.com');
        $r2 = Psr7\Utils::modifyRequest($r1, ['set_headers' => ['foo' => 'bar']]);
        self::assertNotSame($r1, $r2);
        self::assertSame('bar', $r2->getHeaderLine('foo'));
    }

    public function testRemovesHeadersFromMessage(): void
    {
        $r1 = new Psr7\Request('GET', 'http://foo.com', ['foo' => 'bar']);
        $r2 = Psr7\Utils::modifyRequest($r1, ['remove_headers' => ['foo']]);
        self::assertNotSame($r1, $r2);
        self::assertFalse($r2->hasHeader('foo'));
    }

    public function testAddsQueryToUri(): void
    {
        $r1 = new Psr7\Request('GET', 'http://foo.com');
        $r2 = Psr7\Utils::modifyRequest($r1, ['query' => 'foo=bar']);
        self::assertNotSame($r1, $r2);
        self::assertSame('foo=bar', $r2->getUri()->getQuery());
    }

    public function testModifyRequestKeepInstanceOfRequest(): void
    {
        $r1 = new Psr7\Request('GET', 'http://foo.com');
        $r2 = Psr7\Utils::modifyRequest($r1, ['remove_headers' => ['non-existent']]);
        self::assertInstanceOf('GuzzleHttp\Psr7\Request', $r2);

        $r1 = new Psr7\ServerRequest('GET', 'http://foo.com');
        $r2 = Psr7\Utils::modifyRequest($r1, ['remove_headers' => ['non-existent']]);
        self::assertInstanceOf('Psr\Http\Message\ServerRequestInterface', $r2);
    }

    public function testModifyRequestPreservesConcreteRequestSubclass(): void
    {
        $request = new class('GET', 'http://example.com', 'user-123') extends Psr7\Request {
            /** @var string */
            private $userId;

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

        $modified = Psr7\Utils::modifyRequest($request, [
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
            /** @var string */
            private $context;

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

        $modified = Psr7\Utils::modifyRequest($request, [
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

        $modified = Psr7\Utils::modifyRequest($request, [
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

        $modified = Psr7\Utils::modifyRequest($request, [
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

        $modified = Psr7\Utils::modifyRequest($request, [
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

        $modified = Psr7\Utils::modifyRequest($request, [
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

        $modified = Psr7\Utils::modifyRequest($request, [
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

        $modified = Psr7\Utils::modifyRequest($request, [
            'body' => 'payload',
        ]);

        self::assertInstanceOf(StreamInterface::class, $modified->getBody());
        self::assertSame('payload', (string) $modified->getBody());
    }

    public function testModifyRequestReaddsHostHeaderWhenFinalHeadersDoNotContainHost(): void
    {
        $request = (new Psr7\Request('GET', 'http://example.com'))->withoutHeader('Host');

        $modified = Psr7\Utils::modifyRequest($request, [
            'set_headers' => ['X-Test' => '1'],
        ]);

        self::assertSame('example.com', $modified->getHeaderLine('Host'));
        self::assertSame('1', $modified->getHeaderLine('X-Test'));
    }

    /**
     * @dataProvider hostHeaderCaseProvider
     */
    public function testModifyRequestRejectsUriAndExplicitHostHeader(string $hostHeader): void
    {
        $request = new Psr7\Request('GET', 'http://foo.com');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot modify request with both a URI containing a host and an explicit Host header.');

        Psr7\Utils::modifyRequest($request, [
            'uri' => new Psr7\Uri('http://bar.com'),
            'set_headers' => [$hostHeader => 'custom'],
        ]);
    }

    public function testModifyRequestRejectsFalseyUriHostAndExplicitHostHeader(): void
    {
        $request = new Psr7\Request('GET', 'http://foo.com');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot modify request with both a URI containing a host and an explicit Host header.');

        Psr7\Utils::modifyRequest($request, [
            'uri' => new Psr7\Uri('http://0'),
            'set_headers' => ['host' => 'custom'],
        ]);
    }

    public function testModifyRequestCanSetExplicitHostHeaderWithoutUriChange(): void
    {
        $request = new Psr7\Request('GET', 'http://foo.com');

        $modified = Psr7\Utils::modifyRequest($request, [
            'set_headers' => ['host' => 'custom'],
        ]);

        self::assertSame('custom', $modified->getHeaderLine('Host'));
    }

    public function testModifyRequestCanSetExplicitHostHeaderWithRelativeUriChange(): void
    {
        $request = new Psr7\Request('GET', 'http://foo.com');

        $modified = Psr7\Utils::modifyRequest($request, [
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

        $modified = Psr7\Utils::modifyRequest($request, [
            'set_headers' => ['X-Test' => '1'],
        ]);

        self::assertSame('old', $modified->getHeaderLine('123'));
        self::assertSame('1', $modified->getHeaderLine('X-Test'));
    }

    public function testModifyRequestReplacesNumericHeaderNames(): void
    {
        $request = new Psr7\Request('GET', 'http://example.com', ['123' => 'old']);

        $modified = Psr7\Utils::modifyRequest($request, [
            'set_headers' => ['123' => 'new'],
        ]);

        self::assertSame('new', $modified->getHeaderLine('123'));
    }

    public function testModifyRequestRemovesNumericHeaderNames(): void
    {
        $request = new Psr7\Request('GET', 'http://example.com', ['123' => 'old']);

        $modified = Psr7\Utils::modifyRequest($request, [
            'remove_headers' => ['123'],
        ]);

        self::assertFalse($modified->hasHeader('123'));
    }

    public function testModifyServerRequestWithUploadedFiles(): void
    {
        $request = new Psr7\ServerRequest('GET', 'http://example.com/bla');
        $file = new Psr7\UploadedFile('Test', 100, \UPLOAD_ERR_OK);
        $request = $request->withUploadedFiles([$file]);

        /** @var Psr7\ServerRequest $modifiedRequest */
        $modifiedRequest = Psr7\Utils::modifyRequest($request, ['set_headers' => ['foo' => 'bar']]);

        self::assertCount(1, $modifiedRequest->getUploadedFiles());

        $files = $modifiedRequest->getUploadedFiles();
        self::assertInstanceOf('GuzzleHttp\Psr7\UploadedFile', $files[0]);
    }

    public function testModifyServerRequestWithCookies(): void
    {
        $request = (new Psr7\ServerRequest('GET', 'http://example.com/bla'))
            ->withCookieParams(['name' => 'value']);

        /** @var Psr7\ServerRequest $modifiedRequest */
        $modifiedRequest = Psr7\Utils::modifyRequest($request, ['set_headers' => ['foo' => 'bar']]);

        self::assertSame(['name' => 'value'], $modifiedRequest->getCookieParams());
    }

    public function testModifyServerRequestParsedBody(): void
    {
        $request = (new Psr7\ServerRequest('GET', 'http://example.com/bla'))
            ->withParsedBody(['name' => 'value']);

        /** @var Psr7\ServerRequest $modifiedRequest */
        $modifiedRequest = Psr7\Utils::modifyRequest($request, ['set_headers' => ['foo' => 'bar']]);

        self::assertSame(['name' => 'value'], $modifiedRequest->getParsedBody());
    }

    public function testModifyServerRequestQueryParams(): void
    {
        $request = (new Psr7\ServerRequest('GET', 'http://example.com/bla'))
            ->withQueryParams(['name' => 'value']);

        /** @var Psr7\ServerRequest $modifiedRequest */
        $modifiedRequest = Psr7\Utils::modifyRequest($request, ['set_headers' => ['foo' => 'bar']]);

        self::assertSame(['name' => 'value'], $modifiedRequest->getQueryParams());
    }

    public function testModifyServerRequestRetainsAttributes(): void
    {
        $request = (new Psr7\ServerRequest('GET', 'http://example.com/bla'))
            ->withAttribute('foo', 'bar');

        /** @var Psr7\ServerRequest $modifiedRequest */
        $modifiedRequest = Psr7\Utils::modifyRequest($request, ['set_headers' => ['baz' => 'qux']]);

        self::assertSame(['foo' => 'bar'], $modifiedRequest->getAttributes());
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

    /**
     * @return list<array{0: string[], 1: array, 2: array}>
     */
    public static function providesCaselessRemoveCases(): array
    {
        return [
            [
                ['foo-bar'],
                ['Foo-Bar' => 'hello'],
                [],
            ],
            [
                ['foo-bar'],
                ['hello'],
                ['hello'],
            ],
            [
                ['foo-Bar'],
                ['Foo-Bar' => 'hello', 123 => '', 'Foo-BAR' => 'hello123', 'foobar' => 'baz'],
                [123 => '', 'foobar' => 'baz'],
            ],
            [
                ['foo-Bar', 123],
                ['Foo-Bar' => 'hello', 123 => '', 'Foo-BAR' => 'hello123', 'foobar' => 'baz'],
                ['foobar' => 'baz'],
            ],
        ];
    }

    /**
     * @dataProvider providesCaselessRemoveCases
     *
     * @param string[] $keys
     */
    public function testCaselessRemove(array $keys, array $data, array $expected): void
    {
        self::assertSame($expected, Psr7\Utils::caselessRemove($keys, $data));
    }
}
