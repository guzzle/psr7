<?php

declare(strict_types=1);

namespace GuzzleHttp\Tests\Psr7;

use GuzzleHttp\Psr7\Rfc3986;
use PHPUnit\Framework\TestCase;

class Rfc3986Test extends TestCase
{
    /**
     * @dataProvider schemeProvider
     */
    public function testIsValidScheme(string $scheme, bool $expected): void
    {
        self::assertSame($expected, Rfc3986::isValidScheme($scheme));
    }

    /**
     * @return iterable<string, array{0: string, 1: bool}>
     */
    public static function schemeProvider(): iterable
    {
        // Accepted (RFC 3986 section 3.1, plus the empty-string extension).
        yield 'empty (lenient: a URI reference may omit the scheme)' => ['', true];
        yield 'single letter lowercase' => ['a', true];
        yield 'single letter uppercase' => ['Z', true];
        yield 'http' => ['http', true];
        yield 'mixed case' => ['HtTp', true];
        yield 'digit immediately after leader' => ['a1', true];
        yield 'scheme with plus' => ['git+ssh', true];
        yield 'scheme with hyphen' => ['view-source', true];
        yield 'scheme with dot' => ['soap.beep', true];
        yield 'all allowed trailing specials after letter' => ['a+-.', true];
        yield 'letters digits and specials' => ['A0Z9.+-', true];

        // Rejected: bad leader (must be a single ALPHA).
        yield 'leading digit' => ['1http', false];
        yield 'leading plus' => ['+http', false];
        yield 'leading hyphen' => ['-http', false];
        yield 'leading dot' => ['.http', false];
        yield 'only plus' => ['+', false];
        yield 'only dot' => ['.', false];

        // Rejected: characters outside the scheme grammar.
        yield 'underscore' => ['ht_tp', false];
        yield 'tilde' => ['ht~tp', false];
        yield 'percent sequence' => ['ht%20', false];
        yield 'embedded colon' => ['http:', false];
        yield 'embedded slash' => ['ht/tp', false];
        yield 'space inside' => ['ht tp', false];
        yield 'leading space' => [' http', false];
        yield 'trailing space' => ['http ', false];

        // Rejected: control bytes. The /D (PCRE_DOLLAR_ENDONLY) modifier is load-bearing:
        // without it PHP's $ tolerates a single trailing \n and would accept "http\n".
        yield 'trailing newline' => ["http\n", false];
        yield 'double trailing newline' => ["http\n\n", false];
        yield 'embedded newline' => ["ht\ntp", false];
        yield 'leading newline' => ["\nhttp", false];
        yield 'only newline' => ["\n", false];
        yield 'carriage return only' => ["\r", false];
        yield 'CRLF after scheme' => ["http\r\n", false];
        yield 'tab inside' => ["ht\ttp", false];
        yield 'vertical tab inside' => ["ht\x0Btp", false];
        yield 'form feed inside' => ["ht\x0Ctp", false];
        yield 'NUL byte only' => ["\x00", false];
        yield 'letter then NUL' => ["h\x00", false];
        yield 'DEL byte after letter' => ["h\x7F", false];

        // Rejected: non-ASCII / high bytes (pattern is byte-oriented, no /u modifier).
        yield 'standalone high byte leader' => ["\x80abc", false];
        yield 'utf8 e-acute in middle' => ["caf\xC3\xA9", false];
        yield 'leading utf8 e-acute' => ["\xC3\xA9http", false];
    }

    /**
     * @dataProvider hostProvider
     */
    public function testIsValidHost(string $host, bool $expected): void
    {
        self::assertSame($expected, Rfc3986::isValidHost($host));
    }

