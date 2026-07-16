<?php

declare(strict_types=1);

namespace GuzzleHttp\Tests\Psr7;

use GuzzleHttp\Psr7\Rfc9110;
use PHPUnit\Framework\TestCase;

/**
 * @covers \GuzzleHttp\Psr7\Rfc9110
 */
class Rfc9110Test extends TestCase
{
    public function testIsTokenClassifiesEveryByte(): void
    {
        $tokenBytes = "!#$%&'*+-.^_`|~0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz";

        for ($byte = 0; $byte <= 0xFF; ++$byte) {
            $value = chr($byte);

            self::assertSame(
                strpos($tokenBytes, $value) !== false,
                Rfc9110::isToken($value),
                sprintf('Unexpected token result for byte 0x%02X', $byte)
            );
        }

        self::assertFalse(Rfc9110::isToken(''));
        self::assertTrue(Rfc9110::isToken('M-SEARCH'));
        self::assertFalse(Rfc9110::isToken("GET\n"));
    }

    public function testIsFieldValueClassifiesEveryByte(): void
    {
        for ($byte = 0; $byte <= 0xFF; ++$byte) {
            $value = chr($byte);
            $expected = $byte === 0x09 || ($byte >= 0x20 && $byte !== 0x7F);

            self::assertSame(
                $expected,
                Rfc9110::isFieldValue($value),
                sprintf('Unexpected field value result for byte 0x%02X', $byte)
            );
        }

        self::assertTrue(Rfc9110::isFieldValue(''));
        self::assertTrue(Rfc9110::isFieldValue("value \t\x80"));
        self::assertFalse(Rfc9110::isFieldValue("value\n"));
    }
}
