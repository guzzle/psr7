<?php

declare(strict_types=1);

namespace GuzzleHttp\Psr7;

use Psr\Http\Message\UriInterface;

final class UriRedactor
{
    private function __construct()
    {
    }

    /**
     * Redact the user info part of a URI.
     *
     * Returns the URI with the whole userinfo component replaced by "***"
     * when one is present, so neither the username nor the password survives
     * into logs and diagnostics. A URI without userinfo is returned
     * unchanged.
     */
    public static function redactUserInfo(
        #[\SensitiveParameter]
        UriInterface $uri
    ): UriInterface {
        return $uri->getUserInfo() === '' ? $uri : $uri->withUserInfo('***');
    }

    /**
     * Redacts the userinfo of a raw URI string wherever it appears in a
     * subject string.
     *
     * The needle is taken verbatim from the raw URI rather than from parsed
     * components, so credentials that URI normalization would rewrite, such
     * as raw control bytes or unencoded reserved characters, are still found
     * in text that embeds the URI exactly as given, for example transport
     * error messages. A URI without "://" is treated as authority-form: a
     * host and port with optional userinfo.
     *
     * A URI that does not parse has no trustworthy authority boundary, so
     * everything between any scheme and its last "@" is redacted as a safe-side
     * fallback.
     *
     * @param string $subject Text that may embed the URI
     * @param string $uri     Raw URI whose userinfo is redacted in the text
     */
    public static function redactUserInfoInString(string $subject, string $uri): string
    {
        if (\strpos($uri, '@') === false) {
            return $subject;
        }

        $schemePosition = \strpos($uri, '://');
        $remainder = $schemePosition === false ? $uri : \substr($uri, $schemePosition + 3);

        if (\parse_url($schemePosition === false ? 'http://'.$uri : $uri) === false) {
            // Raw '/', '?', or '#' separators may sit inside the credentials
            // of a URI that defeats parse_url(), so the redaction cannot stop
            // at the apparent authority.
            $atPosition = \strrpos($remainder, '@');

            if ($atPosition === false || $atPosition === 0) {
                return $subject;
            }

            return \str_replace(\substr($remainder, 0, $atPosition).'@', '***@', $subject);
        }

        $authority = \substr($remainder, 0, \strcspn($remainder, '/?#'));
        $atPosition = \strrpos($authority, '@');

        if ($atPosition === false || $atPosition === 0) {
            // A parseable URI with '@' only past its authority, or with an
            // empty userinfo, carries no credentials to redact.
            return $subject;
        }

        return \str_replace(\substr($authority, 0, $atPosition).'@', '***@', $subject);
    }

    /**
     * Formats a URI for automatic diagnostics.
     *
     * The whole userinfo component is replaced by "***", and the query and
     * fragment are removed. The scheme, host, port, and path are preserved.
     * The returned string is diagnostic-escaped and the formatter never
     * throws.
     */
    public static function redactUriForMessage(
        #[\SensitiveParameter]
        UriInterface $uri
    ): string {
        try {
            $raw = (string) $uri;
        } catch (\Throwable $e) {
            return '[unavailable URI]';
        }

        try {
            if ($uri->getUserInfo() !== '') {
                $uri = $uri->withUserInfo('***');
            }

            return DiagnosticValue::escape((string) $uri->withQuery('')->withFragment(''));
        } catch (\Throwable $e) {
            return self::redactUriStringForMessage($raw);
        }
    }

    /**
     * Formats a raw URI for automatic diagnostics, including malformed input.
     *
     * The whole userinfo component is replaced by "***", and the query and
     * fragment are removed. The scheme, host, port, and path are preserved
     * where their boundaries can be determined safely. The returned string is
     * diagnostic-escaped and the formatter never throws.
     */
    public static function redactUriStringForMessage(
        #[\SensitiveParameter]
        string $uri
    ): string {
        try {
            $uri = self::redactUserInfoInString($uri, $uri);

            return DiagnosticValue::escape(\substr($uri, 0, \strcspn($uri, '?#')));
        } catch (\Throwable $e) {
            return '[unavailable URI]';
        }
    }
}
