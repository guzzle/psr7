# Static API Helpers and PSR-17 Factories

This page covers utility APIs in the `GuzzleHttp\Psr7` namespace: static helpers for messages, headers, queries, streams, URIs, and MIME types, plus the package's PSR-17 `HttpFactory` implementation. For conceptual message, stream, and URI behavior, see the related pages at the end.


## `GuzzleHttp\Psr7\HttpFactory`

`GuzzleHttp\Psr7\HttpFactory` implements all PSR-17 factory interfaces: `RequestFactoryInterface`, `ResponseFactoryInterface`, `ServerRequestFactoryInterface`, `StreamFactoryInterface`, `UploadedFileFactoryInterface`, and `UriFactoryInterface`.

Use it when code expects PSR-17 factories and you want this package's PSR-7 implementations.

```php
use GuzzleHttp\Psr7\HttpFactory;

$factory = new HttpFactory();

$request = $factory->createRequest('GET', 'https://example.com');
$response = $factory->createResponse(200);
$serverRequest = $factory->createServerRequest('POST', '/submit', ['REMOTE_ADDR' => '192.0.2.1']);
$stream = $factory->createStream('body');
$uri = $factory->createUri('https://example.com/path');
```

It also creates streams from files and resources, and uploaded files from streams.

```php
$stream = $factory->createStreamFromFile('/path/to/file.txt', 'r');
$upload = $factory->createUploadedFile($stream, $stream->getSize(), UPLOAD_ERR_OK, 'file.txt', 'text/plain');
```


## `GuzzleHttp\Psr7\Message::toString`

`public static function toString(MessageInterface $message): string`

Returns the string representation of an HTTP message.

```php
$request = new GuzzleHttp\Psr7\Request('GET', 'http://example.com');
echo GuzzleHttp\Psr7\Message::toString($request);
```


## `GuzzleHttp\Psr7\Message::bodySummary`

`public static function bodySummary(MessageInterface $message, ?int $truncateAt = null): string|null`

Get a short summary of the message body.

Will return `null` if the response is not printable.

Reads seekable bodies from the beginning and restores the original cursor
position before returning. Pass `null` for `$truncateAt` to use the default
summary length.


## `GuzzleHttp\Psr7\Message::rewindBody`

`public static function rewindBody(MessageInterface $message): void`

Attempts to rewind a message body and throws an exception on failure.

The body of the message will only be rewound if a call to `tell()`
returns a value other than `0`.


## `GuzzleHttp\Psr7\Message::parseMessage`

`public static function parseMessage(string $message): array`

Parses an HTTP message into an associative array.

The array contains the "start-line" key containing the start line of
the message, "headers" key containing an associative array of header
array values, and a "body" key containing the body of the message.


## `GuzzleHttp\Psr7\Message::parseRequestUri`

`public static function parseRequestUri(string $path, array $headers): string`

Constructs a URI for an HTTP request message.


## `GuzzleHttp\Psr7\Message::parseRequest`

`public static function parseRequest(string $message): Request`

Parses a request message string into a request object.


## `GuzzleHttp\Psr7\Message::parseResponse`

`public static function parseResponse(string $message): Response`

Parses a response message string into a response object.


## `GuzzleHttp\Psr7\Header::parse`

`public static function parse(string|array $header): array`

Parse an array of header values containing ";" separated data into an
array of associative arrays representing the header key-value pair data
of the header. When a parameter does not contain a value, but just
contains a key, this function will inject a key with a '' string value.


## `GuzzleHttp\Psr7\Header::splitList`

`public static function splitList(string|string[] $header): string[]`

Splits an HTTP header defined to contain a comma-separated list into
each individual value:

```
$knownEtags = Header::splitList($request->getHeader('if-none-match'));
```

Example headers include `accept`, `cache-control` and `if-none-match`.


## `GuzzleHttp\Psr7\Header::normalize` (Deprecated)

`public static function normalize(string|array $header): array`

