<?php

declare(strict_types=1);

namespace GuzzleHttp\Psr7;

use Psr\Http\Message\UriInterface;

/**
 * Provides methods to determine if a modified URI should be considered
 * cross-origin.
 *
 * @author Graham Campbell
 */
final class UriComparator
{
    /**
     * Determines if a modified URI should be considered cross-origin with
     * respect to an original URI.
     *
     * Two URIs are cross-origin when their scheme, host, or effective port
     * differ. Host comparison is case-insensitive, and missing ports use the
     * default port for `http` or `https`. Other schemes do not receive implicit
     * default ports.
     *
     * This helper only compares URI origins. It does not implement redirect
     * handling or credential policy.
     */
    public static function isCrossOrigin(UriInterface $original, UriInterface $modified): bool
    {
        if (\strcasecmp($original->getHost(), $modified->getHost()) !== 0) {
            return true;
        }

        if ($original->getScheme() !== $modified->getScheme()) {
            return true;
        }

        if (self::computePort($original) !== self::computePort($modified)) {
            return true;
        }

        return false;
    }

    private static function computePort(UriInterface $uri): ?int
    {
        $port = $uri->getPort();

        if (null !== $port) {
            return $port;
        }

        if ('http' === $uri->getScheme()) {
            return 80;
        }

        if ('https' === $uri->getScheme()) {
            return 443;
        }

        return null;
    }

    private function __construct()
    {
        // cannot be instantiated
    }
}
