<?php

declare(strict_types=1);

namespace GuzzleHttp\Psr7;

/**
 * Syntax predicates for the URI grammar defined by RFC 3986.
 */
final class Rfc3986
{
    private function __construct()
    {
    }

    /**
     * Sub-delims for use in a regex.
     *
     * @see https://datatracker.ietf.org/doc/html/rfc3986#section-2.2
     *
     * @internal
     */
    public const CHAR_SUB_DELIMS = '!\$&\'\(\)\*\+,;=';

    /**
     * Unreserved characters for use in a regex.
     *
     * @see https://datatracker.ietf.org/doc/html/rfc3986#section-2.3
     *
     * @internal
     */
    public const CHAR_UNRESERVED = 'a-zA-Z0-9_\-\.~';

    /**
     * The two hex digits of a percent-encoded octet (the "3A" in "%3A"), for use in a regex.
     *
     * @see https://datatracker.ietf.org/doc/html/rfc3986#section-2.1
     *
     * @internal
     */
    public const HEX_OCTET = '[A-Fa-f0-9]{2}';

    /**
     * Whether the string is a valid URI scheme.
     *
     * Per RFC 3986 a scheme must start with a letter, followed by letters,
     * digits, `+`, `-`, or `.`. The empty string is also accepted, since a URI
     * reference may omit the scheme.
     *
     * @see https://datatracker.ietf.org/doc/html/rfc3986#section-3.1
     */
    public static function isValidScheme(string $scheme): bool
    {
        return $scheme === '' || preg_match('/^[A-Za-z][A-Za-z0-9.+-]*$/D', $scheme) === 1;
    }

    /**
     * Whether the string is a valid URI host.
     *
     * Per RFC 3986 the host is `IP-literal / IPv4address / reg-name`. An empty
     * host is accepted, since the authority (and thus the host) may be empty.
     * Bracketed values are validated as IPv6 / IPvFuture literals; any other
     * value is rejected if it contains control characters, whitespace, an
     * authority or path delimiter (`/ ? # @ \`), an embedded colon denoting
     * a port, a malformed percent-sequence, or a percent-encoded octet that
     * decodes to one of those rejected bytes, to a bracket, or to `%` itself.
     *
     * @see https://datatracker.ietf.org/doc/html/rfc3986#section-3.2.2
     */
    public static function isValidHost(string $host): bool
    {
        if ($host === '') {
            return true;
        }

        $invalidHost = preg_match('/[\x00-\x20\x7F\/\?#@\\\\]/', $host);

        if ($invalidHost === false) {
            return false;
        }

        if ($invalidHost === 1) {
            return false;
        }

        if (str_contains($host, '[') || str_contains($host, ']')) {
            return self::isValidIpLiteralHost($host);
        }

        if (str_contains($host, ':')) {
            return false;
        }

        return !str_contains($host, '%') || self::hasValidHostPercentEncoding($host);
    }

    /**
     * Whether the string is a valid port number (0-65535).
     *
     * RFC 3986 defines the port as `*DIGIT`, which also permits an empty port
     * and has no upper bound. This applies the stricter policy used throughout
     * the library instead: the value must be a non-empty run of digits (leading
     * zeros are accepted and normalized) that resolves to 0-65535.
     *
     * @see https://datatracker.ietf.org/doc/html/rfc3986#section-3.2.3
     */
    public static function isValidPort(string $port): bool
    {
        if ($port === '' || !ctype_digit($port)) {
            return false;
        }

        $normalized = ltrim($port, '0');
        if ($normalized === '') {
            return true;
        }

        return strlen($normalized) <= 5 && (int) $normalized <= 0xFFFF;
    }

    private static function hasValidHostPercentEncoding(string $host): bool
    {
        // Mirror of the raw reg-name policy above for percent-encoded octets:
        // reject malformed sequences (RFC 3986 requires "%" HEXDIG HEXDIG) and
        // octets that decode to bytes the raw grammar rejects - C0 controls,
        // SP, DEL, the delimiters / ? # @ \ [ ], the port colon, and % itself.
        // Octets decoding to any other byte (unreserved, sub-delims, and
        // non-ASCII UTF-8 data) remain accepted.
        $invalidEncoding = preg_match(
            '/%(?!'.self::HEX_OCTET.')|%(?:[01][0-9A-Fa-f]|2[035F]|3[AF]|40|5[BCD]|7F)/i',
            $host
        );

        return $invalidEncoding === 0;
    }

    private static function isValidIpLiteralHost(string $host): bool
    {
        if (!str_starts_with($host, '[') || !str_ends_with($host, ']')) {
            return false;
        }

        $address = substr($host, 1, -1);
        if (\filter_var($address, \FILTER_VALIDATE_IP, \FILTER_FLAG_IPV6) !== false) {
            return true;
        }

        // RFC 6874 IPv6 zone identifiers are intentionally not supported here.
        // Bracketed hosts are validated as IPv6 or IPvFuture only.
        return preg_match('/^v[0-9a-f]+\.['.self::CHAR_UNRESERVED.self::CHAR_SUB_DELIMS.':]+$/iD', $address) === 1;
    }
}
