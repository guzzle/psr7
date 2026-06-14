<?php

declare(strict_types=1);

namespace GuzzleHttp\Tests\Psr7;

use GuzzleHttp\Psr7\Rfc3986;
use PHPUnit\Framework\TestCase;

class Rfc3986Test extends TestCase
{
    /**
     * @dataProvider portProvider
     */
    public function testIsValidPort(string $port, bool $expected): void
    {
        self::assertSame($expected, Rfc3986::isValidPort($port));
    }

    /**
     * @return iterable<string, array{0: string, 1: bool}>
     */
    public static function portProvider(): iterable
    {
        yield 'minimum' => ['1', true];
        yield 'http' => ['80', true];
        yield 'https' => ['443', true];
        yield 'maximum' => ['65535', true];
        yield 'leading zero' => ['080', true];
        yield 'multiple leading zeros' => ['0080', true];
        yield 'all five digits used' => ['65000', true];

        yield 'zero' => ['0', true];
        yield 'zeros' => ['00', true];
        yield 'many zeros' => ['00000', true];
        yield 'padded zeros' => ['000000', true];
        yield 'empty' => ['', false];
        yield 'non-numeric' => ['abc', false];
        yield 'numeric suffix' => ['80x', false];
        yield 'numeric prefix' => ['x80', false];
        yield 'plus sign' => ['+80', false];
        yield 'minus sign' => ['-1', false];
        yield 'leading space' => [' 80', false];
        yield 'trailing space' => ['80 ', false];
        yield 'decimal' => ['80.5', false];
        yield 'just above range' => ['65536', false];
        yield 'leading zero, in range after trimming' => ['065535', true];
        yield 'leading zero, out of range after trimming' => ['065536', false];
        yield 'too many digits' => ['999999', false];
        yield 'far out of range' => ['4294967295', false];
    }
}
