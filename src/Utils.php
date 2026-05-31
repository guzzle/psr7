<?php

declare(strict_types=1);

namespace GuzzleHttp\Psr7;

use GuzzleHttp\Psr7\Exception\TimeoutException;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;

final class Utils
{
    private function __construct()
    {
    }

    /**
     * Remove the items given by the keys, case insensitively from the data.
     *
     * @param array<array-key, string|int> $keys
     */
    public static function caselessRemove(array $keys, array $data): array
    {
        $result = [];

        foreach ($keys as &$key) {
            $key = strtolower((string) $key);
        }

        foreach ($data as $k => $v) {
            if (!in_array(strtolower((string) $k), $keys)) {
                $result[$k] = $v;
            }
        }

        return $result;
    }

    /**
     * Copy the contents of a stream into another stream until the given number
     * of bytes have been read, returning the number of bytes copied.
     *
     * The destination must accept writes that make positive progress. Streams
     * that return 0 as a backpressure or drop signal (a BufferStream at its high
     * water mark, or a full DroppingStream) will cause this method to throw.
     *
     * @param StreamInterface $source Stream to read from
     * @param StreamInterface $dest   Stream to write to
     * @param int             $maxLen Maximum number of bytes to read. Pass -1
     *                                to read the entire stream.
     *
     * @throws \RuntimeException on error.
     */
    public static function copyToStream(StreamInterface $source, StreamInterface $dest, int $maxLen = -1): int
    {
        $bufferSize = 8192;
        $copied = 0;

        if ($maxLen === -1) {
            while (!$source->eof()) {
                $buf = StreamTimeout::read($source, $bufferSize, 'Unable to read from stream: timed out');
                if ($buf === '') {
                    break;
                }

                self::writeAll($dest, $buf);
                $copied = Integers::add($copied, strlen($buf));
            }
        } else {
            $remaining = $maxLen;
            while ($remaining > 0 && !$source->eof()) {
                $buf = StreamTimeout::read($source, min($bufferSize, $remaining), 'Unable to read from stream: timed out');
                $len = strlen($buf);
                if (!$len) {
                    break;
                }
                $remaining -= $len;
                self::writeAll($dest, $buf);
                $copied = Integers::add($copied, $len);
            }
        }

        return $copied;
    }

    private static function writeAll(StreamInterface $dest, string $buf): void
    {
        $written = 0;
        $len = strlen($buf);

        while ($written < $len) {
            try {
                $result = $dest->write(substr($buf, $written));
            } catch (TimeoutException $e) {
                throw $e;
            } catch (\RuntimeException $e) {
                StreamTimeout::throwIfWriteTimedOut($dest, $e);

                throw $e;
            }

            if ($result <= 0) {
                StreamTimeout::throwIfWriteTimedOut($dest);

                throw new \RuntimeException('Unable to write to stream');
            }

            $written += $result;
        }
    }

    /**
     * Copy the contents of a stream into a string until the given number of
     * bytes have been read.
     *
     * @param StreamInterface $stream Stream to read
     * @param int             $maxLen Maximum number of bytes to read. Pass -1
     *                                to read the entire stream.
     *
     * @throws \RuntimeException on error.
     */
    public static function copyToString(StreamInterface $stream, int $maxLen = -1): string
    {
        $buffer = '';

        if ($maxLen === -1) {
            while (!$stream->eof()) {
                $buf = StreamTimeout::read($stream, 1048576, 'Unable to read from stream: timed out');
                if ($buf === '') {
                    break;
                }
                $buffer .= $buf;
            }

            return $buffer;
        }

        $len = 0;
        while (!$stream->eof() && $len < $maxLen) {
            $buf = StreamTimeout::read($stream, $maxLen - $len, 'Unable to read from stream: timed out');
            if ($buf === '') {
                break;
            }
            $buffer .= $buf;
            $len = strlen($buffer);
        }

        return $buffer;
    }

