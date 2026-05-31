<?php

declare(strict_types=1);

namespace GuzzleHttp\Psr7;

use Psr\Http\Message\MessageInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;

final class Message
{
    private const DEFAULT_BODY_SUMMARY_TRUNCATE_AT = 120;

    private function __construct()
    {
    }

    /**
     * Returns the string representation of an HTTP message.
     *
     * @param MessageInterface $message Message to convert to a string.
     */
    public static function toString(MessageInterface $message): string
    {
        if ($message instanceof RequestInterface) {
            $msg = trim($message->getMethod().' '
                    .$message->getRequestTarget())
                .' HTTP/'.$message->getProtocolVersion();
            if (!$message->hasHeader('host')) {
                $msg .= "\r\nHost: ".self::hostHeaderFromUri($message->getUri());
            }
        } elseif ($message instanceof ResponseInterface) {
            $msg = 'HTTP/'.$message->getProtocolVersion().' '
                .$message->getStatusCode().' '
                .$message->getReasonPhrase();
        } else {
            throw new \InvalidArgumentException('Unknown message type');
        }

        foreach ($message->getHeaders() as $name => $values) {
            if (is_string($name) && strtolower($name) === 'set-cookie') {
                foreach ($values as $value) {
                    $msg .= "\r\n{$name}: ".$value;
                }
            } else {
                $msg .= "\r\n{$name}: ".implode(', ', $values);
            }
        }

        return "{$msg}\r\n\r\n".$message->getBody();
    }

    private static function hostHeaderFromUri(UriInterface $uri): string
    {
        $host = $uri->getHost();

        if ($host === '') {
            return '';
        }

        if (($port = $uri->getPort()) !== null) {
            $host .= ':'.$port;
        }

        return $host;
    }

    /**
     * Get a short summary of the message body.
     *
     * Will return `null` if the response is not printable.
     *
     * @param MessageInterface $message    The message to get the body summary
     * @param int|null         $truncateAt The maximum allowed size of the summary
     */
    public static function bodySummary(MessageInterface $message, ?int $truncateAt = null): ?string
    {
        $truncateAt ??= self::DEFAULT_BODY_SUMMARY_TRUNCATE_AT;

        $body = $message->getBody();

        if (!$body->isSeekable() || !$body->isReadable()) {
            return null;
        }

        $size = $body->getSize();

        if ($size === 0) {
            return null;
        }

        $position = $body->tell();

        try {
            $body->rewind();
            $summary = $body->read($truncateAt);

            if ($size > $truncateAt) {
                if (preg_match('//u', $summary) !== 1) {
                    $summary = self::trimTrailingIncompleteUtf8Character($summary, $body->read(3));
                }

                $summary .= ' (truncated...)';
            }
        } finally {
            $body->seek($position);
        }

        // Matches any printable character, including unicode characters:
        // letters, marks, numbers, punctuation, spacing, and separators.
        if (preg_match('/[^\pL\pM\pN\pP\pS\pZ\n\r\t]/u', $summary) !== 0) {
            return null;
        }

        return $summary;
    }

    /**
     * Trims a partial UTF-8 character from the end of a truncated string.
     */
    private static function trimTrailingIncompleteUtf8Character(string $summary, string $lookahead): string
    {
        $length = strlen($summary);

        if ($length === 0) {
            return $summary;
        }

        $start = $length - 1;

        while ($start >= 0) {
            $byte = ord($summary[$start]);

            if ($byte < 0x80 || $byte > 0xBF) {
                break;
            }

            --$start;
        }

        if ($start < 0) {
            return $summary;
        }

        $lead = ord($summary[$start]);

        if ($lead >= 0xC2 && $lead <= 0xDF) {
            $expectedLength = 2;
        } elseif ($lead >= 0xE0 && $lead <= 0xEF) {
            $expectedLength = 3;
        } elseif ($lead >= 0xF0 && $lead <= 0xF4) {
            $expectedLength = 4;
        } else {
            return $summary;
        }

        $availableLength = $length - $start;

        if ($availableLength >= $expectedLength) {
            return $summary;
        }

        $sequence = substr($summary, $start).substr($lookahead, 0, $expectedLength - $availableLength);

        if (strlen($sequence) !== $expectedLength || preg_match('//u', $sequence) !== 1) {
            return $summary;
        }

        return substr($summary, 0, $start);
    }

