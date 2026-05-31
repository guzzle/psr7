Guzzle PSR-7 Upgrade Guide
==========================

2.x to 3.0
----------

Guzzle PSR-7 3.0 is a major release that raises the minimum PHP version,
updates to the PSR-7 v2 interfaces, validates header values more strictly,
preserves explicit request method casing, and rejects several invalid URI,
request, response, upload, query, stream, and multipart values that 2.x
previously accepted or cast.

#### PHP Version and Dependencies

Guzzle PSR-7 3.0 requires PHP `^7.4 || ^8.0`. Guzzle PSR-7 2.x supported PHP
`^7.2.5 || ^8.0`.

If your application still supports PHP 7.2 or 7.3, continue using Guzzle PSR-7
2.x until your minimum PHP version is raised.

Guzzle PSR-7 3.0 requires `psr/http-message:^2.0` and
`psr/http-factory:^1.1`. Guzzle PSR-7 2.x supported
`psr/http-message:^1.1 || ^2.0` and `psr/http-factory:^1.0`. If your dependency
constraints pin `psr/http-message` to v1, update them before upgrading.

Guzzle PSR-7 no longer depends on `ralouphie/getallheaders` and no longer
provides a transitive global `getallheaders()` polyfill.
`ServerRequest::fromGlobals()` continues to collect request headers internally.
Applications that call `getallheaders()` directly on SAPIs where PHP does not
provide it should require their own polyfill.

#### Header Values

Header values must now be strings or non-empty arrays of strings. Empty strings
remain valid explicit header values, but empty arrays, `null`, `false`, integers,
floats, and other non-string values are no longer cast or accepted.

```php
// 2.x, no longer accepted in 3.0
$response = $response->withHeader('Api-Version', 1);
$response = $response->withHeader('Empty-List', []);

// 3.0
$response = $response->withHeader('Api-Version', '1');
$response = $response->withHeader('Empty-Value', '');
```

Use `withoutHeader()` to remove a header.

#### Request Method Casing

Request methods passed explicitly to `Request`, `ServerRequest`, `withMethod()`,
`Message::parseRequest()`, and the PSR-17 factories are no longer uppercased.
PSR-7 treats method names as case-sensitive, so these APIs now preserve the
method exactly as provided. If your application requires uppercase methods,
normalize methods before constructing or modifying requests.

`ServerRequest::fromGlobals()` is the compatibility-oriented exception. It
continues to uppercase string `REQUEST_METHOD` values read from PHP server
globals, matching Guzzle PSR-7 2.x and common server request behavior. This
normalization only applies when hydrating from globals; it does not apply to
methods passed explicitly to constructors, factories, or `withMethod()`.

```php
// 2.x
$request = new Request('get', '/');
$request->getMethod(); // GET

// 3.0
$request = new Request('get', '/');
$request->getMethod(); // get

// 3.0, server globals
$_SERVER['REQUEST_METHOD'] = 'post';
$request = ServerRequest::fromGlobals();
$request->getMethod(); // POST
```

#### Native PSR-7 Parameter Types

Guzzle PSR-7 3.0 requires the argument types documented by PSR-7 more strictly.
It adds the native parameter types from `psr/http-message` v2. Code passing
invalid argument types may now receive PHP `TypeError` exceptions instead of
package-specific `InvalidArgumentException` exceptions or implicit casts.

Native parameter type changes include:

- `MessageInterface::withProtocolVersion()` now requires `string`.
- Message header names now require `string`.
- `RequestInterface::withRequestTarget()` and `withMethod()` now require `string`.
- `RequestInterface::withUri()` now requires `bool` for `$preserveHost`.
- `ResponseInterface::withStatus()` now requires `int` status codes and `string` reason phrases.
- Server request attribute names now require `string`.
- `UriInterface::withPort()` now requires `int|null`.
- URI scheme, user info, host, path, query, and fragment mutators now require strings.
- Stream `seek()`, `read()`, `write()`, and `getMetadata()` now require their PSR-7 v2 parameter types.
- `UploadedFileInterface::moveTo()` now requires a string target path.

