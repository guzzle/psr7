<?php

declare(strict_types=1);

namespace GuzzleHttp\Tests\Psr7;

use GuzzleHttp\Psr7;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\UriInterface;

/**
 * @covers \GuzzleHttp\Psr7\UriRedactor
 */
class UriRedactorTest extends TestCase
{
    /**
     * @dataProvider redactUserInfoProvider
     */
    public function testRedactUserInfo(string $expected, string $uri): void
    {
        self::assertSame($expected, (string) Psr7\UriRedactor::redactUserInfo(new Psr7\Uri($uri)));
    }

    public static function redactUserInfoProvider(): iterable
    {
        yield 'username and password' => ['http://***@localhost/', 'http://my_user:secretPass@localhost/'];
        yield 'username only' => ['http://***@localhost/', 'http://ghp_TOKEN@localhost/'];
        yield 'empty password' => ['http://***@localhost/', 'http://user:@localhost/'];
        yield 'percent-encoded userinfo' => ['http://***@localhost/', 'http://us%40er:p%23ss@localhost/'];
        yield 'rest of the uri preserved' => ['https://***@example.com:8443/path?q=1#frag', 'https://user:pass@example.com:8443/path?q=1#frag'];
        yield 'ipv6 host' => ['http://***@[::1]:8080/', 'http://user:pass@[::1]:8080/'];
        yield 'already redacted' => ['http://***@localhost/', 'http://***@localhost/'];
        yield 'no userinfo' => ['http://localhost/', 'http://localhost/'];
    }

    public function testRedactUserInfoReturnsSameInstanceWithoutUserInfo(): void
    {
        $uri = new Psr7\Uri('http://localhost/');

        self::assertSame($uri, Psr7\UriRedactor::redactUserInfo($uri));
    }

    /**
     * @dataProvider redactUserInfoInStringProvider
     */
    public function testRedactUserInfoInString(string $expected, string $subject, string $uri): void
    {
        self::assertSame($expected, Psr7\UriRedactor::redactUserInfoInString($subject, $uri));
    }

