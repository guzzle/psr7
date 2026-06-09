# Streams and Decorators

PSR-7 request and response bodies are streams. Streams let HTTP messages represent large bodies, file handles, generated data, or in-memory strings through a single interface.

## Creating Streams

Use `GuzzleHttp\Psr7\Utils::streamFor()` to create streams from common PHP values.

```php
use GuzzleHttp\Psr7\Utils;

$stream = Utils::streamFor('body contents');

echo $stream->read(4);
echo $stream->getContents();
```

`streamFor()` accepts strings, resources returned from `fopen()`, objects implementing `__toString()`, iterators, callables, and existing `Psr\Http\Message\StreamInterface` instances. Strings remain literal body contents, even when they name a callable.

## Stream Metadata

Streams expose capabilities and resource metadata through methods such as `isReadable()`, `isWritable()`, `isSeekable()`, and `getMetadata()`.

```php
use GuzzleHttp\Psr7\Utils;

$resource = Utils::tryFopen('/path/to/file', 'r');
$stream = Utils::streamFor($resource);

var_export($stream->isReadable());
echo $stream->getMetadata('uri');
```

## AppendStream

`GuzzleHttp\Psr7\AppendStream` reads from multiple streams, one after another.

```php
use GuzzleHttp\Psr7;

$stream = new Psr7\AppendStream([
    Psr7\Utils::streamFor('abc'),
    Psr7\Utils::streamFor('123'),
]);

echo $stream;
// abc123
```

## BufferStream

`GuzzleHttp\Psr7\BufferStream` provides a readable and writable in-memory buffer. It can be useful when producer and consumer code need a stream boundary.

## CachingStream

`GuzzleHttp\Psr7\CachingStream` allows seeking over bytes that were previously read from a non-seekable stream. Bytes are cached first in memory and then on disk using `php://temp`.

The optional cache target must be readable, writable, seekable, and lossless. Lossy or non-seekable streams such as `BufferStream` and `DroppingStream` are not valid targets.

## DroppingStream

`GuzzleHttp\Psr7\DroppingStream` starts dropping writes when the underlying stream grows beyond the configured size.

## FnStream

`GuzzleHttp\Psr7\FnStream` composes stream behavior from an array of callables. This is useful for tests and simple stream decoration without creating a concrete class.

## InflateStream

`GuzzleHttp\Psr7\InflateStream` uses PHP's `zlib.inflate` filter to decode zlib or gzip-compressed stream contents.

## LazyOpenStream

`GuzzleHttp\Psr7\LazyOpenStream` opens a file only when the first stream operation needs it.

## LimitStream

`GuzzleHttp\Psr7\LimitStream` exposes a slice of another stream. It is useful for reading a fixed byte range from a larger stream.

## MultipartStream

`GuzzleHttp\Psr7\MultipartStream` creates streaming `multipart/form-data` request bodies.

## NoSeekStream

`GuzzleHttp\Psr7\NoSeekStream` wraps another stream and disables seeking.

## PumpStream

`GuzzleHttp\Psr7\PumpStream` reads from a callable that generates bytes on demand.

## Implementing Stream Decorators

Use `GuzzleHttp\Psr7\StreamDecoratorTrait` when writing a decorator that forwards most stream behavior to another stream. Override only the methods that need custom behavior.

## PHP StreamWrapper

`GuzzleHttp\Psr7\StreamWrapper` exposes a PSR-7 stream as a PHP stream resource for APIs that require native stream resources.