Update callers to pass values of the documented type before calling these
methods:

```php
// 2.x, no longer supported in 3.0
$response = $response->withStatus('201');
$uri = $uri->withPort('8080');

// 3.0
$response = $response->withStatus(201);
$uri = $uri->withPort(8080);
```

#### Request Modification Changes

`Utils::modifyRequest()` now validates recognized change values before applying
request modifications. Unknown change keys are still ignored. Explicit `null`
values are no longer treated as omitted recognized changes; omit the key instead.

Recognized change values must use the documented types:

- `method`: `string`
- `uri`: `UriInterface`
- `query`: `string`
- `version`: `string`
- `body`: `resource|string|int|float|bool|StreamInterface|callable|\Iterator|\Stringable`
- `set_headers`: `array<array-key, string|non-empty-array<array-key, string>>`
- `remove_headers`: `array<array-key, string|int>`

#### Uploaded Files

`ServerRequestInterface::withUploadedFiles()` now rejects invalid nested upload
trees. Every leaf must be an `UploadedFileInterface` instance.

`ServerRequest::normalizeFiles()` and `ServerRequest::fromGlobals()` now reject
malformed `$_FILES` specifications earlier. Single-file specifications must
contain non-null `tmp_name`, `size`, and `error` values. Single-file and nested
file `size` values and `error` values must be non-negative PHP integers;
numeric strings are no longer cast. If PHP supplies an upload size as a string
because the byte count cannot fit in `PHP_INT_MAX`, it is rejected rather than
truncated or cast. Nested specifications must provide `tmp_name`, `size`, and
`error` as arrays with matching keys. When nested `name` or `type` metadata is
provided, it must also be an array.

If your tests or adapters build `$_FILES` arrays manually, populate the full
shape or create `UploadedFile` instances directly.

```php
// 2.x, no longer accepted in 3.0
$files = ['file' => ['tmp_name' => '/tmp/php123', 'error' => '0']];

// 3.0
$files = ['file' => ['tmp_name' => '/tmp/php123', 'size' => 123, 'error' => UPLOAD_ERR_OK]];
```

For stream-backed uploads, `UploadedFile::moveTo()` now rewinds seekable streams
before copying them. If application code reads from a seekable uploaded stream
before calling `moveTo()`, 3.0 writes the full stream contents to the target
instead of only the unread suffix. Non-seekable stream-backed uploads continue to
copy from their current position because consumed bytes cannot be replayed.

#### Parsed Body Values

`ServerRequestInterface::withParsedBody()` now rejects values other than
`array`, `object`, or `null`.

```php
// 2.x, no longer accepted in 3.0
$request = $request->withParsedBody('name=value');

// 3.0
$request = $request->withParsedBody(['name' => 'value']);
```

#### URI Host and Scheme Validation

URI hosts containing URI delimiters, backslashes, embedded ports passed to
`withHost()`, malformed IP-literal brackets, unbracketed IPv6, or other
malformed host forms are no longer accepted. URI schemes containing whitespace
or control characters are also no longer accepted.

If you previously passed a host and port together to `withHost()`, split them
between `withHost()` and `withPort()`:

```php
// 2.x, no longer accepted in 3.0
$uri = $uri->withHost('example.com:8080');

// 3.0
$uri = $uri->withHost('example.com')->withPort(8080);
```

Normal URI strings with ports are still supported:

```php
$uri = new Uri('https://example.com:8080/path');
```

`Uri::fromParts()` accepts integer and decimal digit string ports, but floats and
other port values are no longer cast.

Common host forms such as `localhost`, single-label hosts, underscores, Unicode
hosts, valid IPv6 literals, and normal host and port URI strings remain
supported.

URI schemes must now match RFC 3986 syntax and begin with a letter.

```php
// 2.x, no longer accepted in 3.0
$uri = (new Uri())->withScheme('0');

// 3.0
$uri = (new Uri())->withScheme('https');
```

The stricter validation also applies when a request is created or modified from
a custom `UriInterface` implementation and its host is used to generate or
update a `Host` header.

