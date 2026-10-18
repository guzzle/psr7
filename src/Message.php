<?php

declare(strict_types=1);

namespace GuzzleHttp\Psr7;

use Psr\Http\Message\MessageInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
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
                    .$message->getRequestTarget(), " \n\r\t\0\x0B")
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
            if (is_string($name) && Utils::asciiToLower($name) === 'set-cookie') {
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

        Uri::assertValidHost($host);

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
     * Reads seekable bodies from the beginning and restores the original cursor
     * position before returning. Pass `null` for `$truncateAt` to use the
     * default summary length.
     *
     * @param MessageInterface $message    The message to get the body summary
     * @param int|null         $truncateAt Maximum allowed size of the summary
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
     * The array contains the `start-line` key containing the start line of the
     * message, `headers` key containing an associative array of header array
     * values, and a `body` key containing the body of the message.
     *
     * @param string $message HTTP request or response to parse.
     */
    public static function parseMessage(string $message): array
    {
        return MessageParser::parseMessage($message);
    }

    /**
     * Constructs a URI for an HTTP request message.
     *
     * The URI is composed from the start-line path and the `Host` header, using
     * `https` when the host's port is `443` and `http` otherwise. Without a
     * `Host` header, only the path is returned, with extra leading slashes
     * collapsed so an origin-form target cannot be parsed as a network-path
     * reference with its own authority. An `InvalidArgumentException` is thrown
     * when the `Host` header is invalid.
     *
     * @param string $path    Path from the start-line
     * @param array  $headers Array of headers (each value an array).
     */
    public static function parseRequestUri(string $path, array $headers): string
    {
        return MessageParser::parseRequestUri($path, $headers);
    }

    /**
     * Parses a request message string into a request object.
     *
     * The request-target must be in origin form, absolute form (without a
     * userinfo component), authority form (`CONNECT`), or asterisk form
     * (`OPTIONS`), and any `Host` header must be a single valid value;
     * otherwise an `InvalidArgumentException` is thrown. Non-origin-form
     * targets are preserved on the returned request via `withRequestTarget()`.
     *
     * @param string $message Request message string.
     */
    public static function parseRequest(string $message): RequestInterface
    {
        return MessageParser::parseRequest($message);
    }

    /**
     * Parses a response message string into a response object.
     *
     * @param string $message Response message string.
     */
    public static function parseResponse(string $message): ResponseInterface
    {
        return MessageParser::parseResponse($message);
    }

    /**
     * Clone and modify a request with the given changes.
     *
     * This method is useful for reducing the number of clones needed to mutate
     * a message.
     *
     * The changes can be one of:
     * - method: (string) Changes the HTTP method.
     * - set_headers: (array) Sets the given headers. Values must be strings
     *   or non-empty arrays of strings.
     * - remove_headers: (array) Remove the given headers. Values may be
     *   strings or integers.
     * - body: (mixed) Sets the given body. Present non-null values are
     *   converted with Utils::streamFor(), including resources, streams,
     *   iterators, callable arrays, closures, invokable objects, and stringable
     *   objects. String inputs remain literal bodies.
     * - uri: (UriInterface) Set the URI. When the URI contains a host, the
     *   Host header is updated from it, and combining this with an explicit
     *   Host entry in set_headers throws an InvalidArgumentException. Apply
     *   an intentional Host override separately with withHeader() afterwards.
     * - query: (string) Set the query string value of the URI.
     * - version: (string) Set the protocol version.
     *
     * @param RequestInterface $request Request to clone and modify.
     * @param array{
     *     method?: string,
     *     set_headers?: array<array-key, string|non-empty-array<array-key, string>>,
     *     remove_headers?: array<array-key, string|int>,
     *     body?: resource|string|StreamInterface|callable|\Iterator|\Stringable,
     *     uri?: UriInterface,
     *     query?: string,
     *     version?: string
     * } $changes Changes to apply.
     */
    public static function modifyRequest(RequestInterface $request, array $changes): RequestInterface
    {
        if (!$changes) {
            return $request;
        }

        self::assertValidModifyRequestChanges($changes);

        $headers = $request->getHeaders();

        if (!isset($changes['uri'])) {
            $uri = $request->getUri();
        } else {
            /** @var UriInterface */
            $uri = $changes['uri'];

            $host = $uri->getHost();
            if ($host !== '') {
                Uri::assertValidHost($host);

                if (isset($changes['set_headers']) && is_array($changes['set_headers'])) {
                    foreach (array_keys($changes['set_headers']) as $header) {
                        if (Utils::asciiToLower((string) $header) === 'host') {
                            throw new \InvalidArgumentException(
                                'Cannot modify request with both a URI containing a host and an explicit Host header.'
                            );
                        }
                    }
                }

                $changes['set_headers']['Host'] = $host;

                $port = $uri->getPort();
                if ($port !== null) {
                    $standardPorts = ['http' => 80, 'https' => 443];
                    $scheme = $uri->getScheme();
                    if (!isset($standardPorts[$scheme]) || $port != $standardPorts[$scheme]) {
                        $changes['set_headers']['Host'] .= ':'.$port;
                    }
                }
            }
        }

        if (!empty($changes['remove_headers'])) {
            $headers = Utils::caselessRemove($changes['remove_headers'], $headers);
        }

        if (!empty($changes['set_headers'])) {
            $headers = Utils::caselessRemove(array_keys($changes['set_headers']), $headers);
            $headers = $changes['set_headers'] + $headers;
        }

        if (isset($changes['query'])) {
            $uri = $uri->withQuery($changes['query']);
        }

        $hasHost = false;
        foreach (array_keys($headers) as $header) {
            if (Utils::asciiToLower((string) $header) === 'host') {
                $hasHost = true;
                break;
            }
        }

        // Match Request::__construct() by adding a Host header when one is not provided.
        if (!$hasHost && $uri->getHost() !== '') {
            $host = $uri->getHost();
            Uri::assertValidHost($host);

            if (($port = $uri->getPort()) !== null) {
                $host .= ':'.$port;
            }

            $headers = ['Host' => [$host]] + $headers;
        }

        $new = $request;

        if (isset($changes['method'])) {
            $new = $new->withMethod($changes['method']);
        }

        if (isset($changes['uri']) || isset($changes['query'])) {
            $new = $new->withUri($uri, true);
        }

        if ($headers !== $new->getHeaders()) {
            foreach (array_keys($new->getHeaders()) as $header) {
                /** @var RequestInterface */
                $new = $new->withoutHeader((string) $header);
            }

            $addedHeaders = [];
            foreach ($headers as $header => $value) {
                $header = (string) $header;
                $normalized = Utils::asciiToLower($header);

                if (isset($addedHeaders[$normalized])) {
                    /** @var RequestInterface */
                    $new = $new->withAddedHeader($addedHeaders[$normalized], $value);
                } else {
                    /** @var RequestInterface */
                    $new = $new->withHeader($header, $value);
                    $addedHeaders[$normalized] = $header;
                }
            }
        }

        if (isset($changes['body'])) {
            /** @var RequestInterface */
            $new = $new->withBody(Utils::streamFor($changes['body']));
        }

        if (isset($changes['version'])) {
            /** @var RequestInterface */
            $new = $new->withProtocolVersion($changes['version']);
        }

        return $new;
    }

    /**
     * @param array<array-key, mixed> $changes
     */
    private static function assertValidModifyRequestChanges(array $changes): void
    {
        foreach (['method', 'query', 'version'] as $key) {
            if (\array_key_exists($key, $changes) && !\is_string($changes[$key])) {
                self::assertValidModifyRequestChange($key, 'string', $changes[$key]);
            }
        }

        if (\array_key_exists('uri', $changes) && !$changes['uri'] instanceof UriInterface) {
            self::assertValidModifyRequestChange('uri', 'UriInterface', $changes['uri']);
        }

        if (\array_key_exists('body', $changes) && $changes['body'] === null) {
            self::assertValidModifyRequestChange('body', 'resource|string|StreamInterface|callable|\Iterator|\Stringable', $changes['body']);
        }

        if (\array_key_exists('set_headers', $changes)) {
            if (!\is_array($changes['set_headers'])) {
                self::assertValidModifyRequestChange('set_headers', 'array<array-key, string|non-empty-array<array-key, string>>', $changes['set_headers']);
            } else {
                foreach ($changes['set_headers'] as $header => $value) {
                    $headerPath = \sprintf('set_headers.%s', (string) $header);

                    if (\is_array($value)) {
                        if ($value === []) {
                            self::assertValidModifyRequestChange($headerPath, 'string|non-empty-array<array-key, string>', $value);

                            break;
                        }

                        foreach ($value as $index => $item) {
                            if (!\is_string($item)) {
                                self::assertValidModifyRequestChange(\sprintf('%s.%s', $headerPath, (string) $index), 'string', $item);

                                break 2;
                            }
                        }
                    } elseif (!\is_string($value)) {
                        self::assertValidModifyRequestChange($headerPath, 'string|non-empty-array<array-key, string>', $value);

                        break;
                    }
                }
            }
        }

        if (!\array_key_exists('remove_headers', $changes)) {
            return;
        }

        if (!\is_array($changes['remove_headers'])) {
            self::assertValidModifyRequestChange('remove_headers', 'array<array-key, string|int>', $changes['remove_headers']);

            return;
        }

        foreach ($changes['remove_headers'] as $index => $header) {
            if (!\is_string($header) && !\is_int($header)) {
                self::assertValidModifyRequestChange(\sprintf('remove_headers.%s', (string) $index), 'string|int', $header);

                return;
            }
        }
    }

    /**
     * @param mixed $value
     */
    private static function assertValidModifyRequestChange(string $key, string $expected, $value): void
    {
        throw new \InvalidArgumentException(\sprintf('Message::modifyRequest() change "%s" must be %s; %s provided.', DiagnosticValue::escape($key), $expected, \get_debug_type($value)));
    }
}
