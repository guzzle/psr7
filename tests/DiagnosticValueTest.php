<?php

declare(strict_types=1);

namespace GuzzleHttp\Tests\Psr7;

use GuzzleHttp\Psr7\DiagnosticValue;
use PHPUnit\Framework\TestCase;

/**
 * @covers \GuzzleHttp\Psr7\DiagnosticValue
 */
class DiagnosticValueTest extends TestCase
{
    /**
     * @dataProvider valueProvider
     */
    public function testEscapesDiagnosticValues(string $value, string $expected): void
    {
        $escaped = DiagnosticValue::escape($value);

        self::assertSame($expected, $escaped);
        self::assertSame(1, \preg_match('//u', $escaped));
    }

    public static function valueProvider(): iterable
    {
        yield 'empty' => ['', ''];

        $printableAscii = '';
        for ($byte = 0x20; $byte <= 0x7E; ++$byte) {
            $printableAscii .= \chr($byte);
        }
        yield 'printable ASCII' => [$printableAscii, $printableAscii];
        yield 'printable UTF-8' => ["caf\xC3\xA9 \xE2\x98\x83", "caf\xC3\xA9 \xE2\x98\x83"];

        for ($codePoint = 0; $codePoint <= 0x1F; ++$codePoint) {
            yield \sprintf('C0 U+%04X', $codePoint) => [\chr($codePoint), \sprintf('\\x%02X', $codePoint)];
        }

        yield 'DEL U+007F' => ["\x7F", '\\x7F'];

        for ($codePoint = 0x80; $codePoint <= 0x9F; ++$codePoint) {
            yield \sprintf('C1 U+%04X', $codePoint) => ["\xC2".\chr($codePoint), \sprintf('\\x%02X', $codePoint)];
        }

        yield 'malformed continuation byte' => ["\x80", '\\x80'];
        yield 'malformed incomplete sequence' => ["\xE2\x98", '\\xE2\\x98'];
        yield 'malformed overlong sequence' => ["\xC0\xAF", '\\xC0\\xAF'];
        yield 'printable UTF-8 before malformed byte' => ["snowman \xE2\x98\x83\xFF", 'snowman \\xE2\\x98\\x83\\xFF'];
    }
}
