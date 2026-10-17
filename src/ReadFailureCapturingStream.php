<?php

declare(strict_types=1);

namespace GuzzleHttp\Psr7;

use Psr\Http\Message\StreamInterface;

/**
 * Stream decorator that records read failures and returns an empty string.
 *
 * InflateStream reads through it so that a failure part way through a filtered
 * fread() cannot discard bytes that were already decoded.
 *
 * @internal
 */
final class ReadFailureCapturingStream implements StreamInterface
{
    use StreamDecoratorTrait;

    /** @var StreamInterface */
    private $stream;

    /** @var \Exception|null */
    private $readFailure;

    public function read($length): string
    {
        if (!\is_int($length)) {
            \trigger_deprecation(
                'guzzlehttp/psr7',
                '2.11',
                'Passing %s to StreamInterface::read() is deprecated; guzzlehttp/psr7 3.0 requires int for $length.',
                \get_debug_type($length)
            );
        }

        try {
            return $this->stream->read($length);
        } catch (\Exception $e) {
            $this->readFailure = $e;

            return '';
        }
    }

    public function hasReadFailure(): bool
    {
        return $this->readFailure !== null;
    }

    public function takeReadFailure(): ?\Exception
    {
        $failure = $this->readFailure;
        $this->readFailure = null;

        return $failure;
    }
}
