<?php

declare(strict_types=1);

namespace GuzzleHttp\Psr7;

use Psr\Http\Message\StreamInterface;

/**
 * Provides a read only stream that pumps data from a PHP callable.
 *
 * When invoking the provided callable, the PumpStream will pass the amount of
 * data requested to read to the callable. The callable can choose to ignore
 * this value and return fewer or more bytes than requested. Any extra data
 * returned by the provided callable is buffered internally until drained using
 * the read() function of the PumpStream. The provided callable MUST return
 * false when there is no more data to read.
 */
final class PumpStream implements StreamInterface
{
    /** @var callable(int): (string|false|null)|null */
    private $source;

    /** @var int|null */
    private $size;

    /** @var int */
    private $tellPos = 0;

    /** @var array */
    private $metadata;

    /** @var BufferStream */
    private $buffer;

    /**
     * @param callable(int): (string|false|null)  $source  Source of the stream data. The callable MAY
     *                                                     accept an integer argument used to control the
     *                                                     amount of data to return. The callable MUST
     *                                                     return a string when called, or false|null on error
     *                                                     or EOF.
     * @param array{size?: int, metadata?: array} $options Stream options:
     *                                                     - metadata: Hash of metadata to use with stream.
     *                                                     - size: Size of the stream, if known.
     */
    public function __construct(callable $source, array $options = [])
    {
        $this->source = $source;
        $this->size = $options['size'] ?? null;
        $this->metadata = $options['metadata'] ?? [];
        $this->buffer = new BufferStream();
    }

    public function __toString(): string
    {
        return Utils::copyToString($this);
    }

    public function close(): void
    {
        $this->detach();
    }

    public function detach()
    {
        $this->tellPos = 0;
        $this->source = null;

        return null;
    }

    public function getSize(): ?int
    {
        return $this->size;
    }

    public function tell(): int
    {
        return $this->tellPos;
    }

    public function eof(): bool
    {
        return $this->source === null;
    }

    public function isSeekable(): bool
    {
        return false;
    }

    public function rewind(): void
    {
        $this->seek(0);
    }

    public function seek($offset, $whence = SEEK_SET): void
    {
        if (!\is_int($offset)) {
            \trigger_deprecation(
                'guzzlehttp/psr7',
                '2.11',
                'Passing %s to StreamInterface::seek() is deprecated; guzzlehttp/psr7 3.0 requires int for $offset.',
                \get_debug_type($offset)
            );
        }

        if (!\is_int($whence)) {
            \trigger_deprecation(
                'guzzlehttp/psr7',
                '2.11',
                'Passing %s to StreamInterface::seek() is deprecated; guzzlehttp/psr7 3.0 requires int for $whence.',
                \get_debug_type($whence)
            );
        }

        throw new \RuntimeException('Cannot seek a PumpStream');
    }

    public function isWritable(): bool
    {
        return false;
    }

    public function write($string): int
    {
        if (!\is_string($string)) {
            \trigger_deprecation(
                'guzzlehttp/psr7',
                '2.11',
                'Passing %s to StreamInterface::write() is deprecated; guzzlehttp/psr7 3.0 requires string for $string.',
                \get_debug_type($string)
            );
        }

        throw new \RuntimeException('Cannot write to a PumpStream');
    }

    public function isReadable(): bool
    {
        return true;
    }

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

        $data = $this->buffer->read($length);
        $readLen = strlen($data);
        $this->tellPos += $readLen;
        $remaining = $length - $readLen;

        if ($remaining) {
            $this->pump($remaining);
            $data .= $this->buffer->read($remaining);
            $this->tellPos += strlen($data) - $readLen;
        }

        return $data;
    }

    public function getContents(): string
    {
        $result = '';
        while (!$this->eof()) {
            $result .= $this->read(1000000);
        }

        return $result;
    }

    /**
     * @return mixed
     */
    public function getMetadata($key = null)
    {
        if ($key !== null && !\is_string($key)) {
            \trigger_deprecation(
                'guzzlehttp/psr7',
                '2.11',
                'Passing %s to StreamInterface::getMetadata() is deprecated; guzzlehttp/psr7 3.0 requires string|null for $key.',
                \get_debug_type($key)
            );
        }

        if (!$key) {
            return $this->metadata;
        }

        return $this->metadata[$key] ?? null;
    }

    private function pump(int $length): void
    {
        if ($this->source !== null) {
            do {
                $data = ($this->source)($length);
                if ($data === false || $data === null) {
                    $this->source = null;

                    return;
                }
                $this->buffer->write($data);
                $length -= strlen($data);
            } while ($length > 0);
        }
    }
}
