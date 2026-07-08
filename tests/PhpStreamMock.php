<?php

declare(strict_types=1);

namespace GuzzleHttp\Tests\Psr7;

/**
 * Shared mutable state for the `GuzzleHttp\Psr7` stream-function overrides
 * defined below. Tests toggle these flags to drive low-level read, write, and
 * timeout paths deterministically without real sockets or timeouts.
 */
final class PhpStreamMock
{
    public static bool $isFReadError = false;
    public static bool $isFReadZero = false;
    public static bool $isFReadException = false;
    public static bool $isFWriteError = false;
    public static bool $isFWriteZero = false;
    public static bool $isFWriteException = false;
    public static bool $isStreamTimedOut = false;
    public static bool $isStreamMetadataError = false;
    public static bool $streamGetContentsReturnsFalse = false;
    public static ?string $streamGetContentsResult = null;
    public static ?\Throwable $streamGetContentsThrowable = null;

    public static function reset(): void
    {
        self::$isFReadError = false;
        self::$isFReadZero = false;
        self::$isFReadException = false;
        self::$isFWriteError = false;
        self::$isFWriteZero = false;
        self::$isFWriteException = false;
        self::$isStreamTimedOut = false;
        self::$isStreamMetadataError = false;
        self::$streamGetContentsReturnsFalse = false;
        self::$streamGetContentsResult = null;
        self::$streamGetContentsThrowable = null;
    }
}

namespace GuzzleHttp\Psr7;

use GuzzleHttp\Tests\Psr7\PhpStreamMock;

/**
 * @param resource $handle
 *
 * @return string|false
 */
function fread($handle, int $length)
{
    if (PhpStreamMock::$isFReadException) {
        throw new \ErrorException('Some read error');
    }

    if (PhpStreamMock::$isFReadError) {
        return false;
    }

    if (PhpStreamMock::$isFReadZero) {
        return '';
    }

    return \fread($handle, $length);
}

/**
 * @param resource $handle
 *
 * @return int|false
 */
function fwrite($handle, string $string, ?int $length = null)
{
    if (PhpStreamMock::$isFWriteException) {
        throw new \ErrorException('Some write error');
    }

    if (PhpStreamMock::$isFWriteError) {
        return false;
    }

    if (PhpStreamMock::$isFWriteZero) {
        return 0;
    }

    if ($length === null) {
        return \fwrite($handle, $string);
    }

    return \fwrite($handle, $string, $length);
}

/**
 * @param resource $stream
 *
 * @return array<string, mixed>
 */
function stream_get_meta_data($stream): array
{
    if (PhpStreamMock::$isStreamMetadataError) {
        throw new \RuntimeException('metadata failed');
    }

    $metadata = \stream_get_meta_data($stream);

    if (PhpStreamMock::$isStreamTimedOut) {
        $metadata['timed_out'] = true;
    }

    return $metadata;
}

/**
 * @param resource $stream
 *
 * @return string|false
 */
function stream_get_contents($stream, ?int $length = null, int $offset = -1)
{
    if (PhpStreamMock::$streamGetContentsThrowable !== null) {
        throw PhpStreamMock::$streamGetContentsThrowable;
    }

    if (PhpStreamMock::$streamGetContentsReturnsFalse) {
        return false;
    }

    if (PhpStreamMock::$streamGetContentsResult !== null) {
        return PhpStreamMock::$streamGetContentsResult;
    }

    // PHP 7.4's stream_get_contents() rejects a null length, so pass only the
    // stream when no length was requested (the sole call site does).
    if ($length === null) {
        return \stream_get_contents($stream);
    }

    return $offset === -1
        ? \stream_get_contents($stream, $length)
        : \stream_get_contents($stream, $length, $offset);
}