    /**
     * @return iterable<string, array{0: string, 1: bool}>
     */
    public static function hostProvider(): iterable
    {
        // Accepted non-bracketed hosts: reg-names and bare IPv4 addresses (RFC 3986 section 3.2.2).
        yield 'empty host' => ['', true];
        yield 'simple reg-name' => ['example.com', true];
        yield 'plain IPv4 address' => ['192.168.0.1', true];
        yield 'reg-name with all sub-delims' => ["a!\$&'()*+,;=b", true];
        yield 'reg-name with tilde and unreserved' => ['foo~bar', true];
        yield 'reg-name with underscore' => ['foo_bar', true];

        // Accepted reg-names that strict RFC 3986 reg-name would reject. Characterization
        // of the reg-name blocklist's leniency (not a behavior change).
        yield 'high byte 0x80 in reg-name (lenient)' => ["a\x80b", true];
        yield 'high byte 0xFF in reg-name (lenient)' => ["a\xFFb", true];
        yield 'raw UTF-8 e-acute in reg-name (lenient)' => ["h\xC3\xA9llo", true];
        yield 'non-blocklisted ASCII punctuation in reg-name (lenient)' => ['a<b>{c}|^`"', true];

        // Percent-encoding in reg-names: malformed sequences and octets that
        // decode to bytes the raw grammar rejects are invalid; other octets,
        // including non-ASCII UTF-8 data, remain accepted.
        yield 'unencoded percent sign in reg-name' => ['ex%ample', false];
        yield 'malformed percent-encoding in reg-name' => ['ex%zz', false];
        yield 'truncated percent-encoding at end of reg-name' => ['example.com%4', false];
        yield 'double percent before valid octet' => ['ex%%41mple', false];
        yield 'percent-encoded NUL in reg-name' => ['ex%00ample.com', false];
        yield 'percent-encoded LF in reg-name' => ['ex%0Aample.com', false];
        yield 'percent-encoded lowercase LF in reg-name' => ['ex%0aample.com', false];
        yield 'percent-encoded unit separator boundary in reg-name' => ['ex%1Fample.com', false];
        yield 'percent-encoded SP in reg-name' => ['ex%20ample.com', false];
        yield 'percent-encoded DEL in reg-name' => ['ex%7Fample.com', false];
        yield 'percent-encoded slash in reg-name' => ['ex%2Fample.com', false];
        yield 'percent-encoded lowercase slash in reg-name' => ['ex%2fample.com', false];
        yield 'percent-encoded question mark in reg-name' => ['ex%3Fample.com', false];
        yield 'percent-encoded hash in reg-name' => ['ex%23ample.com', false];
        yield 'percent-encoded at sign in reg-name' => ['ex%40ample.com', false];
        yield 'percent-encoded colon in reg-name' => ['ex%3Aample.com', false];
        yield 'percent-encoded open bracket in reg-name' => ['ex%5Bample.com', false];
        yield 'percent-encoded backslash in reg-name' => ['ex%5Cample.com', false];
        yield 'percent-encoded close bracket in reg-name' => ['ex%5Dample.com', false];
        yield 'percent-encoded percent in reg-name' => ['ex%25ample.com', false];
        yield 'percent-encoded unreserved letter in reg-name' => ['ex%61mple.com', true];
        yield 'percent-encoded sub-delim plus in reg-name' => ['ex%2Bample.com', true];
        yield 'percent-encoded exclamation boundary octet' => ['ex%21ample.com', true];
        yield 'percent-encoded tilde boundary octet' => ['ex%7Eample.com', true];
        yield 'percent-encoded UTF-8 pair in reg-name' => ['a%C3%A9b', true];
        yield 'percent-encoded lone high byte in reg-name' => ['ex%FFample.com', true];

        // Reg-name byte boundaries around the forbidden range [\x00-\x20\x7F].
        yield 'byte 0x21 boundary (bang, just above forbidden range)' => ["a\x21b", true];
        yield 'byte 0x7E boundary (tilde, just below DEL)' => ["a\x7Eb", true];
        yield 'byte 0x20 boundary (space, top of forbidden range)' => ["a\x20b", false];
        yield 'DEL 0x7F' => ["a\x7Fb", false];
        yield 'NUL byte' => ["a\x00b", false];
        yield 'tab control byte' => ["a\x09b", false];
        yield 'literal space character' => ['a b', false];
        yield 'trailing newline reg-name' => ["abc\n", false];

        // Reg-name delimiters in the forbidden class.
        yield 'embedded colon (port-like)' => ['example.com:80', false];
        yield 'forward slash delimiter' => ['a/b', false];
        yield 'question mark delimiter' => ['a?b', false];
        yield 'hash delimiter' => ['a#b', false];
        yield 'at-sign delimiter' => ['a@b', false];
        yield 'backslash' => ['a\\b', false];

        // IP-literal: IPv6.
        yield 'IPv6 literal lowercase' => ['[::1]', true];
        yield 'IPv6 literal uppercase' => ['[FE80::1]', true];
        yield 'IPv6 uncompressed' => ['[2001:db8:0:0:0:0:0:1]', true];
        yield 'IPv4-mapped IPv6 literal' => ['[::ffff:192.0.2.1]', true];
        yield 'IPv6 with general ls32 IPv4 form' => ['[2001:db8::192.168.0.1]', true];
        yield 'IPv6 with percent-encoded zone id' => ['[fe80::1%25eth0]', false];
        yield 'IPv6 with raw zone id' => ['[fe80::1%eth0]', false];
        yield 'IPv6 with trailing garbage inside brackets' => ['[::1extra]', false];

        // IP-literal: bracket framing / wrong shapes.
        yield 'unbracketed IPv6' => ['::1', false];
        yield 'bracketed IPv4 address' => ['[192.168.0.1]', false];
        yield 'bracketed reg-name' => ['[example]', false];
        yield 'only opening bracket' => ['[abc', false];
        yield 'only closing bracket' => ['abc]', false];
        yield 'bracket in middle (open)' => ['a[b', false];
        yield 'bracket in middle (close)' => ['a]b', false];
        yield 'wrong-order brackets' => [']abc[', false];
        yield 'double brackets' => ['[[::1]]', false];
        yield 'empty brackets' => ['[]', false];
        yield 'bracket with bad inner (space)' => ['[a b]', false];
        yield 'bracketed IPv6 with trailing port colon' => ['[fe80::1]:80', false];
        yield 'trailing garbage after valid bracket' => ['[::1]extra', false];

        // IP-literal: IPvFuture.
        yield 'IPvFuture basic' => ['[v1.foo]', true];
        yield 'IPvFuture version 0' => ['[v0.a]', true];
        yield 'IPvFuture long hex version' => ['[vdeadbeef.a]', true];
        yield 'IPvFuture mixed-case hex version' => ['[vAbC.foo]', true];
        yield 'IPvFuture with colon in suffix' => ['[v1.a:b]', true];
        yield 'IPvFuture with extra dot in suffix' => ['[v1..]', true];
        // "v" is a case-insensitive ABNF literal (RFC 2234 section 2.3), so "V" is also valid.
        yield 'IPvFuture uppercase V' => ['[V1.foo]', true];
        yield 'IPvFuture missing hex digits' => ['[v.foo]', false];
        yield 'IPvFuture missing dot/suffix' => ['[vF]', false];
        yield 'IPvFuture empty after dot' => ['[vF.]', false];
        yield 'IPvFuture non-hex version char' => ['[vg.foo]', false];
        yield 'IPvFuture with percent in suffix' => ['[v1.a%20b]', false];
        yield 'IPvFuture with embedded newline' => ["[v1.a\nb]", false];
        yield 'bracket containing just v' => ['[v]', false];
    }

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
        yield 'minimum non-zero' => ['1', true];
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
        yield 'internal space' => ['8 0', false];
        yield 'tab prefix' => ["\t80", false];
        yield 'newline prefix' => ["\n80", false];
        yield 'trailing newline' => ["65535\n", false];
        yield 'embedded NUL byte' => ["8\x000", false];
        yield 'lone NUL byte' => ["\x00", false];
        yield 'DEL byte' => ["\x7F", false];
        yield 'utf8 e-acute' => ["\xC3\xA9", false];
        yield 'arabic-indic digits' => ["\xD9\xA8\xD9\xA0", false];
        yield 'fullwidth digits' => ["\xEF\xBC\x91\xEF\xBC\x92\xEF\xBC\x93", false];
    }
}