`Header::normalize()` is deprecated in favor of [`Header::splitList()`](#guzzlehttppsr7headersplitlist)
which performs the same operation with a cleaned-up API and improved
documentation.

Converts an array of header values that may contain comma-separated
headers into an array of headers with no comma-separated values.


## `GuzzleHttp\Psr7\Query::parse`

`public static function parse(string $str, int|bool $urlEncoding = true): array`

Parse a query string into an associative array.

If multiple values are found for the same key, the value of that
key-value pair becomes an array. This function does not parse nested
PHP style arrays into an associative array (e.g., `foo[a]=1&foo[b]=2`
will be parsed into `['foo[a]' => '1', 'foo[b]' => '2'])`.


## `GuzzleHttp\Psr7\Query::build`

`public static function build(array $params, int|false $encoding = PHP_QUERY_RFC3986, bool $treatBoolsAsInts = true): string`

Build a query string from an array of key-value pairs.

This function can use the return value of `parse()` to build a query
string. This function does not modify the provided keys when an array is
encountered (like `http_build_query()` would).


## `GuzzleHttp\Psr7\Utils::caselessRemove`

`public static function caselessRemove(array $keys, array $data): array`

Remove the items given by the keys from the data, case-insensitively.


## `GuzzleHttp\Psr7\Utils::copyToStream`

`public static function copyToStream(StreamInterface $source, StreamInterface $dest, int $maxLen = -1): int`

Copy the contents of a stream into another stream until the given number of
bytes have been read, returning the number of bytes copied as an `int`. On
32-bit PHP, an unbounded copy larger than `PHP_INT_MAX` bytes cannot be
represented by that return type. 64-bit PHP is not affected.

The destination must accept writes that make positive progress. Streams that
return 0 as a backpressure or drop signal — a `BufferStream` past its high water
mark, or a full `DroppingStream` — will cause this method to throw. For full
copies, use a normal writable stream such as a file or `php://temp` stream.

Throws `GuzzleHttp\Psr7\Exception\TimeoutException` when PHP-style timeout
metadata can be detected after a source read or destination write cannot make
progress.


## `GuzzleHttp\Psr7\Utils::copyToString`

`public static function copyToString(StreamInterface $stream, int $maxLen = -1): string`

Copy the contents of a stream into a string until the given number of
bytes have been read.

Throws `GuzzleHttp\Psr7\Exception\TimeoutException` when PHP-style timeout
metadata can be detected after a stream read cannot make progress.


## `GuzzleHttp\Psr7\Utils::hash`

`public static function hash(StreamInterface $stream, string $algo, bool $rawOutput = false): string`

Calculate a hash of a stream.

This method reads the entire stream to calculate a rolling hash, based on
PHP's `hash_init` functions.

Throws `GuzzleHttp\Psr7\Exception\TimeoutException` when PHP-style timeout
metadata can be detected after a stream read cannot make progress.


## `GuzzleHttp\Psr7\Utils::modifyRequest`

`public static function modifyRequest(RequestInterface $request, array $changes): RequestInterface`

Clone and modify a request with the given changes.

This method is useful for reducing the number of clones needed to mutate
a message.

- method: (string) Changes the HTTP method.
- set_headers: (array) Sets the given headers. Values must be strings or arrays
  of strings.
- remove_headers: (array) Remove the given headers. Values may be strings or
  integers.
- body: (mixed) Sets the given body. Present non-null values are converted with
  `GuzzleHttp\Psr7\Utils::streamFor()`, including scalar values, resources,
  streams, iterators, callable arrays, closures, invokable objects, and
  stringable objects. String inputs remain literal bodies.
- uri: (UriInterface) Set the URI.
- query: (string) Set the query string value of the URI.
- version: (string) Set the protocol version.


## `GuzzleHttp\Psr7\Utils::readLine`

`public static function readLine(StreamInterface $stream, ?int $maxLength = null): string`

Read a line from the stream up to the maximum allowed buffer length.

Throws `GuzzleHttp\Psr7\Exception\TimeoutException` when PHP-style timeout
metadata can be detected after a stream read cannot make progress.


## `GuzzleHttp\Psr7\Utils::redactUserInfo`

`public static function redactUserInfo(UriInterface $uri): UriInterface`

Redact the user info part of a URI.


## `GuzzleHttp\Psr7\Utils::streamFor`

`public static function streamFor(resource|string|null|int|float|bool|StreamInterface|callable|\Iterator|\Stringable $resource = '', array $options = []): StreamInterface`

Create a new stream based on the input type.

Options are provided as an associative array that can contain the following keys:

- metadata: Array of custom metadata.
- size: Size of the stream.

This method accepts the following `$resource` types:

- `Psr\Http\Message\StreamInterface`: Returns the value as-is.
- `string`: Creates a stream object that uses the given string as the contents.
- `resource`: Creates a stream object that wraps the given PHP stream resource.
- `Iterator`: If the provided value implements `Iterator`, then a read-only
  stream object will be created that wraps the given iterable. Each time the
  stream is read from, data from the iterator will fill a buffer and will be
  continuously called until the buffer is equal to the requested read size.
  Values that stringify to an empty string are skipped while the iterator
  advances. Subsequent read calls will first read from the buffer and then call
  `next` on the underlying iterator until it is exhausted.
- `object` with `__toString()`: If the object has the `__toString()` method,
  the object will be cast to a string and then a stream will be returned that
  uses the string value.
- `NULL`: When `null` is passed, an empty stream object is returned.
- `callable`: When a callable array, closure, or invokable object is passed and
  no earlier resource or object rule applies, a read-only stream object will be
  created that invokes the given callable. The callable is invoked with the
  suggested number of bytes to read. The callable can return fewer or more bytes
  than requested, but MUST return a non-empty string to provide data and MUST
  return `false` or `null` when there is no more data to return. Any additional
  bytes will be buffered and used in subsequent reads. String inputs are always
  treated as string bodies, even when they name callable functions.

```php
$stream = GuzzleHttp\Psr7\Utils::streamFor('foo');
$stream = GuzzleHttp\Psr7\Utils::streamFor(fopen('/path/to/file', 'r'));

$generator = function ($bytes) {
    for ($i = 0; $i < $bytes; $i++) {
        yield ' ';
    }
};

$stream = GuzzleHttp\Psr7\Utils::streamFor($generator(100));
```


## `GuzzleHttp\Psr7\Utils::tryFopen`

`public static function tryFopen(string $filename, string $mode): resource`

Safely opens a PHP stream resource using a filename.

When fopen fails, PHP normally raises a warning. This function adds an
error handler that checks for errors and throws an exception instead.


## `GuzzleHttp\Psr7\Utils::tryGetContents`

`public static function tryGetContents(resource $stream): string`

Safely gets the contents of a given stream.

When stream_get_contents fails, PHP normally raises a warning. This
function adds an error handler that checks for errors and throws an
exception instead.

Throws `GuzzleHttp\Psr7\Exception\TimeoutException` when PHP-style timeout
metadata can be detected after the stream read cannot make progress.


## `GuzzleHttp\Psr7\Utils::uriFor`

`public static function uriFor(string|UriInterface $uri): UriInterface`

Returns a `UriInterface` for the given value.

This function accepts a string or `UriInterface` and returns a
`UriInterface` for the given value. If the value is already a
`UriInterface`, it is returned as-is.


## `GuzzleHttp\Psr7\MimeType::fromFilename`

`public static function fromFilename(string $filename): string|null`

Determines the MIME type of a file by looking at its extension.


## `GuzzleHttp\Psr7\MimeType::fromExtension`

`public static function fromExtension(string $extension): string|null`

Maps a file extension to a MIME type.


## Related

- [PSR-7 Messages](psr-7-messages.md)
- [Streams and Decorators](streams.md)
- [URI Helpers](uri.md)