`ServerRequest::getUriFromGlobals()` now falls back to `SERVER_NAME`, then
`SERVER_ADDR`, then the existing default host behavior for malformed
`HTTP_HOST` values. It also rejects zero-port `HTTP_HOST` authorities and
malformed `SERVER_PORT` values when fallback authority reconstruction needs the
server port. When `REQUEST_URI` is absolute-form or CONNECT authority-form and
supplies a valid authority, that authority is used before fallback `SERVER_PORT`
validation. Origin-form, asterisk-form, missing `REQUEST_URI`, and fallback
reconstruction paths still reject malformed `SERVER_PORT` values when fallback
authority reconstruction needs the server port.

Absolute-form `REQUEST_URI` userinfo is removed when reconstructing the URI and
request target from globals, including empty userinfo such as
`http://@example.com/`. The host after the last raw `@` remains the URI host.

`Message::parseRequest()` now applies 3.0 authority rules when deriving a URI
from an origin-form or asterisk-form request target. Host ports with leading
zeroes are normalized for URI reconstruction, and port zero is rejected.
It also rejects duplicate `Host` field lines, including case-insensitive
duplicates. Any present raw `Host` field is validated before returning a parsed
request, even when the request target supplies the URI authority, such as
absolute-form and CONNECT requests. Valid `Host` values may still differ from
the absolute-form or CONNECT request-target authority.

For server globals, applications that need to reject malformed inbound `Host`
headers should validate the original server parameters before calling
`getUriFromGlobals()` or inspect them afterward.

#### Request Host Synchronization

`Request::withUri()` now applies PSR-7 Host header synchronization before using
the same-URI no-op shortcut. When the provided URI is the same object already
attached to the request, the method may still return a new request if the URI
has a host and the current Host header is missing, empty, or stale.

With `$preserveHost = true`, a non-empty Host header is still preserved. Missing
or empty Host headers are treated as absent and are populated from the URI when
the URI contains a host.

```php
$request = (new Request('GET', 'http://example.com:8124/'))->withoutHeader('Host');

$updated = $request->withUri($request->getUri());

$updated->getHeaderLine('Host'); // example.com:8124
```

If your application intentionally sends an empty or stale Host header, set it
after calling `withUri()` or preserve a non-empty Host header explicitly.

`Message::toString()` now applies the same URI host and port synthesis when
serializing a request without a `Host` header. Generated `Host` lines include
non-null URI ports.

#### URI Paths and Request Targets

`Uri::getPath()` now normalizes multiple leading slashes to one slash when
returning the path in isolation. Casting the URI to string still preserves the
original URI representation.

```php
$uri = new Uri('http://example.org//valid///path');

$uri->getPath(); // /valid///path
(string) $uri;   // http://example.org//valid///path
```

`Request::getRequestTarget()` applies the same normalization for URI-derived
origin-form request targets.

#### HTTP Start-line Parsing

`Message::parseRequest()` and `Message::parseResponse()` now validate HTTP
start-line fields more strictly. Malformed request methods, request targets
containing whitespace or control characters, malformed protocol versions,
invalid response status codes, invalid response spacing, and reason phrases
containing invalid control characters now throw `InvalidArgumentException`.

`Request` and `Response` constructors and mutators apply the same validation to
protocol versions, request targets, status codes, and reason phrases. If you
parse raw HTTP messages or construct messages from partially validated input,
normalize or reject invalid values before passing them to Guzzle PSR-7.

```php
// 2.x-style tolerant input, no longer accepted in 3.0
Message::parseRequest("GET /foo bar HTTP/1.1\r\nHost: example.com\r\n\r\n");
new Response(200, [], null, 'HTTP/1.1');

// 3.0
Message::parseRequest("GET /foo%20bar HTTP/1.1\r\nHost: example.com\r\n\r\n");
new Response(200, [], null, '1.1');
```

#### Query Builder Values

`Query::build()` now rejects unsupported values instead of relying on PHP string
casts. Query values must be scalar, `null`, stringable objects, or flat arrays of
those values.

Nested arrays, resources, and objects without `__toString()` now throw
`InvalidArgumentException`.

