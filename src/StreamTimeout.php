<?php

declare(strict_types=1);

namespace GuzzleHttp\Psr7;

use Psr\Http\Message\StreamInterface;

/**
 * @internal
 */
final class StreamTimeout
{
    private function __construct()
    {
    }

    public static function isReadTimedOut(StreamInterface $stream): bool
    {
        try {
            if ($stream->getMetadata('timed_out') !== true) {
                return false;
            }

            return !$stream->eof();
        } catch (\Throwable $e) {
            return false;
        }
    }

    public static function isWriteTimedOut(StreamInterface $stream): bool
    {
        try {
            return $stream->getMetadata('timed_out') === true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * @param resource $resource
     */
    public static function isResourceReadTimedOut($resource): bool
    {
        try {
            /** @var array<string, mixed> $metadata */
            $metadata = stream_get_meta_data($resource);

            return ($metadata['timed_out'] ?? false) === true && !feof($resource);
        } catch (\Throwable $e) {
            return false;
        }
    }
}
