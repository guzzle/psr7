<?php

declare(strict_types=1);

namespace GuzzleHttp\Psr7;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;

final class RequestModifier
{
    private function __construct()
    {
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
     *   converted with Streams::streamFor(), including resources, streams,
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
    public static function apply(RequestInterface $request, array $changes): RequestInterface
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
            $new = $new->withBody(Streams::streamFor($changes['body']));
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
        throw new \InvalidArgumentException(\sprintf('Utils::modifyRequest() change "%s" must be %s; %s provided.', DiagnosticValue::escape($key), $expected, \get_debug_type($value)));
    }
}
