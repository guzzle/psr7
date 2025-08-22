<?php

declare(strict_types=1);

namespace GuzzleHttp\Tests\Psr7;

use GuzzleHttp\Psr7;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\StreamInterface;

/**
 * @covers \GuzzleHttp\Psr7\MessageTrait
 * @covers \GuzzleHttp\Psr7\Response
 */
class ResponseTest extends TestCase
{
    public function testDefaultConstructor(): void
    {
        $r = new Response();
        self::assertSame(200, $r->getStatusCode());
        self::assertSame('1.1', $r->getProtocolVersion());
        self::assertSame('OK', $r->getReasonPhrase());
        self::assertSame([], $r->getHeaders());
        self::assertInstanceOf(StreamInterface::class, $r->getBody());
        self::assertSame('', (string) $r->getBody());
    }

    public function testCanConstructWithStatusCode(): void
    {
        $r = new Response(404);
        self::assertSame(404, $r->getStatusCode());
        self::assertSame('Not Found', $r->getReasonPhrase());
    }

    public function testConstructorDoesNotReadStreamBody(): void
    {
        $streamIsRead = false;
        $body = Psr7\FnStream::decorate(Psr7\Utils::streamFor(''), [
            '__toString' => function () use (&$streamIsRead) {
                $streamIsRead = true;

                return '';
            },
        ]);

        $r = new Response(200, [], $body);
        self::assertFalse($streamIsRead);
        self::assertSame($body, $r->getBody());
    }

    public function testStatusCanBeNumericString(): void
    {
        $r = (new Response())->withStatus('201');

        self::assertSame(201, $r->getStatusCode());
        self::assertSame('Created', $r->getReasonPhrase());
    }

    public function testCanConstructWithHeaders(): void
    {
        $r = new Response(200, ['Foo' => 'Bar']);
        self::assertSame(['Foo' => ['Bar']], $r->getHeaders());
        self::assertSame('Bar', $r->getHeaderLine('Foo'));
        self::assertSame(['Bar'], $r->getHeader('Foo'));
    }

    public function testCanConstructWithHeadersAsArray(): void
    {
        $r = new Response(200, [
            'Foo' => ['baz', 'bar'],
        ]);
        self::assertSame(['Foo' => ['baz', 'bar']], $r->getHeaders());
        self::assertSame('baz, bar', $r->getHeaderLine('Foo'));
        self::assertSame(['baz', 'bar'], $r->getHeader('Foo'));
    }

    public function testCanConstructWithBody(): void
    {
        $r = new Response(200, [], 'baz');
        self::assertInstanceOf(StreamInterface::class, $r->getBody());
        self::assertSame('baz', (string) $r->getBody());
    }

    public function testNullBody(): void
    {
        $r = new Response(200, [], null);
        self::assertInstanceOf(StreamInterface::class, $r->getBody());
        self::assertSame('', (string) $r->getBody());
    }

    public function testFalseyBody(): void
    {
        $r = new Response(200, [], '0');
        self::assertInstanceOf(StreamInterface::class, $r->getBody());
        self::assertSame('0', (string) $r->getBody());
    }

    public function testCanConstructWithReason(): void
    {
        $r = new Response(200, [], null, '1.1', 'bar');
        self::assertSame('bar', $r->getReasonPhrase());

        $r = new Response(200, [], null, '1.1', '0');
        self::assertSame('0', $r->getReasonPhrase(), 'Falsey reason works');
    }

    public function testCanConstructWithProtocolVersion(): void
    {
        $r = new Response(200, [], null, '1000');
        self::assertSame('1000', $r->getProtocolVersion());
    }

    public function testWithStatusCodeAndNoReason(): void
    {
        $r = (new Response())->withStatus(201);
        self::assertSame(201, $r->getStatusCode());
        self::assertSame('Created', $r->getReasonPhrase());
    }

    public function testWithStatusCodeAndReason(): void
    {
        $r = (new Response())->withStatus(201, 'Foo');
        self::assertSame(201, $r->getStatusCode());
        self::assertSame('Foo', $r->getReasonPhrase());

        $r = (new Response())->withStatus(201, '0');
        self::assertSame(201, $r->getStatusCode());
        self::assertSame('0', $r->getReasonPhrase(), 'Falsey reason works');
    }

    public function testWithProtocolVersion(): void
    {
        $r = (new Response())->withProtocolVersion('1000');
        self::assertSame('1000', $r->getProtocolVersion());
    }

    public function testSameInstanceWhenSameProtocol(): void
    {
        $r = new Response();
        self::assertSame($r, $r->withProtocolVersion('1.1'));
    }

    public function testWithBody(): void
    {
        $b = Psr7\Utils::streamFor('0');
        $r = (new Response())->withBody($b);
        self::assertInstanceOf(StreamInterface::class, $r->getBody());
        self::assertSame('0', (string) $r->getBody());
    }