    /**
     * Attempts to rewind a message body and throws an exception on failure.
     *
     * The body of the message will only be rewound if a call to `tell()`
     * returns a value other than `0`.
     *
     * @param MessageInterface $message Message to rewind
     *
     * @throws \RuntimeException
     */
    public static function rewindBody(MessageInterface $message): void
    {
        $body = $message->getBody();

        if ($body->tell()) {
            $body->rewind();
        }
    }

    /**
     * Parses an HTTP message into an associative array.
     *
     * The array contains the "start-line" key containing the start line of
     * the message, "headers" key containing an associative array of header
     * array values, and a "body" key containing the body of the message.
     *
     * @param string $message HTTP request or response to parse.
     */
    public static function parseMessage(string $message): array
    {
        if (!$message) {
            throw new \InvalidArgumentException('Invalid message');
        }

        $message = ltrim($message, "\r\n");

        $messageParts = preg_split("/\r?\n\r?\n/", $message, 2);

        if ($messageParts === false || count($messageParts) !== 2) {
            throw new \InvalidArgumentException('Invalid message: Missing header delimiter');
        }

        [$rawHeaders, $body] = $messageParts;
        $rawHeaders .= "\r\n"; // Put back the delimiter we split previously
        $headerParts = preg_split("/\r?\n/", $rawHeaders, 2);

        if ($headerParts === false || count($headerParts) !== 2) {
            throw new \InvalidArgumentException('Invalid message: Missing status line');
        }

        [$startLine, $rawHeaders] = $headerParts;

        if (preg_match("/(?:^HTTP\/|^[A-Z]+ \S+ HTTP\/)(\d+(?:\.\d+)?)/i", $startLine, $matches) && $matches[1] === '1.0') {
            // Header folding is deprecated for HTTP/1.1, but allowed in HTTP/1.0
            $rawHeaders = preg_replace(Rfc7230::HEADER_FOLD_REGEX, ' ', $rawHeaders);
        }

        /** @var array[] $headerLines */
        $count = preg_match_all(Rfc7230::HEADER_REGEX, $rawHeaders, $headerLines, PREG_SET_ORDER);

        // If these aren't the same, then one line didn't match and there's an invalid header.
        if ($count !== substr_count($rawHeaders, "\n")) {
            // Folding is deprecated, see https://datatracker.ietf.org/doc/html/rfc7230#section-3.2.4
            if (preg_match(Rfc7230::HEADER_FOLD_REGEX, $rawHeaders)) {
                throw new \InvalidArgumentException('Invalid header syntax: Obsolete line folding');
            }

            throw new \InvalidArgumentException('Invalid header syntax');
        }

        $headers = [];

        foreach ($headerLines as $headerLine) {
            $headers[$headerLine[1]][] = $headerLine[2];
        }

        return [
            'start-line' => $startLine,
            'headers' => $headers,
            'body' => $body,
        ];
    }

    /**
     * Constructs a URI for an HTTP request message.
     *
     * @param string $path    Path from the start-line
     * @param array  $headers Array of headers (each value an array).
     */
    public static function parseRequestUri(string $path, array $headers): string
    {
        $host = self::getHostFromHeaders($headers);

        // If no host is found, then a full URI cannot be constructed.
        if ($host === null) {
            return $path;
        }

        [$authorityHost, $port] = self::parseHostHeaderAuthority($host);
        $scheme = $port === 443 ? 'https' : 'http';

        return $scheme.'://'.self::composeAuthority($authorityHost, $port).'/'.ltrim($path, '/');
    }

    /**
     * @return array{0: string, 1: int|null}
     */
    private static function parseHostHeaderAuthority(string $authority): array
    {
        $parsed = Rfc7230::parseHostHeader($authority);
        if ($parsed === null) {
            throw new \InvalidArgumentException('Invalid request string');
        }

        return $parsed;
    }

    private static function composeAuthority(string $host, ?int $port): string
    {
        return $host.($port !== null ? ':'.$port : '');
    }

    /**
     * @param array $headers Array of headers (each value an array).
     */
    private static function getHostFromHeaders(array $headers): ?string
    {
        $host = self::getSingleHostHeader($headers);
        if ($host === null) {
            return null;
        }

        self::parseHostHeaderAuthority($host);

        return $host;
    }