    /**
     * Calculate a hash of a stream.
     *
     * This method reads the entire stream to calculate a rolling hash, based
     * on PHP's `hash_init` functions.
     *
     * @param StreamInterface $stream    Stream to calculate the hash for
     * @param string          $algo      Hash algorithm (e.g. md5, crc32, etc)
     * @param bool            $rawOutput Whether or not to use raw output
     *
     * @throws \RuntimeException on error.
     */
    public static function hash(StreamInterface $stream, string $algo, bool $rawOutput = false): string
    {
        $pos = $stream->tell();

        if ($pos > 0) {
            $stream->rewind();
        }

        $ctx = hash_init($algo);
        while (!$stream->eof()) {
            $buf = StreamTimeout::read($stream, 1048576, 'Unable to calculate stream hash: timed out');
            if ($buf === '') {
                break;
            }

            hash_update($ctx, $buf);
        }

        $out = hash_final($ctx, $rawOutput);
        $stream->seek($pos);

        return $out;
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
     * - body: (mixed) Sets the given body. Present non-null values are converted
     *   with self::streamFor(), including scalar values, resources, streams,
     *   iterators, callable arrays, closures, invokable objects, and stringable
     *   objects. String inputs remain literal bodies.
     * - uri: (UriInterface) Set the URI.
     * - query: (string) Set the query string value of the URI.
     * - version: (string) Set the protocol version.
     *
     * @param RequestInterface $request Request to clone and modify.
     * @param array{
     *     method?: string,
     *     set_headers?: array<array-key, string|non-empty-array<array-key, string>>,
     *     remove_headers?: array<array-key, string|int>,
     *     body?: resource|string|int|float|bool|StreamInterface|callable|\Iterator|\Stringable,
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
                        if (strtolower((string) $header) === 'host') {
                            throw new \InvalidArgumentException(
                                'Cannot modify request with both a URI containing a host and an explicit Host header.'
                            );
                        }
                    }
                }

                $changes['set_headers']['Host'] = $host;

