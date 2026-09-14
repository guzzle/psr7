<?php

declare(strict_types=1);

namespace GuzzleHttp\Psr7;

use GuzzleHttp\Psr7\Exception\TimeoutException;
use Psr\Http\Message\StreamInterface;

final class Streams
{
    private function __construct()
    {
    }

    /**
     * Copy the contents of a stream into another stream until the given number
     * of bytes have been read, returning the number of bytes copied as an
     * `int`. On 32-bit PHP, an unbounded copy larger than `PHP_INT_MAX` bytes
     * cannot be represented by that return type. 64-bit PHP is not affected.
     *
     * The destination must accept writes that make positive progress. Streams
     * that return 0 as a backpressure or drop signal (a `BufferStream` at its
     * high water mark, or a full `DroppingStream`) will cause this method to
     * throw. For full copies, use a normal writable stream such as a file or
     * `php://temp` stream.
     *
     * Throws `TimeoutException` when PHP-style timeout metadata can be detected
     * after a source read or destination write cannot make progress.
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
     * Throws `TimeoutException` when PHP-style timeout metadata can be detected
     * after a stream read cannot make progress.
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
     * This method reads the entire stream to calculate a rolling hash, based on
     * PHP's `hash_init` functions.
     *
     * Throws `TimeoutException` when PHP-style timeout metadata can be detected
     * after a stream read cannot make progress.
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
     * Read a line from the stream up to the maximum allowed buffer length.
     *
     * Throws `TimeoutException` when PHP-style timeout metadata can be detected
     * after a stream read cannot make progress.
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
     * Create a new stream based on the input type.
     *
     * Options are provided as an associative array that can contain the
     * following keys:
     * - metadata: Array of custom metadata.
     * - size: Size of the stream.
     *
     * This method accepts the following `$resource` types:
     * - `Psr\Http\Message\StreamInterface`: Returns the value as-is.
     * - `string`: Creates a stream object that uses the given string as the
     *   contents.
     * - `resource`: Creates a stream object that wraps the given PHP stream
     *   resource.
     * - `Iterator`: If the provided value implements `Iterator`, then a
     *   read-only stream object will be created that wraps the given iterable.
     *   Each time the stream is read from, data from the iterator will fill a
     *   buffer and will be continuously called until the buffer is equal to the
     *   requested read size. Yielded strings, integers, finite floats,
     *   booleans, `null`, and stringable objects are converted to string
     *   chunks; non-finite floats and other values throw
     *   `UnexpectedValueException` when the stream is read. Values that
     *   stringify to an empty string are skipped while the iterator advances.
     *   Subsequent read calls will first read from the buffer and then call
     *   `next` on the underlying iterator until it is exhausted.
     * - `object` with `__toString()`: If the object has the `__toString()`
     *   method, the object will be cast to a string and then a stream will be
     *   returned that uses the string value.
     * - `NULL`: When `null` is passed, an empty stream object is returned.
     * - `callable`: When a callable array, closure, or invokable object is
     *   passed and no earlier resource or object rule applies, a read-only
     *   stream object will be created that invokes the given callable. The
     *   callable is invoked with the suggested number of bytes to read. The
     *   callable can return fewer or more bytes than requested, but MUST return
     *   a non-empty string to provide data and MUST return `false` or `null`
     *   when there is no more data to return. Any additional bytes will be
     *   buffered and used in subsequent reads. String inputs are always treated
     *   as string bodies, even when they name callable functions.
     *
     * @param resource|string|StreamInterface|callable|\Iterator|\Stringable|null $resource Entity body data
     * @param array{size?: int, metadata?: array}                                 $options  Additional options
     *
     * @throws \InvalidArgumentException if the $resource arg is not valid.
     */
    public static function streamFor($resource = '', array $options = []): StreamInterface
    {
        if (is_scalar($resource)) {
            if (!is_string($resource)) {
                throw new \InvalidArgumentException(\sprintf(
                    'Cannot create a stream from %s; pass a string, resource, StreamInterface, Stringable, Iterator, callable, or null.',
                    \get_debug_type($resource)
                ));
            }

            $stream = self::tryFopen('php://temp', 'r+');
            if ($resource !== '') {
                fwrite($stream, $resource);
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

                            if (is_float($result) && !is_finite($result)) {
                                throw new \UnexpectedValueException('Iterator must not yield non-finite float values');
                            }

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
     * When `fopen()` fails, PHP normally raises a warning. This function adds
     * an error handler that checks for errors and throws an exception instead.
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
            $ex = new \RuntimeException(sprintf('Unable to open %s using mode %s: %s', DiagnosticValue::escape($filename), DiagnosticValue::escape($mode), DiagnosticValue::escape($errstr)));

            return true;
        });

        try {
            /** @var resource $handle */
            $handle = fopen($filename, $mode);
        } catch (\Throwable $e) {
            $ex = new \RuntimeException(sprintf('Unable to open %s using mode %s: %s', DiagnosticValue::escape($filename), DiagnosticValue::escape($mode), $e->getMessage()), 0, $e);
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
     * When `stream_get_contents()` fails, PHP normally raises a warning. This
     * function adds an error handler that checks for errors and throws an
     * exception instead.
     *
     * Throws `TimeoutException` when PHP-style timeout metadata can be detected
     * after a stream read cannot make progress.
     *
     * @param resource $stream
     *
     * @throws \RuntimeException if the stream cannot be read
     */
    public static function tryGetContents($stream): string
    {
        $ex = null;
        set_error_handler(static function (int $errno, string $errstr) use (&$ex): bool {
            $ex = new \RuntimeException(sprintf('Unable to read stream contents: %s', DiagnosticValue::escape($errstr)));

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
                : new \RuntimeException(sprintf('Unable to read stream contents: %s', $e->getMessage()), 0, $e);
        }

        restore_error_handler();

        if ($ex) {
            /** @var \RuntimeException $ex */
            throw $ex;
        }

        return $contents;
    }
}