    /**
     * @param array $headers Array of headers (each value an array).
     */
    private static function getSingleHostHeader(array $headers): ?string
    {
        $host = null;
        $found = false;

        foreach ($headers as $name => $values) {
            if (strtolower((string) $name) !== 'host') {
                continue;
            }

            if ($found || !is_array($values) || count($values) !== 1) {
                throw new \InvalidArgumentException('Invalid request string');
            }

            $found = true;
            $host = reset($values);
        }

        if (!$found) {
            return null;
        }

        if (!is_string($host)) {
            throw new \InvalidArgumentException('Invalid request string');
        }

        return $host;
    }

    /**
     * @param array $headers Array of headers (each value an array).
     */
    private static function parseRequestAuthorityUri(array $headers): string
    {
        $host = self::getHostFromHeaders($headers);
        if ($host === null) {
            return '';
        }

        [$authorityHost, $port] = self::parseHostHeaderAuthority($host);
        $scheme = $port === 443 ? 'https' : 'http';

        return $scheme.'://'.self::composeAuthority($authorityHost, $port);
    }

    /**
     * Parses a request message string into a request object.
     *
     * @param string $message Request message string.
     */
    public static function parseRequest(string $message): RequestInterface
    {
        $data = self::parseMessage($message);
        $matches = [];
        if (!preg_match('/^(?P<method>[!#$%&\'*+.^_`|~0-9A-Za-z-]+) (?P<target>[^\x00-\x20\x7F]+) HTTP\/(?P<version>\d+(?:\.\d+)?)$/D', $data['start-line'], $matches)) {
            throw new \InvalidArgumentException('Invalid request string');
        }

        self::getHostFromHeaders($data['headers']);

        if ($matches['target'][0] === '/') {
            return new Request(
                $matches['method'],
                self::parseRequestUri($matches['target'], $data['headers']),
                $data['headers'],
                $data['body'],
                $matches['version']
            );
        }

        if (Rfc7230::isAbsoluteFormRequestTarget($matches['target'])) {
            return (new Request(
                $matches['method'],
                $matches['target'],
                $data['headers'],
                $data['body'],
                $matches['version']
            ))->withRequestTarget($matches['target']);
        }

        if (Rfc7230::isAsteriskFormRequestTarget($matches['method'], $matches['target'])) {
            return (new Request(
                $matches['method'],
                self::parseRequestAuthorityUri($data['headers']),
                $data['headers'],
                $data['body'],
                $matches['version']
            ))->withRequestTarget($matches['target']);
        }

        $connectUri = self::parseConnectAuthorityFormRequestTarget($matches['method'], $matches['target']);
        if ($connectUri !== null) {
            return (new Request(
                $matches['method'],
                $connectUri,
                $data['headers'],
                $data['body'],
                $matches['version']
            ))->withRequestTarget($matches['target']);
        }

        throw new \InvalidArgumentException('Invalid request string');
    }

    private static function parseConnectAuthorityFormRequestTarget(string $method, string $target): ?Uri
    {
        if (!Rfc7230::isConnectAuthorityFormRequestTarget($method, $target)) {
            return null;
        }

        $parsed = Rfc7230::parseHostHeader($target);
        if ($parsed === null) {
            return null;
        }

        [$host, $port] = $parsed;
        if ($port === null) {
            return null;
        }

        try {
            return new Uri('//'.self::composeAuthority($host, $port));
        } catch (\InvalidArgumentException $e) {
            return null;
        }
    }

    /**
     * Parses a response message string into a response object.
     *
     * @param string $message Response message string.
     */
    public static function parseResponse(string $message): ResponseInterface
    {
        $data = self::parseMessage($message);
        // According to https://datatracker.ietf.org/doc/html/rfc7230#section-3.1.2
        // the space between status-code and reason-phrase is required. But
        // browsers accept responses without space and reason as well.
        if (!preg_match('/^HTTP\/(?P<version>\d+(?:\.\d+)?) (?P<status>[1-5][0-9]{2})(?: (?P<reason>[\x09\x20-\x7E\x80-\xFF]*))?$/D', $data['start-line'], $matches)) {
            throw new \InvalidArgumentException('Invalid response string: '.$data['start-line']);
        }

        return new Response(
            (int) $matches['status'],
            $data['headers'],
            $data['body'],
            $matches['version'],
            $matches['reason'] ?? null
        );
    }
}
