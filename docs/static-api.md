# Static API Helpers

This package includes static helper classes for common message, header, query string, stream, and MIME-type operations.

## `GuzzleHttp\Psr7\Message::toString`

Converts a PSR-7 message to an HTTP message string.

## `GuzzleHttp\Psr7\Message::bodySummary`

Returns a short body preview for debugging and exception messages.

## `GuzzleHttp\Psr7\Message::rewindBody`

Attempts to rewind a message body stream and throws when the body cannot be rewound.

## `GuzzleHttp\Psr7\Message::parseMessage`

Parses a raw HTTP message string into headers and body parts.

## `GuzzleHttp\Psr7\Message::parseRequestUri`

Parses the request-target from a raw HTTP request line.

## `GuzzleHttp\Psr7\Message::parseRequest`

Parses a raw HTTP request string into a PSR-7 request.

## `GuzzleHttp\Psr7\Message::parseResponse`

Parses a raw HTTP response string into a PSR-7 response.

## `GuzzleHttp\Psr7\Header::parse`

Parses header parameters into structured values.

## `GuzzleHttp\Psr7\Header::splitList`

Splits HTTP list headers according to the list parsing rules used by HTTP specifications.

## `GuzzleHttp\Psr7\Header::normalize` (Deprecated)

`Header::normalize()` is deprecated in favor of [`Header::splitList()`](#guzzlehttppsr7headersplitlist), which performs the same operation with a cleaned up API and improved documentation.

## `GuzzleHttp\Psr7\Query::parse`

Parses a query string into an array.

## `GuzzleHttp\Psr7\Query::build`

Builds a query string from an array.

## `GuzzleHttp\Psr7\Utils::caselessRemove`

Removes keys from an array using case-insensitive matching.

## `GuzzleHttp\Psr7\Utils::copyToStream`

Copies bytes from one stream to another.

## `GuzzleHttp\Psr7\Utils::copyToString`

Copies bytes from a stream into a string.

## `GuzzleHttp\Psr7\Utils::hash`

Calculates a hash of stream contents.

## `GuzzleHttp\Psr7\Utils::modifyRequest`

Returns a request with multiple changes applied at once.

## `GuzzleHttp\Psr7\Utils::readLine`

Reads a single line from a stream.

## `GuzzleHttp\Psr7\Utils::redactUserInfo`

Returns a URI string with user-info credentials redacted.

## `GuzzleHttp\Psr7\Utils::streamFor`

Creates a stream from common PHP values. See [Streams and decorators](streams.md) for examples.

## `GuzzleHttp\Psr7\Utils::tryFopen`

Opens a file and throws a runtime exception with context when `fopen()` fails.

## `GuzzleHttp\Psr7\Utils::tryGetContents`

Reads a stream resource and throws a runtime exception with context when reading fails.

## `GuzzleHttp\Psr7\Utils::uriFor`

Creates a PSR-7 URI from a string or existing URI instance.

## `GuzzleHttp\Psr7\MimeType::fromFilename`

Returns a MIME type based on a file name extension.

## `GuzzleHttp\Psr7\MimeType::fromExtension`

Returns a MIME type for an extension.