```php
// Before: could produce warnings or silently mangle the value.
Query::build(['filter' => ['name' => ['value']]]);

// After: use explicit query keys for nested query shapes.
Query::build(['filter[name]' => 'value']);
```

Flat arrays are still supported for repeated query parameters:

```php
Query::build(['tag' => ['a', 'b']]);
// tag=a&tag=b
```

#### PumpStream Source Callables

`PumpStream` source callables must now return a non-empty string when producing
data. Returning an empty string now throws `RuntimeException` instead of being
retried indefinitely. Return `false` or `null` to signal EOF.

If your callable used `''` to mean "temporarily no data", update it to wait
until data is available, return a non-empty string, or return `false` or `null`
when the stream is complete.

#### Iterator-backed Streams

`Utils::streamFor()` now validates values yielded by `Iterator` instances before
passing them to the internal `PumpStream`. Scalar values, `null`, and stringable
objects are converted to string chunks. Arrays, resources, and non-stringable
objects now throw `UnexpectedValueException`.

Iterator exhaustion is now the only EOF signal for iterator-backed streams.
Yielding `false`, `null`, or an empty string no longer ends the stream; those
values are zero-length chunks and are skipped while the iterator advances. If
your iterator yielded `false` or `null` to stop streaming, update it to finish
iteration instead.

Avoid iterators that yield only zero-length chunks indefinitely. Such iterators
never produce bytes and never reach EOF, so they cannot satisfy stream reads.

```php
// Before: yielding false or null could stop an iterator-backed stream early.
$stream = Utils::streamFor(new ArrayIterator([false, 'body']));

// After: false and null are skipped chunks. End the iterator to signal EOF.
$stream = Utils::streamFor(new ArrayIterator(['body']));
```

#### Stream Mode Capabilities

`Stream::isReadable()` and `Stream::isWritable()` now follow PHP stream mode
semantics more closely. Update modes are detected by the presence of `+`,
including valid modes such as `rt+`, `wt+`, `at+`, `xt+`, and `ct+`.

Literal `rw` metadata is now treated as read-only, matching PHP real-file
streams. If a custom stream wrapper previously exposed `rw` for a writable
resource, open it with a valid update mode such as `r+`, `w+`, or `a+` instead.

#### Stream Copy Behavior

Stream sizes, offsets, high-water marks, and byte counts are now validated as
non-negative PHP integers where applicable. Operations that would overflow
`PHP_INT_MAX` throw `OverflowException` instead of silently wrapping or producing
an invalid position or size.

`Utils::copyToStream()` now returns the number of bytes copied and throws a
`RuntimeException` when the destination stream cannot make progress, for example
a `BufferStream` at its high-water mark or a full `DroppingStream`. Its
signature changed from `: void` to `: int`, but callers that ignore the return
value do not need to change anything. In 2.x, the copy stopped silently when the
destination could not make progress. For a guaranteed full copy, use a normal
writable stream such as a file or `php://temp` stream.

#### Stream Timeout Detection

Timed-out stream operations now throw
`GuzzleHttp\Psr7\Exception\TimeoutException`, which extends
`RuntimeException`. `Stream::read()`, `Stream::write()`,
`Utils::copyToStream()`, `Utils::copyToString()`, `Utils::hash()`,
`Utils::readLine()`, and `Utils::tryGetContents()` detect PHP-style stream
timeout metadata when a read or write operation cannot make progress. Timeout
detection is best-effort; custom stream implementations that do not expose
`timed_out` metadata continue to behave as before. Previously, timed-out reads
could be treated as EOF or return partial results, and timed-out writes could be
reported as generic write failures or no-progress writes.

#### Message Body Summaries

`Message::bodySummary()` still summarizes seekable bodies from the beginning,
even when the body was already partially read. It now restores the body cursor to
the position it had before the summary was created. In 2.x, calling
`bodySummary()` left seekable bodies rewound to the beginning. The optional
`$truncateAt` argument now accepts `null` as an explicit request for the default
summary length, matching the behavior of omitting the argument.

