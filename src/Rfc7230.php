<?php

declare(strict_types=1);

namespace GuzzleHttp\Psr7;

/**
 * @internal
 */
final class Rfc7230
{
    private function __construct()
    {
    }

    /**
     * Header related regular expressions (based on amphp/http package)
     *
     * Note: header delimiter (\r\n) is modified to \r?\n to accept line feed only delimiters for BC reasons.
     *
     * @see https://github.com/amphp/http/blob/v1.0.1/src/Rfc7230.php#L12-L15
     *
     * @license https://github.com/amphp/http/blob/v1.0.1/LICENSE
     */
    public const HEADER_REGEX = "(^([^()<>@,;:\\\"/[\]?={}\x01-\x20\x7F]++):[ \t]*+((?:[ \t]*+[\x21-\x7E\x80-\xFF]++)*+)[ \t]*+\r?\n)m";
    public const HEADER_FOLD_REGEX = "(\r?\n[ \t]++)";

    /**
     * @return array{0: string, 1: int|null}|null
     */
    public static function parseHostHeader(string $authority): ?array
    {
        if ($authority === '') {
            return null;
        }

        $host = $authority;
        $port = null;

        if ($authority[0] === '[') {
            $closingBracket = strpos($authority, ']');
            if ($closingBracket === false) {
                return null;
            }

            $host = substr($authority, 0, $closingBracket + 1);
            $remainder = substr($authority, $closingBracket + 1);
            if ($remainder !== '') {
                if ($remainder[0] !== ':') {
                    return null;
                }

                $port = self::parsePort(substr($remainder, 1));
                if ($port === null) {
                    return null;
                }
            }
        } elseif (false !== ($colon = strpos($authority, ':'))) {
            $host = substr($authority, 0, $colon);
            $port = self::parsePort(substr($authority, $colon + 1));
            if ($port === null) {
                return null;
            }
        }

        if ($host === '' || !Rfc3986::isValidHost($host)) {
            return null;
        }

        return [$host, $port];
    }

    public static function isAbsoluteFormRequestTarget(string $target): bool
    {
        return preg_match('/^[A-Za-z][A-Za-z0-9+.-]*:\/\//D', $target) === 1;
    }

    public static function isAsteriskFormRequestTarget(string $method, string $target): bool
    {
        return $method === 'OPTIONS' && $target === '*';
    }

    public static function isConnectAuthorityFormRequestTarget(string $method, string $target): bool
    {
        return $method === 'CONNECT' && strpbrk($target, '/?#') === false;
    }

    public static function parsePort(string $port): ?int
    {
        if ($port === '' || !ctype_digit($port)) {
            return null;
        }

        $normalized = ltrim($port, '0');
        if ($normalized === '') {
            return null;
        }

        if (strlen($normalized) > 5 || (int) $normalized > 0xFFFF) {
            return null;
        }

        return (int) $normalized;
    }
}