    public function testSameInstanceWhenSameBody(): void
    {
        $r = new Response();
        $b = $r->getBody();
        self::assertSame($r, $r->withBody($b));
    }

    public function testWithHeader(): void
    {
        $r = new Response(200, ['Foo' => 'Bar']);
        $r2 = $r->withHeader('baZ', 'Bam');
        self::assertSame(['Foo' => ['Bar']], $r->getHeaders());
        self::assertSame(['Foo' => ['Bar'], 'baZ' => ['Bam']], $r2->getHeaders());
        self::assertSame('Bam', $r2->getHeaderLine('baz'));
        self::assertSame(['Bam'], $r2->getHeader('baz'));
    }

    public function testNumericHeaderValue(): void
    {
        $r = (new Response())->withHeader('Api-Version', 1);
        self::assertSame(['Api-Version' => ['1']], $r->getHeaders());
    }

    public function testWithHeaderAsArray(): void
    {
        $r = new Response(200, ['Foo' => 'Bar']);
        $r2 = $r->withHeader('baZ', ['Bam', 'Bar']);
        self::assertSame(['Foo' => ['Bar']], $r->getHeaders());
        self::assertSame(['Foo' => ['Bar'], 'baZ' => ['Bam', 'Bar']], $r2->getHeaders());
        self::assertSame('Bam, Bar', $r2->getHeaderLine('baz'));
        self::assertSame(['Bam', 'Bar'], $r2->getHeader('baz'));
    }

    public function testWithHeaderReplacesDifferentCase(): void
    {
        $r = new Response(200, ['Foo' => 'Bar']);
        $r2 = $r->withHeader('foO', 'Bam');
        self::assertSame(['Foo' => ['Bar']], $r->getHeaders());
        self::assertSame(['foO' => ['Bam']], $r2->getHeaders());
        self::assertSame('Bam', $r2->getHeaderLine('foo'));
        self::assertSame(['Bam'], $r2->getHeader('foo'));
    }

    public function testWithAddedHeader(): void
    {
        $r = new Response(200, ['Foo' => 'Bar']);
        $r2 = $r->withAddedHeader('foO', 'Baz');
        self::assertSame(['Foo' => ['Bar']], $r->getHeaders());
        self::assertSame(['Foo' => ['Bar', 'Baz']], $r2->getHeaders());
        self::assertSame('Bar, Baz', $r2->getHeaderLine('foo'));
        self::assertSame(['Bar', 'Baz'], $r2->getHeader('foo'));
    }

    public function testWithAddedHeaderAsArray(): void
    {
        $r = new Response(200, ['Foo' => 'Bar']);
        $r2 = $r->withAddedHeader('foO', ['Baz', 'Bam']);
        self::assertSame(['Foo' => ['Bar']], $r->getHeaders());
        self::assertSame(['Foo' => ['Bar', 'Baz', 'Bam']], $r2->getHeaders());
        self::assertSame('Bar, Baz, Bam', $r2->getHeaderLine('foo'));
        self::assertSame(['Bar', 'Baz', 'Bam'], $r2->getHeader('foo'));
    }

    public function testWithAddedHeaderThatDoesNotExist(): void
    {
        $r = new Response(200, ['Foo' => 'Bar']);
        $r2 = $r->withAddedHeader('nEw', 'Baz');
        self::assertSame(['Foo' => ['Bar']], $r->getHeaders());
        self::assertSame(['Foo' => ['Bar'], 'nEw' => ['Baz']], $r2->getHeaders());
        self::assertSame('Baz', $r2->getHeaderLine('new'));
        self::assertSame(['Baz'], $r2->getHeader('new'));
    }

    public function testWithoutHeaderThatExists(): void
    {
        $r = new Response(200, ['Foo' => 'Bar', 'Baz' => 'Bam']);
        $r2 = $r->withoutHeader('foO');
        self::assertTrue($r->hasHeader('foo'));
        self::assertSame(['Foo' => ['Bar'], 'Baz' => ['Bam']], $r->getHeaders());
        self::assertFalse($r2->hasHeader('foo'));
        self::assertSame(['Baz' => ['Bam']], $r2->getHeaders());
    }

    public function testWithoutHeaderThatDoesNotExist(): void
    {
        $r = new Response(200, ['Baz' => 'Bam']);
        $r2 = $r->withoutHeader('foO');
        self::assertSame($r, $r2);
        self::assertFalse($r2->hasHeader('foo'));
        self::assertSame(['Baz' => ['Bam']], $r2->getHeaders());
    }

    public function testSameInstanceWhenRemovingMissingHeader(): void
    {
        $r = new Response();
        self::assertSame($r, $r->withoutHeader('foo'));
    }

    public function testPassNumericHeaderNameInConstructor(): void
    {
        $r = new Response(200, ['Location' => 'foo', '123' => 'bar']);
        self::assertSame('bar', $r->getHeaderLine('123'));
    }

