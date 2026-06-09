# URI Helpers

Aside from the standard `Psr\Http\Message\UriInterface` implementation in form of the `GuzzleHttp\Psr7\Uri` class,
this library also provides additional functionality when working with URIs as static methods.

## URI Types

An instance of `Psr\Http\Message\UriInterface` can either be an absolute URI or a relative reference.
An absolute URI has a scheme. A relative reference is used to express a URI relative to another URI,
the base URI. Relative references can be divided into several forms according to
[RFC 3986 Section 4.2](https://datatracker.ietf.org/doc/html/rfc3986#section-4.2):

- network-path references, e.g. `//example.com/path`
- absolute-path references, e.g. `/path`
- relative-path references, e.g. `subpath`

The following methods can be used to identify the type of the URI.

### `GuzzleHttp\Psr7\Uri::isAbsolute`

`public static function isAbsolute(UriInterface $uri): bool`

Whether the URI is absolute, i.e. it has a scheme.

### `GuzzleHttp\Psr7\Uri::isNetworkPathReference`

`public static function isNetworkPathReference(UriInterface $uri): bool`

Whether the URI is a network-path reference. A relative reference that begins with two slash characters is
termed an network-path reference.

### `GuzzleHttp\Psr7\Uri::isAbsolutePathReference`

`public static function isAbsolutePathReference(UriInterface $uri): bool`

Whether the URI is a absolute-path reference. A relative reference that begins with a single slash character is
termed an absolute-path reference.

### `GuzzleHttp\Psr7\Uri::isRelativePathReference`

`public static function isRelativePathReference(UriInterface $uri): bool`

Whether the URI is a relative-path reference. A relative reference that does not begin with a slash character is
termed a relative-path reference.

### `GuzzleHttp\Psr7\Uri::isSameDocumentReference`

`public static function isSameDocumentReference(UriInterface $uri, ?UriInterface $base = null): bool`

Whether the URI is a same-document reference. A same-document reference refers to a URI that is, aside from its
fragment component, identical to the base URI. When no base URI is given, only an empty URI reference
(apart from its fragment) is considered a same-document reference.

## URI Components

Additional methods to work with URI components.

### `GuzzleHttp\Psr7\Uri::isDefaultPort`

`public static function isDefaultPort(UriInterface $uri): bool`

Whether the URI has the default port of the current scheme. `Psr\Http\Message\UriInterface::getPort` may return null
or the standard port. This method can be used independently of the implementation.

### `GuzzleHttp\Psr7\Uri::composeComponents`

`public static function composeComponents($scheme, $authority, $path, $query, $fragment): string`

Composes a URI reference string from its various components according to
[RFC 3986 Section 5.3](https://datatracker.ietf.org/doc/html/rfc3986#section-5.3). Usually this method does not need
to be called manually but instead is used indirectly via `Psr\Http\Message\UriInterface::__toString`.

### `GuzzleHttp\Psr7\Uri::fromParts`

`public static function fromParts(array $parts): UriInterface`

Creates a URI from a hash of [`parse_url`](https://www.php.net/manual/en/function.parse-url.php) components.


### `GuzzleHttp\Psr7\Uri::withQueryValue`

`public static function withQueryValue(UriInterface $uri, $key, $value): UriInterface`

Creates a new URI with a specific query string value. Any existing query string values that exactly match the
provided key are removed and replaced with the given key value pair. A value of null will set the query string
key without a value, e.g. "key" instead of "key=value".

### `GuzzleHttp\Psr7\Uri::withQueryValues`

`public static function withQueryValues(UriInterface $uri, array $keyValueArray): UriInterface`

Creates a new URI with multiple query string values. It has the same behavior as `withQueryValue()` but for an
associative array of key => value.

### `GuzzleHttp\Psr7\Uri::withoutQueryValue`

`public static function withoutQueryValue(UriInterface $uri, $key): UriInterface`

Creates a new URI with a specific query string value removed. Any existing query string values that exactly match the
provided key are removed.

## Cross-Origin Detection

`GuzzleHttp\Psr7\UriComparator` provides methods to determine if a modified URL should be considered cross-origin.

### `GuzzleHttp\Psr7\UriComparator::isCrossOrigin`

`public static function isCrossOrigin(UriInterface $original, UriInterface $modified): bool`

Determines if a modified URL should be considered cross-origin with respect to an original URL.

Two URLs are cross-origin when their scheme, host, or effective port differ. Host comparison is case-insensitive, and missing ports use the default port for `http` or `https`. Other schemes do not receive implicit default ports.

This helper only compares URI origins. It does not implement redirect handling or credential policy.

## Reference Resolution

`GuzzleHttp\Psr7\UriResolver` provides methods to resolve a URI reference in the context of a base URI according
to [RFC 3986 Section 5](https://datatracker.ietf.org/doc/html/rfc3986#section-5). This is for example also what web
browsers do when resolving a link in a website based on the current request URI.

### `GuzzleHttp\Psr7\UriResolver::resolve`

`public static function resolve(UriInterface $base, UriInterface $rel): UriInterface`

Converts the relative URI into a new URI that is resolved against the base URI.

### `GuzzleHttp\Psr7\UriResolver::removeDotSegments`

`public static function removeDotSegments(string $path): string`

Removes dot segments from a path and returns the new path according to
[RFC 3986 Section 5.2.4](https://datatracker.ietf.org/doc/html/rfc3986#section-5.2.4).

### `GuzzleHttp\Psr7\UriResolver::relativize`

`public static function relativize(UriInterface $base, UriInterface $target): UriInterface`

Returns the target URI as a relative reference from the base URI. This method is the counterpart to resolve():

```php
(string) $target === (string) UriResolver::resolve($base, UriResolver::relativize($base, $target))
```

One use-case is to use the current request URI as base URI and then generate relative links in your documents
to reduce the document size or offer self-contained downloadable document archives.

```php
$base = new Uri('http://example.com/a/b/');
echo UriResolver::relativize($base, new Uri('http://example.com/a/b/c'));  // prints 'c'.
echo UriResolver::relativize($base, new Uri('http://example.com/a/x/y'));  // prints '../x/y'.
echo UriResolver::relativize($base, new Uri('http://example.com/a/b/?q')); // prints '?q'.
echo UriResolver::relativize($base, new Uri('http://example.org/a/b/'));   // prints '//example.org/a/b/'.
```

## Normalization and Comparison

`GuzzleHttp\Psr7\UriNormalizer` provides methods to normalize and compare URIs according to
[RFC 3986 Section 6](https://datatracker.ietf.org/doc/html/rfc3986#section-6).

### `GuzzleHttp\Psr7\UriNormalizer::normalize`

`public static function normalize(UriInterface $uri, $flags = self::PRESERVING_NORMALIZATIONS): UriInterface`

Returns a normalized URI. The scheme and host component are already normalized to lowercase per PSR-7 UriInterface.
This methods adds additional normalizations that can be configured with the `$flags` parameter which is a bitmask
of normalizations to apply. The following normalizations are available:

- `UriNormalizer::PRESERVING_NORMALIZATIONS`

    Default normalizations which only include the ones that preserve semantics.

- `UriNormalizer::CAPITALIZE_PERCENT_ENCODING`

    All letters within a percent-encoding triplet (e.g., "%3A") are case-insensitive, and should be capitalized.

    Example: `http://example.org/a%c2%b1b` → `http://example.org/a%C2%B1b`

- `UriNormalizer::DECODE_UNRESERVED_CHARACTERS`

    Decodes percent-encoded octets of unreserved characters. For consistency, percent-encoded octets in the ranges of
    ALPHA (%41–%5A and %61–%7A), DIGIT (%30–%39), hyphen (%2D), period (%2E), underscore (%5F), or tilde (%7E) should
    not be created by URI producers and, when found in a URI, should be decoded to their corresponding unreserved
    characters by URI normalizers.

    Example: `http://example.org/%7Eusern%61me/` → `http://example.org/~username/`

- `UriNormalizer::CONVERT_EMPTY_PATH`

    Converts the empty path to "/" for http and https URIs.

    Example: `http://example.org` → `http://example.org/`

- `UriNormalizer::REMOVE_DEFAULT_HOST`

    Removes the default host of the given URI scheme from the URI. Only the "file" scheme defines the default host
    "localhost". All of `file:/myfile`, `file:///myfile`, and `file://localhost/myfile` are equivalent according to
    RFC 3986.

    Example: `file://localhost/myfile` → `file:///myfile`

- `UriNormalizer::REMOVE_DEFAULT_PORT`

    Removes the default port of the given URI scheme from the URI.

    Example: `http://example.org:80/` → `http://example.org/`

- `UriNormalizer::REMOVE_DOT_SEGMENTS`

    Removes unnecessary dot-segments. Dot-segments in relative-path references are not removed as it would
    change the semantics of the URI reference.

    Example: `http://example.org/../a/b/../c/./d.html` → `http://example.org/a/c/d.html`

- `UriNormalizer::REMOVE_DUPLICATE_SLASHES`

    Paths which include two or more adjacent slashes are converted to one. Webservers usually ignore duplicate slashes
    and treat those URIs equivalent. But in theory those URIs do not need to be equivalent. So this normalization
    may change the semantics. Encoded slashes (%2F) are not removed.

    Example: `http://example.org//foo///bar.html` → `http://example.org/foo/bar.html`

- `UriNormalizer::SORT_QUERY_PARAMETERS`

    Sort query parameters with their values in alphabetical order. However, the order of parameters in a URI may be
    significant (this is not defined by the standard). So this normalization is not safe and may change the semantics
    of the URI.

    Example: `?lang=en&article=fred` → `?article=fred&lang=en`

### `GuzzleHttp\Psr7\UriNormalizer::isEquivalent`

`public static function isEquivalent(UriInterface $uri1, UriInterface $uri2, $normalizations = self::PRESERVING_NORMALIZATIONS): bool`

Whether two URIs can be considered equivalent. Both URIs are normalized automatically before comparison with the given
`$normalizations` bitmask. The method also accepts relative URI references and returns true when they are equivalent.
This of course assumes they will be resolved against the same base URI. If this is not the case, determination of
equivalence or difference of relative references does not mean anything.