Most applications do not need to change anything. Check your code only if you
called `bodySummary()` and then read the same body while relying on
`bodySummary()` to leave the body rewound. If you need to read the body from the
beginning after summarizing it, call `Message::rewindBody()` explicitly.

#### Stream Lifecycle

`FnStream` now treats `close()` and successful `detach()` calls as terminal
lifecycle operations. Its configured `close` callback is invoked at most once;
repeated `close()` calls are no-ops, destruction after explicit close no longer
invokes the close callback, and closed or detached streams no longer forward
read, write, seek, metadata, or stringification callbacks. `FnStream` also
suppresses exceptions thrown by destructor-triggered close callbacks. Call
`close()` explicitly if cleanup failures must be observed.

`CachingStream::close()` is now idempotent. Calling `close()` after `detach()`
still closes the remote stream owned by the `CachingStream`, but it no longer
closes the detached cache resource returned to the caller. Repeated `close()`
calls are no-ops.

`PumpStream::close()` and `PumpStream::detach()` now discard internally buffered
unread bytes. If a callable or iterator source returns more bytes than a read
requested, drain the stream before closing it if you need those buffered bytes.

#### Multipart Part Headers and Metadata

`MultipartStream` no longer adds default `Content-Length` headers to individual
`multipart/form-data` parts. RFC 7578 section 4.8 says multipart form-data
parts must not include `Content-*` headers other than the supported multipart
part headers, so 3.0 stops generating per-part `Content-Length` by default.

If your tests compare raw multipart payloads, remove the generated
`Content-Length` lines from expected strings:

```text
// 2.x generated:
--boundary\r\n
Content-Disposition: form-data; name="foo"\r\n
Content-Length: 3\r\n
\r\n
bar\r\n

// 3.0 generates:
--boundary\r\n
Content-Disposition: form-data; name="foo"\r\n
\r\n
bar\r\n
```

Applications can still pass an explicit `Content-Length` header in a multipart
element's `headers` array if a non-standard peer requires it:

```php
$body = new MultipartStream([
    [
        'name' => 'foo',
        'contents' => 'bar',
        'headers' => ['Content-Length' => '3'],
    ],
]);
```

`MultipartStream` now escapes generated `Content-Disposition` `name` and
`filename` parameters before serializing multipart part headers. Double quotes,
carriage returns, and line feeds are encoded as `%22`, `%0D`, and `%0A`. Literal
backslashes and other characters are serialized unchanged, matching browser
multipart form submission behavior.

```php
// Before: these values were interpolated into the generated part header.
$body = new MultipartStream([
    [
        'name' => "field\"\r\nname",
        'filename' => "avatar\"\r\n.txt",
        'contents' => 'body',
    ],
]);

// After: the generated Content-Disposition parameters contain
// field%22%0D%0Aname and avatar%22%0D%0A.txt.
```

Explicit custom boundaries are now validated using RFC 2046 multipart boundary
syntax. Omit the boundary or pass `null` to continue using a generated random
boundary.

Custom multipart part header names and values are also validated before
serialization. Header names must be valid HTTP tokens, and header values must be
strings without CR, LF, or other invalid control bytes.

`MultipartStream` now preserves trailing spaces and tabs in custom multipart
part header values when serializing the body. In 2.x, the final serialized part
header line was trimmed as a side effect of removing the generated header
terminator. Normal multipart parsers treat this optional whitespace as
insignificant, but tests, signatures, or snapshots that compare raw multipart
body bytes may need updated expectations.

#### URI Userinfo Redaction

`Utils::redactUserInfo()` now redacts all non-empty URI userinfo, including
username-only userinfo. In 2.x, it only redacted the password portion when
userinfo contained a password delimiter.

```php
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\Utils;

// 2.x: https://TOKEN@example.com
// 3.0: https://***@example.com
(string) Utils::redactUserInfo(new Uri('https://TOKEN@example.com'));

// 2.x: https://user:***@example.com
// 3.0: https://***@example.com
(string) Utils::redactUserInfo(new Uri('https://user:pass@example.com'));
```

#### Non-instantiable Utility Classes

