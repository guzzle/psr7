# Streams and Decorators

## AppendStream

`GuzzleHttp\Psr7\AppendStream`

Reads from multiple streams, one after the other.

```php
use GuzzleHttp\Psr7;

$a = Psr7\Utils::streamFor('abc, ');
$b = Psr7\Utils::streamFor('123.');
$composed = new Psr7\AppendStream([$a, $b]);

$composed->addStream(Psr7\Utils::streamFor(' Above all listen to me'));

echo $composed; // abc, 123. Above all listen to me.
```


## BufferStream

`GuzzleHttp\Psr7\BufferStream`

Provides a buffer stream that can be written to fill a buffer, and read
from to remove bytes from the buffer.

This stream returns a "hwm" metadata value that tells upstream consumers
what the configured high water mark of the stream is, or the maximum
preferred size of the buffer.

```php
use GuzzleHttp\Psr7;

// When the buffer reaches or exceeds 1024 bytes, it will begin returning 0 to
// writes. This is an indication that writers should slow down.
$buffer = new Psr7\BufferStream(1024);
```


## CachingStream

The CachingStream is used to allow seeking over previously read bytes on
non-seekable streams. This can be useful when transferring a non-seekable
entity body fails due to needing to rewind the stream (for example, resulting
from a redirect). Data that is read from the remote stream will be buffered in
a PHP temp stream so that previously read bytes are cached first in memory,
then on disk.

```php
use GuzzleHttp\Psr7;

$original = Psr7\Utils::streamFor(fopen('http://www.google.com', 'r'));
$stream = new Psr7\CachingStream($original);

$stream->read(1024);
echo $stream->tell();
// 1024

$stream->seek(0);
echo $stream->tell();
// 0
```

By default the bytes are cached in a `php://temp` stream. You can supply your own
cache target as the second constructor argument, but it is used as a random-access
byte buffer to replay the remote stream, so it must be readable, writable, and
seekable, report an accurate position and size, and store writes losslessly. Lossy
or non-seekable streams such as `BufferStream` and `DroppingStream` are not valid
targets.


## DroppingStream

`GuzzleHttp\Psr7\DroppingStream`

Stream decorator that begins dropping data once the size of the underlying
stream becomes too full.

```php
use GuzzleHttp\Psr7;

// Create an empty stream
$stream = Psr7\Utils::streamFor();

// Start dropping data when the stream has more than 10 bytes
$dropping = new Psr7\DroppingStream($stream, 10);

$dropping->write('01234567890123456789');
echo $stream; // 0123456789
```


## FnStream

`GuzzleHttp\Psr7\FnStream`

Compose stream implementations based on a hash of callables.

Allows for easy testing and extension of a provided stream without needing
to create a concrete class for a simple extension point.

```php

use GuzzleHttp\Psr7;

$stream = Psr7\Utils::streamFor('hi');
$fnStream = Psr7\FnStream::decorate($stream, [
    'rewind' => function () use ($stream) {
        echo 'About to rewind - ';
        $stream->rewind();
        echo 'rewound!';
    }
]);

$fnStream->rewind();
// Outputs: About to rewind - rewound!
```


## InflateStream

`GuzzleHttp\Psr7\InflateStream`

Uses PHP's zlib.inflate filter to inflate zlib (HTTP deflate, RFC1950) or gzipped (RFC1952) content.

This stream decorator converts the provided stream to a PHP stream resource,
then appends the zlib.inflate filter. The stream is then converted back
to a Guzzle stream resource to be used as a Guzzle stream.


## LazyOpenStream

`GuzzleHttp\Psr7\LazyOpenStream`

Lazily reads or writes to a file that is opened only after an IO operation
take place on the stream.

```php
use GuzzleHttp\Psr7;

$stream = new Psr7\LazyOpenStream('/path/to/file', 'r');
// The file has not yet been opened...

echo $stream->read(10);
// The file is opened and read from only when needed.
```


## LimitStream

`GuzzleHttp\Psr7\LimitStream`