    public static function redactUserInfoInStringProvider(): iterable
    {
        yield 'full uri embedded' => [
            "Failed to connect to 'http://***@localhost:8125'",
            "Failed to connect to 'http://my_user:secretPass@localhost:8125'",
            'http://my_user:secretPass@localhost:8125',
        ];
        yield 'embedded without scheme' => [
            'Could not resolve ***@localhost',
            'Could not resolve ghp_TOKEN@localhost',
            'http://ghp_TOKEN@localhost/',
        ];
        yield 'multiple occurrences' => [
            'via http://***@localhost and http://***@localhost',
            'via http://user:pass@localhost and http://user:pass@localhost',
            'http://user:pass@localhost',
        ];
        yield 'authority-form uri' => [
            "Unsupported proxy syntax in '***@localhost:8125'",
            "Unsupported proxy syntax in 'user:pass@localhost:8125'",
            'user:pass@localhost:8125',
        ];
        yield 'non-http scheme' => [
            'via socks5h://***@localhost:1080',
            'via socks5h://user:pass@localhost:1080',
            'socks5h://user:pass@localhost:1080',
        ];
        yield 'ipv6 host' => [
            'via http://***@[::1]:8080',
            'via http://user:pass@[::1]:8080',
            'http://user:pass@[::1]:8080',
        ];
        yield 'raw control bytes in credentials' => [
            'Failed to connect to http://***@localhost:8125',
            "Failed to connect to http://user:se\x01cr\x7Fet@localhost:8125",
            "http://user:se\x01cr\x7Fet@localhost:8125",
        ];
        yield 'raw at sign in credentials' => [
            'http://***@localhost',
            'http://user:p@ss@localhost',
            'http://user:p@ss@localhost',
        ];
        yield 'raw slash in credentials' => [
            "Unsupported proxy syntax in 'http://***@localhost:8125'",
            "Unsupported proxy syntax in 'http://user:se/cret@localhost:8125'",
            'http://user:se/cret@localhost:8125',
        ];
        yield 'raw question mark in credentials' => [
            "Unsupported proxy syntax in 'http://***@localhost:8125'",
            "Unsupported proxy syntax in 'http://user:se?cret@localhost:8125'",
            'http://user:se?cret@localhost:8125',
        ];
        yield 'raw hash in credentials' => [
            "Unsupported proxy syntax in 'http://***@localhost:8125'",
            "Unsupported proxy syntax in 'http://user:se#cret@localhost:8125'",
            'http://user:se#cret@localhost:8125',
        ];
        yield 'multiple raw at signs across a raw slash' => [
            "Unsupported proxy syntax in 'http://***@real.example'",
            "Unsupported proxy syntax in 'http://user:old@localhost:99999999/se:cret@real.example'",
            'http://user:old@localhost:99999999/se:cret@real.example',
        ];
        yield 'multiple raw at signs across a raw question mark' => [
            "Unsupported proxy syntax in 'http://***@real.example'",
            "Unsupported proxy syntax in 'http://user:old@localhost:99999999?se:cret@real.example'",
            'http://user:old@localhost:99999999?se:cret@real.example',
        ];
        yield 'multiple raw at signs across a raw hash' => [
            "Unsupported proxy syntax in 'http://***@real.example'",
            "Unsupported proxy syntax in 'http://user:old@localhost:99999999#se:cret@real.example'",
            'http://user:old@localhost:99999999#se:cret@real.example',
        ];
        yield 'multiple raw at signs in authority-form' => [
            "Unsupported proxy syntax in '***@real.example'",
            "Unsupported proxy syntax in 'user:old@localhost:99999999/se:cret@real.example'",
            'user:old@localhost:99999999/se:cret@real.example',
        ];
        yield 'at sign only in path' => [
            "Failed to connect to 'http://localhost:8125/health@check'",
            "Failed to connect to 'http://localhost:8125/health@check'",
            'http://localhost:8125/health@check',
        ];
        yield 'at sign only in query' => [
            'via http://localhost:8125?q=user@example.com',
            'via http://localhost:8125?q=user@example.com',
            'http://localhost:8125?q=user@example.com',
        ];
        yield 'at sign only in fragment' => [
            'via http://localhost:8125#frag@ment',
            'via http://localhost:8125#frag@ment',
            'http://localhost:8125#frag@ment',
        ];
        yield 'at sign only before the scheme' => [
            'via we@ird://host',
            'via we@ird://host',
            'we@ird://host',
        ];
        yield 'uri not embedded in subject' => [
            'Connection refused',
            'Connection refused',
            'http://user:pass@localhost',
        ];
        yield 'no userinfo' => ['error text', 'error text', 'http://localhost:8125'];
        yield 'empty uri' => ['error text', 'error text', ''];
        yield 'empty userinfo' => ['http://@localhost', 'http://@localhost', 'http://@localhost'];
    }

    /**
     * @dataProvider redactUriForMessageProvider
     */
    public function testRedactUriForMessage(string $expected, string $uri): void
    {
        self::assertSame($expected, Psr7\UriRedactor::redactUriForMessage(new Psr7\Uri($uri)));
    }

    public static function redactUriForMessageProvider(): iterable
    {
        yield 'credentials and sensitive components' => [
            'https://***@example.com:8443/path',
            'https://user:pass@example.com:8443/path?token=secret#private',
        ];
        yield 'username only' => [
            'https://***@0x7f000001/path',
            'https://token@0x7f000001/path?secret',
        ];
        yield 'no credentials' => [
            'https://example.com/path',
            'https://example.com/path?token=secret#private',
        ];
    }

    public function testRedactUriForMessageFallsBackWhenComponentAccessThrows(): void
    {
        $uri = $this->createMock(UriInterface::class);
        $uri->method('__toString')->willReturn('https://user:pass@example.com/path?token=secret');
        $uri->method('getUserInfo')->willThrowException(new \RuntimeException('failed'));

        self::assertSame('https://***@example.com/path', Psr7\UriRedactor::redactUriForMessage($uri));
    }

    /**
     * @dataProvider redactUriStringForMessageProvider
     */
    public function testRedactUriStringForMessage(string $expected, string $uri): void
    {
        self::assertSame($expected, Psr7\UriRedactor::redactUriStringForMessage($uri));
    }

    public static function redactUriStringForMessageProvider(): iterable
    {
        yield 'malformed port and credentials' => [
            'https://***@example.com:bad/path',
            'https://user:pass@example.com:bad/path?token=secret#private',
        ];
        yield 'relative reference' => ['/path', '/path?token=secret#private'];
        yield 'diagnostic control escaping' => ['http://[::1]\\x0A', "http://[::1]\n"];
    }
}
