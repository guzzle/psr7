<?php

declare(strict_types=1);

namespace GuzzleHttp\Tests\Psr7;

use GuzzleHttp\Psr7\Exception\MalformedUriException;
use GuzzleHttp\Psr7\Idna;
use GuzzleHttp\Psr7\Uri;
use PHPUnit\Framework\TestCase;

/**
 * @covers \GuzzleHttp\Psr7\Idna
 */
class IdnaTest extends TestCase
{
    /**
     * @dataProvider asciiHostProvider
     */
    public function testToAsciiReturnsHostsWithoutNonAsciiDataUnchanged(string $host): void
    {
        self::assertSame($host, Idna::toAscii($host));
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function asciiHostProvider(): iterable
    {
        yield 'empty host' => [''];
        yield 'reg-name' => ['example.com'];
        yield 'uppercase reg-name' => ['EXAMPLE.com'];
        yield 'A-labels' => ['xn--d1acpjx3f.xn--p1ai'];
        yield 'underscore label' => ['_dmarc.example.com'];
        yield 'IPv6 literal' => ['[::1]'];
        yield 'percent-encoded ASCII octet' => ['ex%7Ample.com'];
    }

    /**
     * @requires extension intl
     *
     * @dataProvider internationalizedHostProvider
     */
    public function testToAsciiConvertsInternationalizedHosts(string $host, string $expected): void
    {
        self::assertSame($expected, Idna::toAscii($host));
    }

    /**
     * @return iterable<string, array{0: string, 1: string}>
     */
    public static function internationalizedHostProvider(): iterable
    {
        yield 'raw UTF-8' => ['яндекс.рф', 'xn--d1acpjx3f.xn--p1ai'];
        yield 'raw uppercase UTF-8' => ['ЯНДЕКС.РФ', 'xn--d1acpjx3f.xn--p1ai'];
        yield 'percent-encoded UTF-8' => ['%D1%8F%D0%BD%D0%B4%D0%B5%D0%BA%D1%81.%D1%80%D1%84', 'xn--d1acpjx3f.xn--p1ai'];
        yield 'lowercase percent-encoded UTF-8' => ['b%c3%bccher.example', 'xn--bcher-kva.example'];
        yield 'percent-encoded three-byte UTF-8' => ['%E9%A6%99%E6%B8%AF.example', 'xn--j6w193g.example'];
        yield 'raw and percent-encoded UTF-8' => ['яндекс.%D1%80%D1%84', 'xn--d1acpjx3f.xn--p1ai'];
        yield 'trailing dot' => ['яндекс.рф.', 'xn--d1acpjx3f.xn--p1ai.'];
        yield 'sharp s is not mapped to ss' => ['faß.de', 'xn--fa-hia.de'];
        yield 'decomposed letters' => [
            implode('.', array_fill(0, 4, str_repeat("e\u{0301}", 50))),
            implode('.', array_fill(0, 4, 'xn--9c'.str_repeat('a', 50))),
        ];
        yield 'ideographic full stop' => ['example。com', 'example.com'];
        yield 'fullwidth digits spelling an IPv4 address' => ['１２７.０.０.１', '127.0.0.1'];
        yield 'soft hyphens up to 1024 characters' => [str_repeat('%C2%AD', 1013).'example.com', 'example.com'];
    }

    /**
     * @requires extension intl
     *
     * @dataProvider unconvertibleHostProvider
     */
    public function testToAsciiRejectsHostsThatCannotBeConverted(string $host): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unable to convert host to ASCII');

        Idna::toAscii($host);
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function unconvertibleHostProvider(): iterable
    {
        yield 'raw invalid UTF-8' => ["a\xFFb.example"];
        yield 'raw lone continuation byte' => ["a\x80b.example"];
        yield 'percent-encoded invalid UTF-8' => ['a%FFb.example'];
        yield 'percent-encoded lone continuation byte' => ['a%80b.example'];
        yield 'percent-encoded overlong UTF-8' => ['a%C0%AFb.example'];
        yield 'fullwidth slash' => ['good.example／.evil.example'];
        yield 'fullwidth question mark' => ['evil.example？.good.example'];
        yield 'fullwidth number sign' => ['evil.example＃.good.example'];
        yield 'fullwidth at sign' => ['good.example＠evil.example'];
        yield 'fullwidth colon' => ['good.example：80'];
        yield 'fullwidth percent sign' => ['evil.example％2f.good.example'];
        yield 'account of sign' => ['evil.example℀.good.example'];
        yield 'soft hyphens beyond 1024 characters' => [str_repeat("\u{00AD}", 1014).'example.com'];
        yield 'zero width joiner' => ["exam\u{200D}ple.com"];
        yield 'C1 control' => ["a\u{0085}b.example"];
        yield 'bidi override' => ["a\u{202E}b.example"];
        yield 'mixed-direction label' => ['aא.example'];
        yield 'underscore beside an internationalized label' => ['my_bucket.яндекс.рф'];
        yield 'percent-encoded ASCII octet beside an internationalized label' => ['ex%7Ample.яндекс.рф'];
        yield 'non-ASCII data in an IP literal' => ['[::１]'];
        yield 'port beside an internationalized label' => ['яндекс.рф:80'];
    }

    /**
     * @requires extension intl
     */
    public function testToAsciiRejectsAnOverlongLabelWhenIntlThrowsExceptions(): void
    {
        $useExceptions = ini_set('intl.use_exceptions', '1');

        try {
            $this->expectException(\InvalidArgumentException::class);
            $this->expectExceptionMessage('Unable to convert host to ASCII');

            // Maps to 1200 characters, more than ICU encodes in one label.
            Idna::toAscii(str_repeat("\u{3316}", 200).'.example');
        } finally {
            ini_set('intl.use_exceptions', $useExceptions);
        }
    }

    /**
     * @requires extension intl
     */
    public function testToAsciiReportsTheConversionErrors(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unable to convert host to ASCII: -яндекс-.рф (IDNA_ERROR_LEADING_HYPHEN, IDNA_ERROR_TRAILING_HYPHEN)');

        Idna::toAscii('-яндекс-.рф');
    }

    /**
     * @requires extension intl
     */
    public function testToAsciiDiagnosticEscapesControlBytes(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unable to convert host to ASCII: яндекс.рф\\x0D\\x0AX-Injected: yes');

        Idna::toAscii("яндекс.рф\r\nX-Injected: yes");
    }

    /**
     * @requires extension intl
     */
    public function testToAsciiAppliesTheGivenOptions(): void
    {
        self::assertSame(
            'my_bucket.xn--d1acpjx3f.xn--p1ai',
            Idna::toAscii('my_bucket.яндекс.рф', \IDNA_NONTRANSITIONAL_TO_ASCII)
        );
    }

    /**
     * @requires extension intl
     *
     * @dataProvider invalidResultProvider
     */
    public function testToAsciiRejectsResultsThatAreNotAsciiHostsWhateverTheOptions(string $host): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unable to convert host to ASCII');

        Idna::toAscii($host, \IDNA_NONTRANSITIONAL_TO_ASCII);
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function invalidResultProvider(): iterable
    {
        yield 'fullwidth slash mapped to a path delimiter' => ['good.example／.evil.example'];
        yield 'fullwidth percent sign mapped to a percent-encoded octet' => ['ex％41mple.яндекс.рф'];
        yield 'fullwidth brackets mapped to an IP literal' => ['［::１］'];
    }

    /**
     * @requires extension intl
     */
    public function testUriForConvertsAnInternationalizedHost(): void
    {
        $uri = Idna::uriFor('https://юзер@яндекс.рф:8443/путь?q=1#f');

        self::assertSame('xn--d1acpjx3f.xn--p1ai', $uri->getHost());
        self::assertSame('https://%D1%8E%D0%B7%D0%B5%D1%80@xn--d1acpjx3f.xn--p1ai:8443/%D0%BF%D1%83%D1%82%D1%8C?q=1#f', (string) $uri);
    }

    /**
     * @requires extension intl
     */
    public function testUriForConvertsAPercentEncodedHost(): void
    {
        self::assertSame(
            'https://xn--d1acpjx3f.xn--p1ai/',
            (string) Idna::uriFor('https://%D1%8F%D0%BD%D0%B4%D0%B5%D0%BA%D1%81.%D1%80%D1%84/')
        );
    }

    /**
     * @requires extension intl
     */
    public function testUriForAppliesTheGivenOptions(): void
    {
        self::assertSame(
            'my_bucket.xn--d1acpjx3f.xn--p1ai',
            Idna::uriFor('https://my_bucket.яндекс.рф/', \IDNA_NONTRANSITIONAL_TO_ASCII)->getHost()
        );
    }

    /**
     * @dataProvider asciiHostUriProvider
     */
    public function testUriForMatchesTheConstructorWhenTheHostIsAscii(string $uri): void
    {
        self::assertSame((string) new Uri($uri), (string) Idna::uriFor($uri));
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function asciiHostUriProvider(): iterable
    {
        yield 'absolute URI' => ['https://user:pass@example.com:8443/path?q=1#f'];
        yield 'non-ASCII path and query' => ['https://example.com/путь?q=ä'];
        yield 'scheme without a host' => ['http:'];
        yield 'scheme without a host and a non-ASCII path' => ['http:/путь'];
        yield 'relative-path reference' => ['путь'];
        yield 'empty authority' => ['file:///tmp/файл'];
        yield 'IPv6 literal' => ['http://[::1]/путь'];
    }

    /**
     * @requires extension intl
     */
    public function testUriForDoesNotTriggerTheHostDeprecation(): void
    {
        $deprecations = [];
        set_error_handler(static function (int $errno, string $errstr) use (&$deprecations): bool {
            $deprecations[] = $errstr;

            return true;
        }, E_USER_DEPRECATED);

        try {
            Idna::uriFor('https://яндекс.рф/');
            Idna::uriFor('//яндекс.рф:8080/');
        } finally {
            restore_error_handler();
        }

        self::assertSame([], $deprecations);
    }

    public function testUriForRejectsAUriThatCannotBeParsed(): void
    {
        $this->expectException(MalformedUriException::class);
        $this->expectExceptionMessage('Unable to parse URI');

        Idna::uriFor('http://яндекс.рф:port/');
    }

    /**
     * @requires extension intl
     */
    public function testUriForRejectsAHostThatCannotBeConverted(): void
    {
        $this->expectException(MalformedUriException::class);
        $this->expectExceptionMessage('Unable to convert host to ASCII: -яндекс.рф (IDNA_ERROR_LEADING_HYPHEN)');

        Idna::uriFor('https://-яндекс.рф/');
    }
}