Static utility and constant classes such as `Header`, `Message`, `MimeType`,
`Query`, `Rfc7230`, and `Utils` now have private constructors. Replace any
accidental instantiation with static method calls or constant access.

1.x to 2.0
----------

Guzzle PSR-7 2.0 is a major release that removes deprecated APIs, raises the
minimum PHP version, and adds PHP 7 parameter and return types. Applications that
only depend on PSR-7 interfaces should usually need small changes. Applications
that call helper functions, extend package classes, or pass invalid argument
types need closer review.

#### PHP Version and Dependencies

Guzzle PSR-7 2.0 requires PHP `^7.2.5 || ^8.0`. Guzzle PSR-7 1.x supported PHP
`>=5.4.0`.

Composer dependency changes that can affect upgrades:

- `ralouphie/getallheaders` v2 support was dropped; 2.0 requires `^3.0`.
- `psr/http-factory:^1.0` is required because 2.0 ships PSR-17 factories through `GuzzleHttp\Psr7\HttpFactory`.

#### PHP 7 Type Hints and Return Types

Type hints and return types were added wherever possible. Please make sure:

- You pass values of the documented type when calling methods and functions.
- Classes that extend Guzzle PSR-7 classes update any overridden method signatures to remain compatible.
- Code that expected package-specific `InvalidArgumentException` exceptions for invalid argument types may now receive PHP `TypeError` exceptions instead.

Common examples include passing a real integer status code to `Response::__construct()` and passing a string method to `Request::__construct()`.

#### Removed Function API

The static API was introduced in 1.7.0 to mitigate problems with functions
conflicting between global and local copies of the package. The function API was
removed in 2.0.0, along with the Composer `files` autoload entry that loaded
`src/functions_include.php`.

Replace namespaced function calls with the corresponding static methods in the
`GuzzleHttp\Psr7` namespace:

```php
// Before:
use function GuzzleHttp\Psr7\stream_for;

$stream = stream_for('body');

// After:
use GuzzleHttp\Psr7\Utils;

$stream = Utils::streamFor('body');
```

| Original Function | Replacement Method |
|-------------------|--------------------|
| `str` | `Message::toString` |
| `uri_for` | `Utils::uriFor` |
| `stream_for` | `Utils::streamFor` |
| `parse_header` | `Header::parse` |
| `normalize_header` | `Header::normalize` |
| `modify_request` | `Utils::modifyRequest` |
| `rewind_body` | `Message::rewindBody` |
| `try_fopen` | `Utils::tryFopen` |
| `copy_to_string` | `Utils::copyToString` |
| `copy_to_stream` | `Utils::copyToStream` |
| `hash` | `Utils::hash` |
| `readline` | `Utils::readLine` |
| `parse_request` | `Message::parseRequest` |
| `parse_response` | `Message::parseResponse` |
| `parse_query` | `Query::parse` |
| `build_query` | `Query::build` |
| `mimetype_from_filename` | `MimeType::fromFilename` |
| `mimetype_from_extension` | `MimeType::fromExtension` |
| `_parse_message` | `Message::parseMessage` |
| `_parse_request_uri` | `Message::parseRequestUri` |
| `get_message_body_summary` | `Message::bodySummary` |
| `_caseless_remove` | `Utils::caselessRemove` |

`Header::normalize()` remains the direct 2.0 replacement for
`normalize_header()`. In newer 2.x versions, prefer `Header::splitList()` for
new code.

#### Deprecated URI Methods Removed

The deprecated `Uri::resolve()` and `Uri::removeDotSegments()` methods were
removed. Use `UriResolver` instead.

```php
// Before:
$resolved = Uri::resolve($base, '../path');
$path = Uri::removeDotSegments('/a/../b');

// After:
use GuzzleHttp\Psr7\UriResolver;
use GuzzleHttp\Psr7\Utils;

$resolved = UriResolver::resolve($base, Utils::uriFor('../path'));
$path = UriResolver::removeDotSegments('/a/../b');
```

#### Stricter URI Validation

Guzzle PSR-7 1.x automatically fixed a URI that combined an authority with a
relative path by prepending `/` to the path. That deprecated behavior was removed
in 2.0. Such URIs now throw `InvalidArgumentException`.

