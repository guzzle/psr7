<?php

declare(strict_types=1);

namespace GuzzleHttp\Psr7;

/**
 * @internal
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
     */
    public const CHAR_SUB_DELIMS = '!\$&\'\(\)\*\+,;=';

    /**
     * Unreserved characters for use in a regex.
     *
     * @see https://datatracker.ietf.org/doc/html/rfc3986#section-2.3
     */
    public const CHAR_UNRESERVED = 'a-zA-Z0-9_\-\.~';

    /**
     * The two hex digits of a percent-encoded octet (the "3A" in "%3A"), for use in a regex.
     *
     * @see https://datatracker.ietf.org/doc/html/rfc3986#section-2.1
     */
    public const HEX_OCTET = '[A-Fa-f0-9]{2}';

    public static function isValidScheme(string $scheme): bool
    {
        return $scheme === '' || preg_match('/^[A-Za-z][A-Za-z0-9.+-]*$/D', $scheme) === 1;
    }

    public static function isValidHost(string $host): bool
    {
        if ($host === '') {
            return true;
        }

        if (preg_match('/[\x00-\x20\x7F\/\?#@\\\\]/', $host)) {
            return false;
        }

        if (strpos($host, '[') !== false || strpos($host, ']') !== false) {
            return self::isValidIpLiteralHost($host);
        }

        return strpos($host, ':') === false;
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
