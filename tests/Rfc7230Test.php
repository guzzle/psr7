<?php

declare(strict_types=1);

namespace GuzzleHttp\Tests\Psr7;

use GuzzleHttp\Psr7\Rfc7230;
use PHPUnit\Framework\TestCase;

class Rfc7230Test extends TestCase
{
    /**
     * @dataProvider absoluteFormProvider
     */
    public function testIsAbsoluteFormRequestTarget(string $target, bool $expected): void
    {
        self::assertSame($expected, Rfc7230::isAbsoluteFormRequestTarget($target));
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
        self::assertSame($expected, Rfc7230::isAsteriskFormRequestTarget($method, $target));
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
        self::assertSame($expected, Rfc7230::isConnectAuthorityFormRequestTarget($method, $target));
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
}