                if ($port = $uri->getPort()) {
                    $standardPorts = ['http' => 80, 'https' => 443];
                    $scheme = $uri->getScheme();
                    if (isset($standardPorts[$scheme]) && $port != $standardPorts[$scheme]) {
                        $changes['set_headers']['Host'] .= ':'.$port;
                    }
                }
            }
        }

        if (!empty($changes['remove_headers'])) {
            $headers = self::caselessRemove($changes['remove_headers'], $headers);
        }

        if (!empty($changes['set_headers'])) {
            $headers = self::caselessRemove(array_keys($changes['set_headers']), $headers);
            $headers = $changes['set_headers'] + $headers;
        }

        if (isset($changes['query'])) {
            $uri = $uri->withQuery($changes['query']);
        }

        $hasHost = false;
        foreach (array_keys($headers) as $header) {
            if (strtolower((string) $header) === 'host') {
                $hasHost = true;
                break;
            }
        }

        // Match Request::__construct() by adding a Host header when one is not provided.
        if (!$hasHost && $uri->getHost() !== '') {
            $host = $uri->getHost();

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
                $normalized = strtolower($header);

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
            $new = $new->withBody(self::streamFor($changes['body']));
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
            self::assertValidModifyRequestChange('body', 'resource|string|int|float|bool|StreamInterface|callable|\Iterator|\Stringable', $changes['body']);
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
        throw new \InvalidArgumentException(\sprintf(
            'Utils::modifyRequest() change "%s" must be %s; %s provided.',
            $key,
            $expected,
            \get_debug_type($value)
        ));
    }

    /**
     * Read a line from the stream up to the maximum allowed buffer length.
     *
     * @param StreamInterface $stream    Stream to read from
     * @param int|null        $maxLength Maximum buffer length
     */
    public static function readLine(StreamInterface $stream, ?int $maxLength = null): string
    {
        $buffer = '';
        $size = 0;

        while (!$stream->eof()) {
            if ('' === ($byte = StreamTimeout::read($stream, 1, 'Unable to read line from stream: timed out'))) {
                return $buffer;
            }
            $buffer .= $byte;
            // Break when a new line is found or the max length - 1 is reached
            if ($byte === "\n" || ++$size === $maxLength - 1) {
                break;
            }
        }

        return $buffer;
    }

    /**
     * Redact the user info part of a URI.
     */
    public static function redactUserInfo(UriInterface $uri): UriInterface
    {
        return $uri->getUserInfo() === '' ? $uri : $uri->withUserInfo('***');
    }

    /**
     * Create a new stream based on the input type.
     *
     * Options is an associative array that can contain the following keys:
     * - metadata: Array of custom metadata.
     * - size: Size of the stream.
     *
     * This method accepts the following `$resource` types:
     * - `Psr\Http\Message\StreamInterface`: Returns the value as-is.
     * - `string`: Creates a stream object that uses the given string as the contents.
     * - `resource`: Creates a stream object that wraps the given PHP stream resource.
     * - `Iterator`: If the provided value implements `Iterator`, then a read-only
     *   stream object will be created that wraps the given iterable. Each time the
     *   stream is read from, data from the iterator will fill a buffer and will be
     *   continuously called until the buffer is equal to the requested read size.
     *   Values that stringify to an empty string are skipped while the iterator
     *   advances. Subsequent read calls will first read from the buffer and then
     *   call `next` on the underlying iterator until it is exhausted.
     * - `object` with `__toString()`: If the object has the `__toString()` method,
     *   the object will be cast to a string and then a stream will be returned that
     *   uses the string value.
     * - `NULL`: When `null` is passed, an empty stream object is returned.
     * - `callable`: When a callable array, closure, or invokable object is passed
     *   and no earlier resource or object rule applies, a read-only stream object
     *   will be created that invokes the given callable. The callable is invoked
     *   with the suggested number of bytes to read. The callable can return fewer
     *   or more bytes than requested, but MUST return a non-empty string when data
     *   is available and `false` or `null` when there is no more data to return.
     *   Any additional bytes will be buffered and used in subsequent reads. String
     *   inputs are always treated as string bodies, even when they name callable
     *   functions.
     *
     * @param resource|string|int|float|bool|StreamInterface|callable|\Iterator|\Stringable|null $resource Entity body data
     * @param array{size?: int, metadata?: array}                                                $options  Additional options
     *
     * @throws \InvalidArgumentException if the $resource arg is not valid.
     */
    public static function streamFor($resource = '', array $options = []): StreamInterface
    {
        if (is_scalar($resource)) {
            $stream = self::tryFopen('php://temp', 'r+');
            if ($resource !== '') {
                fwrite($stream, (string) $resource);
                fseek($stream, 0);
            }

            return new Stream($stream, $options);
        }

        switch (gettype($resource)) {
            case 'resource':
                /*
                 * The 'php://input' is a special stream with quirks and inconsistencies.
                 * We avoid using that stream by reading it into php://temp
                 */

                /** @var resource $resource */
                if ((\stream_get_meta_data($resource)['uri'] ?? '') === 'php://input') {
                    $stream = self::tryFopen('php://temp', 'w+');
                    stream_copy_to_stream($resource, $stream);
                    fseek($stream, 0);
                    $resource = $stream;
                }

                return new Stream($resource, $options);
            case 'object':
                /** @var object $resource */
                if ($resource instanceof StreamInterface) {
                    return $resource;
                } elseif ($resource instanceof \Iterator) {
                    return new PumpStream(function (int $length) use ($resource) {
                        while ($resource->valid()) {
                            $result = $resource->current();
                            $resource->next();

                            if ($result === null || is_scalar($result)) {
                                $data = (string) $result;
                            } elseif (is_object($result) && method_exists($result, '__toString')) {
                                $data = (string) $result;
                            } else {
                                throw new \UnexpectedValueException('Iterator must yield scalar, null, or stringable values');
                            }

                            if ($data !== '') {
                                return $data;
                            }
                        }

                        return false;
                    }, $options);
                } elseif (method_exists($resource, '__toString')) {
                    return self::streamFor((string) $resource, $options);
                }
                break;
            case 'NULL':
                return new Stream(self::tryFopen('php://temp', 'r+'), $options);
        }

        if (is_callable($resource)) {
            return new PumpStream($resource, $options);
        }

        throw new \InvalidArgumentException('Invalid resource type: '.\get_debug_type($resource));
    }

    /**
     * Safely opens a PHP stream resource using a filename.
     *
     * When fopen fails, PHP normally raises a warning. This function adds an
     * error handler that checks for errors and throws an exception instead.
     *
     * @param string $filename File to open
     * @param string $mode     Mode used to open the file
     *
     * @return resource
     *
     * @throws \RuntimeException if the file cannot be opened
     */
    public static function tryFopen(string $filename, string $mode)
    {
        $ex = null;
        set_error_handler(static function (int $errno, string $errstr) use ($filename, $mode, &$ex): bool {
            $ex = new \RuntimeException(sprintf(
                'Unable to open "%s" using mode "%s": %s',
                $filename,
                $mode,
                $errstr
            ));

            return true;
        });

        try {
            /** @var resource $handle */
            $handle = fopen($filename, $mode);
        } catch (\Throwable $e) {
            $ex = new \RuntimeException(sprintf(
                'Unable to open "%s" using mode "%s": %s',
                $filename,
                $mode,
                $e->getMessage()
            ), 0, $e);
        }

        restore_error_handler();

        if ($ex) {
            /** @var \RuntimeException $ex */
            throw $ex;
        }

        return $handle;
    }

    /**
     * Safely gets the contents of a given stream.
     *
     * When stream_get_contents fails, PHP normally raises a warning. This
     * function adds an error handler that checks for errors and throws an
     * exception instead.
     *
     * @param resource $stream
     *
     * @throws \RuntimeException if the stream cannot be read
     */
    public static function tryGetContents($stream): string
    {
        $ex = null;
        set_error_handler(static function (int $errno, string $errstr) use (&$ex): bool {
            $ex = new \RuntimeException(sprintf(
                'Unable to read stream contents: %s',
                $errstr
            ));

            return true;
        });

        try {
            /** @var string|false $contents */
            $contents = stream_get_contents($stream);

            if ($contents === false) {
                $ex = StreamTimeout::isResourceReadTimedOut($stream)
                    ? new TimeoutException('Unable to read stream contents: timed out')
                    : new \RuntimeException('Unable to read stream contents');
            } elseif (StreamTimeout::isResourceReadTimedOut($stream)) {
                $ex = new TimeoutException('Unable to read stream contents: timed out');
            }
        } catch (TimeoutException $e) {
            $ex = $e;
        } catch (\Throwable $e) {
            $ex = StreamTimeout::isResourceReadTimedOut($stream)
                ? new TimeoutException('Unable to read stream contents: timed out', 0, $e)
                : new \RuntimeException(sprintf(
                    'Unable to read stream contents: %s',
                    $e->getMessage()
                ), 0, $e);
        }

        restore_error_handler();

        if ($ex) {
            /** @var \RuntimeException $ex */
            throw $ex;
        }

        return $contents;
    }

    /**
     * Returns a UriInterface for the given value.
     *
     * This function accepts a string or UriInterface and returns a
     * UriInterface for the given value. If the value is already a
     * UriInterface, it is returned as-is.
     *
     * @param string|UriInterface $uri
     *
     * @throws \InvalidArgumentException
     */
    public static function uriFor($uri): UriInterface
    {
        if ($uri instanceof UriInterface) {
            return $uri;
        }

        if (is_string($uri)) {
            return new Uri($uri);
        }

        throw new \InvalidArgumentException('URI must be a string or UriInterface');
    }
}
