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
     * authority or path delimiter (`/ ? # @ \`), or an embedded colon denoting
     * a port.
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

        if (strpos($host, '[') !== false || strpos($host, ']') !== false) {
            return self::isValidIpLiteralHost($host);
        }

        return strpos($host, ':') === false;
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

    private static function isValidIpLiteralHost(string $host): bool
    {
        if ($host[0] !== '[' || substr($host, -1) !== ']') {
            return false;
        }

        $address = substr($host, 1, -1);
        if (\filter_var($address, \FILTER_VALIDATE_IP, \FILTER_FLAG_IPV6) !== false) {
            return true;
        }

        return preg_match('/^v[0-9a-f]+\.['.self::CHAR_UNRESERVED.self::CHAR_SUB_DELIMS.':]+$/iD', $address) === 1;
    }
}
