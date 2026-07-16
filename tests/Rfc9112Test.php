<?php

declare(strict_types=1);

namespace GuzzleHttp\Tests\Psr7;

use GuzzleHttp\Psr7\Rfc9112;
use PHPUnit\Framework\TestCase;

/**
 * @covers \GuzzleHttp\Psr7\Rfc9112
 */
class Rfc9112Test extends TestCase
{
    /**
     * @dataProvider protocolVersionProvider
     */
    public function testIsValidProtocolVersion(string $version, bool $expected): void
    {
        self::assertSame($expected, Rfc9112::isValidProtocolVersion($version));
    }

    /**
     * @return iterable<string, array{0: string, 1: bool}>
     */
    public static function protocolVersionProvider(): iterable
    {
        yield 'integer' => ['1', true];
        yield 'major and minor' => ['1.1', true];
        yield 'multiple digits' => ['100.200', true];
        yield 'empty' => ['', false];
        yield 'prefix' => ['HTTP/1.1', false];
        yield 'missing minor' => ['1.', false];
        yield 'missing major' => ['.1', false];
        yield 'multiple dots' => ['1.1.1', false];
        yield 'trailing newline' => ["1.1\n", false];
        yield 'high byte' => ["1.\xFF", false];
    }

    public function testIsValidRequestTargetClassifiesEveryByte(): void
    {
        for ($byte = 0; $byte <= 0xFF; ++$byte) {
            $value = chr($byte);
            $expected = $byte >= 0x21 && $byte !== 0x7F;

            self::assertSame(
                $expected,
                Rfc9112::isValidRequestTarget($value),
                sprintf('Unexpected request target result for byte 0x%02X', $byte)
            );
        }

        self::assertFalse(Rfc9112::isValidRequestTarget(''));
        self::assertTrue(Rfc9112::isValidRequestTarget('/path?query=value'));
        self::assertFalse(Rfc9112::isValidRequestTarget("/path\n"));
    }

    public function testIsValidReasonPhraseClassifiesEveryByte(): void
    {
        for ($byte = 0; $byte <= 0xFF; ++$byte) {
            $value = chr($byte);
            $expected = $byte === 0x09 || ($byte >= 0x20 && $byte !== 0x7F);

            self::assertSame(
                $expected,
                Rfc9112::isValidReasonPhrase($value),
                sprintf('Unexpected reason phrase result for byte 0x%02X', $byte)
            );
        }

        self::assertTrue(Rfc9112::isValidReasonPhrase(''));
        self::assertTrue(Rfc9112::isValidReasonPhrase("OK \t\x80"));
        self::assertFalse(Rfc9112::isValidReasonPhrase("OK\r\n"));
    }

    /**
     * @dataProvider absoluteFormProvider
     */
    public function testIsAbsoluteFormRequestTarget(string $target, bool $expected): void
    {
        self::assertSame($expected, Rfc9112::isAbsoluteFormRequestTarget($target));
    }

    /**
     * @return iterable<string, array{0: string, 1: bool}>
     */
    public static function absoluteFormProvider(): iterable
    {
        yield 'http' => ['http://example.com/path', true];
        yield 'https' => ['https://example.com', true];
        yield 'uppercase scheme' => ['HTTP://example.com', true];
        yield 'scheme with allowed characters' => ['a+b.c-d://example.com', true];
        yield 'origin-form' => ['/path', false];
        yield 'asterisk-form' => ['*', false];
        yield 'authority-form' => ['example.com:80', false];
        yield 'scheme not starting with a letter' => ['1http://example.com', false];
        yield 'missing scheme' => ['://example.com', false];
        yield 'empty' => ['', false];
    }

    /**
     * @dataProvider asteriskFormProvider
     */
    public function testIsAsteriskFormRequestTarget(string $method, string $target, bool $expected): void
    {
        self::assertSame($expected, Rfc9112::isAsteriskFormRequestTarget($method, $target));
    }

    /**
     * @return iterable<string, array{0: string, 1: string, 2: bool}>
     */
    public static function asteriskFormProvider(): iterable
    {
        yield 'options asterisk' => ['OPTIONS', '*', true];
        yield 'wrong method' => ['GET', '*', false];
        yield 'options non-asterisk' => ['OPTIONS', '/', false];
        yield 'options empty' => ['OPTIONS', '', false];
    }

    /**
     * @dataProvider connectAuthorityFormProvider
     */
    public function testIsConnectAuthorityFormRequestTarget(string $method, string $target, bool $expected): void
    {
        self::assertSame($expected, Rfc9112::isConnectAuthorityFormRequestTarget($method, $target));
    }

    /**
     * @return iterable<string, array{0: string, 1: string, 2: bool}>
     */
    public static function connectAuthorityFormProvider(): iterable
    {
        yield 'host and port' => ['CONNECT', 'example.com:443', true];
        yield 'ipv6 and port' => ['CONNECT', '[::1]:443', true];
        yield 'wrong method' => ['GET', 'example.com:443', false];
        yield 'contains path' => ['CONNECT', 'example.com/path', false];
        yield 'contains query' => ['CONNECT', 'example.com?a=b', false];
        yield 'contains fragment' => ['CONNECT', 'example.com#a', false];
    }

    /**
     * @dataProvider portProvider
     */
    public function testParsePort(string $port, ?int $expected): void
    {
        self::assertSame($expected, Rfc9112::parsePort($port));
    }

    /**
     * @return iterable<string, array{0: string, 1: int|null}>
     */
    public static function portProvider(): iterable
    {
        yield 'minimum' => ['1', 1];
        yield 'http' => ['80', 80];
        yield 'https' => ['443', 443];
        yield 'maximum' => ['65535', 65535];
        yield 'leading zero' => ['080', 80];
        yield 'multiple leading zeros' => ['0080', 80];
        yield 'leading zero, in range after trimming' => ['065535', 65535];
        yield 'zero' => ['0', null];
        yield 'zeros' => ['00', null];
        yield 'padded zeros' => ['000000', null];
        yield 'empty' => ['', null];
        yield 'non-numeric' => ['abc', null];
        yield 'numeric suffix' => ['80x', null];
        yield 'plus sign' => ['+80', null];
        yield 'out of range' => ['65536', null];
        yield 'leading zero, out of range after trimming' => ['065536', null];
        yield 'too many digits' => ['999999', null];
    }
}