```php
// Before: automatically converted to //example.com/foo.
$uri = (new Uri())->withHost('example.com')->withPath('foo');

// After: make the absolute path explicit.
$uri = (new Uri())->withHost('example.com')->withPath('/foo');
```

#### Header Validation

Header names are validated more strictly according to RFC 7230 token syntax.
Names containing whitespace, `/`, `(`, `)`, `\\`, or other invalid characters are
rejected.

If you construct messages from untrusted or non-standard input, normalize or
reject invalid header names before constructing `Request`, `Response`, or
`ServerRequest` instances.

#### Query String Boolean Serialization

`Query::build()` now serializes booleans as `1` and `0`, matching
`http_build_query()` behavior.

```php
Query::build(['enabled' => true, 'disabled' => false]);
// enabled=1&disabled=0
```

In current 2.x versions, pass `false` as the third argument if you need textual
boolean values:

```php
Query::build(['enabled' => true, 'disabled' => false], PHP_QUERY_RFC3986, false);
// enabled=true&disabled=false
```

#### Final Stream and Decorator Classes

Several classes that were annotated with `@final` in 1.x are declared `final` in
2.0:

- `AppendStream`
- `BufferStream`
- `CachingStream`
- `DroppingStream`
- `FnStream`
- `InflateStream`
- `LazyOpenStream`
- `LimitStream`
- `MultipartStream`
- `NoSeekStream`
- `PumpStream`
- `StreamWrapper`

If your code extends one of these classes, replace inheritance with composition.
For custom streams, implement `Psr\Http\Message\StreamInterface` directly or use
`GuzzleHttp\Psr7\StreamDecoratorTrait` in your own class.

`Request`, `Response`, `ServerRequest`, `Stream`, `UploadedFile`, and `Uri` remain
extendable in 2.0, but overridden methods must have compatible signatures.

#### Public Constants and Internal Details

Some constants that were public in 1.x are implementation details in 2.0:

- `Stream::READABLE_MODES`
- `Stream::WRITABLE_MODES`
- `Uri::HTTP_DEFAULT_HOST`

If your code used these constants, define application-specific constants instead
of depending on package internals.

#### Stream Behavior Changes

`BufferStream::write()` returns `0` instead of `false` when the buffer exceeds
its high-water mark. This keeps the method compatible with the `int` return type
from `StreamInterface::write()`.

All stream implementations now reject negative `read()` lengths with
`RuntimeException`. In 2.x, some decorators passed negative lengths through,
some returned sliced data, and some behavior varied by PHP version.

`LimitStream` now rejects negative offsets and limits below `-1`. For
non-seekable streams, offsets are tracked by the number of bytes actually
skipped. Short reads are retried until the offset is reached, EOF is reached, or
the decorated stream stops making progress.

`StreamWrapper` now translates `RuntimeException` failures from the wrapped
PSR-7 stream into PHP stream-wrapper failure values. When using a resource from
`StreamWrapper::getResource()`, functions such as `fread()`, `fwrite()`,
`fseek()`, `feof()`, and `fstat()` may now return normal PHP failure values
instead of propagating the PSR-7 stream exception. Call the PSR-7 stream directly
if you need exception-based failure handling.

The `StreamWrapper::stream_read()` callback no longer declares a native return
type so read failures can return `false`. The `StreamWrapper::stream_tell()`
callback no longer declares a native return type so post-seek position lookup
failures can make `fseek()` fail.

Several stream `__toString()` implementations now allow exceptions thrown during
stringification to be rethrown. Avoid relying on `(string) $stream` to hide read
failures; call `getContents()` or `read()` and handle exceptions when failures
are possible.

#### PSR-17 Factories

Guzzle PSR-7 2.0 adds `GuzzleHttp\Psr7\HttpFactory`, an implementation of the
PSR-17 factory interfaces from `psr/http-factory`. This is additive, but it is
the reason for the new required dependency.

For the full 2.0 diff, see
https://github.com/guzzle/psr7/compare/1.8.1...2.0.0.