    public function testConstructResponseEmptyListHeaderValue(): void
    {
        $r = new Response(200, ['Foo' => []]);
        self::assertSame('', $r->getHeaderLine('Foo'));
        self::assertSame([], $r->getHeader('Foo'));
        self::assertSame(['Foo' => []], $r->getHeaders());
    }

    /**
     * @dataProvider invalidHeaderProvider
     */
    public function testConstructResponseInvalidHeader($header, $headerValue, $expectedMessage): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage($expectedMessage);
        new Response(200, [$header => $headerValue]);
    }

    public static function invalidHeaderProvider(): iterable
    {
        return [
            ['', '', '"" is not valid header name'],
            ['foo', new \stdClass(),  'Header value must be scalar or null but stdClass provided.'],
        ];
    }

    /**
     * @dataProvider invalidWithHeaderProvider
     */
    public function testWithInvalidHeader($header, $headerValue, $expectedMessage): void
    {
        $r = new Response();
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage($expectedMessage);
        $r->withHeader($header, $headerValue);
    }

    public static function invalidWithHeaderProvider(): iterable
    {
        yield from self::invalidHeaderProvider();
        yield [[], 'foo', 'Header name must be a string but array provided.'];
        yield [false, 'foo', 'Header name must be a string but boolean provided.'];
        yield [new \stdClass(), 'foo', 'Header name must be a string but stdClass provided.'];
        yield ['', 'foo', '"" is not valid header name.'];
        yield ["Content-Type\r\n\r\n", 'foo', "\"Content-Type\r\n\r\n\" is not valid header name."];
        yield ["Content-Type\r\n", 'foo', "\"Content-Type\r\n\" is not valid header name."];
        yield ["Content-Type\n", 'foo', "\"Content-Type\n\" is not valid header name."];
        yield ["\r\nContent-Type", 'foo', "\"\r\nContent-Type\" is not valid header name."];
        yield ["\nContent-Type", 'foo', "\"\nContent-Type\" is not valid header name."];
        yield ["\n", 'foo', "\"\n\" is not valid header name."];
        yield ["\r\n", 'foo', "\"\r\n\" is not valid header name."];
        yield ["\t", 'foo', "\"\t\" is not valid header name."];
    }

    public function testHeaderValuesAreTrimmed(): void
    {
        $r1 = new Response(200, ['OWS' => " \t \tFoo\t \t "]);
        $r2 = (new Response())->withHeader('OWS', " \t \tFoo\t \t ");
        $r3 = (new Response())->withAddedHeader('OWS', " \t \tFoo\t \t ");

        foreach ([$r1, $r2, $r3] as $r) {
            self::assertSame(['OWS' => ['Foo']], $r->getHeaders());
            self::assertSame('Foo', $r->getHeaderLine('OWS'));
            self::assertSame(['Foo'], $r->getHeader('OWS'));
        }
    }

    public function testWithAddedHeaderArrayValueAndKeys(): void
    {
        $message = (new Response())->withAddedHeader('list', ['foo' => 'one']);
        $message = $message->withAddedHeader('list', ['foo' => 'two', 'bar' => 'three']);

        $headerLine = $message->getHeaderLine('list');
        self::assertSame('one, two, three', $headerLine);
    }

    /**
     * @dataProvider nonIntegerStatusCodeProvider
     *
     * @param mixed $invalidValues
     */
    public function testConstructResponseWithNonIntegerStatusCode($invalidValues): void
    {
        $this->expectException(\TypeError::class);
        new Response($invalidValues);
    }

    /**
     * @dataProvider nonIntegerStatusCodeProvider
     *
     * @param mixed $invalidValues
     */
    public function testResponseChangeStatusCodeWithNonInteger($invalidValues): void
    {
        $response = new Response();
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Status code must be an integer value.');
        $response->withStatus($invalidValues);
    }

    public static function nonIntegerStatusCodeProvider(): iterable
    {
        return [
            ['whatever'],
            ['1.01'],
            [1.01],
            [new \stdClass()],
        ];
    }

    /**
     * @dataProvider invalidStatusCodeRangeProvider
     *
     * @param mixed $invalidValues
     */
    public function testConstructResponseWithInvalidRangeStatusCode($invalidValues): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Status code must be an integer value between 1xx and 5xx.');
        new Response($invalidValues);
    }

    /**
     * @dataProvider invalidStatusCodeRangeProvider
     *
     * @param mixed $invalidValues
     */
    public function testResponseChangeStatusCodeWithWithInvalidRange($invalidValues): void
    {
        $response = new Response();
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Status code must be an integer value between 1xx and 5xx.');
        $response->withStatus($invalidValues);
    }

    public static function invalidStatusCodeRangeProvider(): iterable
    {
        return [
            [600],
            [99],
        ];
    }
}
