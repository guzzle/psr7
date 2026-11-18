<?php

declare(strict_types=1);

namespace GuzzleHttp\Psr7;

use GuzzleHttp\Psr7\Exception\MalformedUriException;
use Psr\Http\Message\UriInterface;

/**
 * Converts internationalized hosts to their ASCII form.
 */
final class Idna
{
    private function __construct()
    {
    }

    /**
     * Returns the ASCII form of a URI host.
     *
     * A host with no non-ASCII data, raw or percent-encoded, is returned
     * unchanged. Any other host is converted to A-labels with the UTS #46
     * ToASCII operation, by default non-transitionally and with the STD3 ASCII
     * rules and the bidi and joiner checks. `$options` replaces that default
     * with `IDNA_*` constants. Whatever the options, a converted host must be a
     * valid ASCII host that is neither bracketed nor percent-encoded.
     *
     * Conversion maps characters, so fullwidth digits can become an IPv4
     * address. Apply any destination policy to the returned host, not to the
     * input.
     *
     * @throws \InvalidArgumentException If the host cannot be converted or
     *                                   decodes to more than 1024 characters.
     * @throws \RuntimeException         If the intl extension is not available.
     *
     * @see https://www.unicode.org/reports/tr46/#ToASCII
     */
    public static function toAscii(string $host, ?int $options = null): string
    {
        if (!self::hasNonAsciiData($host)) {
            return $host;
        }

        if (!function_exists('idn_to_ascii') || !defined('INTL_IDNA_VARIANT_UTS46')) {
            throw new \RuntimeException('Converting an internationalized host requires the intl extension');
        }

        $options ??= \IDNA_NONTRANSITIONAL_TO_ASCII | \IDNA_USE_STD3_RULES | \IDNA_CHECK_BIDI | \IDNA_CHECK_CONTEXTJ;

        $decoded = preg_replace_callback('/%[89A-Fa-f][0-9A-Fa-f]/', static function (array $match): string {
            return rawurldecode($match[0]);
        }, $host);
        if ($decoded === null) {
            throw new \RuntimeException('Unable to decode URI host percent-encoding: '.preg_last_error_msg());
        }

        $info = [];
        $ascii = false;
        $previous = null;

        // ICU can take quadratic time, and no valid name needs more characters.
        if (preg_match('/\A.{1,1024}\z/su', $decoded) === 1) {
            try {
                $ascii = idn_to_ascii($decoded, $options, \INTL_IDNA_VARIANT_UTS46, $info);
            } catch (\IntlException $e) {
                // With intl.use_exceptions, a label too long to encode throws.
                $previous = $e;
            }
        }

        if ($ascii === false || !self::isPlainAsciiHost($ascii)) {
            $errors = self::describeErrors($info['errors'] ?? 0);

            throw new \InvalidArgumentException(sprintf(
                'Unable to convert host to ASCII: %s%s',
                DiagnosticValue::escape($host),
                $errors === '' ? '' : ' ('.$errors.')'
            ), 0, $previous);
        }

        return $ascii;
    }

    /**
     * Returns a URI for a string whose host may be internationalized.
     *
     * The string is parsed exactly as `new Uri($uri)` parses it, except that a
     * host containing non-ASCII data is converted with `toAscii()` and the
     * given `$options`.
     *
     * @throws MalformedUriException If the URI cannot be parsed or its host
     *                               cannot be converted.
     * @throws \RuntimeException     If the intl extension is not available.
     */
    public static function uriFor(
        #[\SensitiveParameter]
        string $uri,
        ?int $options = null
    ): UriInterface {
        if (!self::hasNonAsciiData($uri)) {
            return new Uri($uri);
        }

        $parts = UriParser::parse($uri);
        if ($parts === false) {
            return new Uri($uri);
        }

        $host = $parts['host'] ?? null;
        if (!is_string($host) || !self::hasNonAsciiData($host)) {
            return new Uri($uri);
        }

        try {
            $parts['host'] = self::toAscii($host, $options);
        } catch (\InvalidArgumentException $e) {
            throw new MalformedUriException($e->getMessage(), 0, $e);
        }

        return Uri::fromParts($parts);
    }

    private static function hasNonAsciiData(string $value): bool
    {
        // An engine failure counts as non-ASCII so it cannot skip conversion.
        return preg_match('/[\x80-\xFF]|%[89A-Fa-f][0-9A-Fa-f]/', $value) !== 0;
    }

    private static function isPlainAsciiHost(string $host): bool
    {
        return preg_match('/\A[\x21-\x7E]++\z/', $host) === 1
            && strpbrk($host, '%[]') === false
            && Rfc3986::isValidHost($host);
    }

    /**
     * @param mixed $errors
     */
    private static function describeErrors($errors): string
    {
        $names = [];

        if (is_int($errors) && $errors !== 0) {
            foreach (get_defined_constants() as $name => $value) {
                if (str_starts_with($name, 'IDNA_ERROR_') && is_int($value) && ($errors & $value) !== 0) {
                    $names[] = $name;
                }
            }
        }

        return implode(', ', $names);
    }
}