LimitStream can be used to read a subset or slice of an existing stream object.
This can be useful for breaking a large file into smaller pieces to be sent in
chunks (e.g. Amazon S3's multipart upload API).

```php
use GuzzleHttp\Psr7;

$original = Psr7\Utils::streamFor(fopen('/tmp/test.txt', 'r+'));
echo $original->getSize();
// >>> 1048576

// Limit the size of the body to 1024 bytes and start reading from byte 2048
$stream = new Psr7\LimitStream($original, 1024, 2048);
echo $stream->getSize();
// >>> 1024
echo $stream->tell();
// >>> 0
```


## MultipartStream

`GuzzleHttp\Psr7\MultipartStream`

Stream that when read returns bytes for a streaming multipart or
multipart/form-data stream.

Each multipart element must contain a `name` and `contents` key. `contents` may
be any non-array value accepted by `GuzzleHttp\Psr7\Utils::streamFor()`,
including closures and invokable objects. Array contents are recursively
expanded into nested form fields.


## NoSeekStream

`GuzzleHttp\Psr7\NoSeekStream`

NoSeekStream wraps a stream and does not allow seeking.

```php
use GuzzleHttp\Psr7;

$original = Psr7\Utils::streamFor('foo');
$noSeek = new Psr7\NoSeekStream($original);

echo $noSeek->read(3);
// foo
var_export($noSeek->isSeekable());
// false
$noSeek->seek(0);
var_export($noSeek->read(3));
// NULL
```


## PumpStream

`GuzzleHttp\Psr7\PumpStream`

Provides a read only stream that pumps data from a PHP callable.

When invoking the provided callable, the PumpStream will pass the suggested
number of bytes to read to the callable. The callable can choose to ignore
this value and return fewer or more bytes than requested. Any extra data
returned by the provided callable is buffered internally until drained using
the read() function of the PumpStream. The provided callable MUST return a
non-empty string to provide data, and MUST return false or null when there is
no more data to read. Returning an empty string causes a RuntimeException
because it cannot satisfy a positive-length read.

Userland callables that declare no parameters are tolerated by PHP, but
length-aware callables remain the recommended formal shape.


## Implementing Stream Decorators

Creating a stream decorator is very easy thanks to the
`GuzzleHttp\Psr7\StreamDecoratorTrait`. This trait provides methods that
implement `Psr\Http\Message\StreamInterface` by proxying to an underlying
stream. Just `use` the `StreamDecoratorTrait` and implement your custom
methods.

For example, let's say we wanted to call a specific function each time the last
byte is read from a stream. This could be implemented by overriding the
`read()` method.

```php
use Psr\Http\Message\StreamInterface;
use GuzzleHttp\Psr7\StreamDecoratorTrait;

class EofCallbackStream implements StreamInterface
{
    use StreamDecoratorTrait;

    private $callback;

    private $stream;

    public function __construct(StreamInterface $stream, callable $cb)
    {
        $this->stream = $stream;
        $this->callback = $cb;
    }

    public function read(int $length): string
    {
        $result = $this->stream->read($length);

        // Invoke the callback when EOF is hit.
        if ($this->eof()) {
            ($this->callback)();
        }

        return $result;
    }
}
```

This decorator could be added to any existing stream and used like so:

```php
use GuzzleHttp\Psr7;

$original = Psr7\Utils::streamFor('foo');

$eofStream = new EofCallbackStream($original, function () {
    echo 'EOF!';
});

$eofStream->read(2);
$eofStream->read(1);
// echoes "EOF!"
$eofStream->seek(0);
$eofStream->read(3);
// echoes "EOF!"
```


## PHP StreamWrapper

You can use the `GuzzleHttp\Psr7\StreamWrapper` class if you need to use a
PSR-7 stream as a PHP stream resource.

Use the `GuzzleHttp\Psr7\StreamWrapper::getResource()` method to create a PHP
stream from a PSR-7 stream.

```php
use GuzzleHttp\Psr7\StreamWrapper;

$stream = GuzzleHttp\Psr7\Utils::streamFor('hello!');
$resource = StreamWrapper::getResource($stream);
echo fread($resource, 6); // outputs hello!
```
