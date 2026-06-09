# URI Helpers

The `GuzzleHttp\Psr7\Uri` helpers provide URI classification, composition, resolution, comparison, and normalization behavior on top of the PSR-7 URI interface.

## URI Types

`GuzzleHttp\Psr7\Uri` includes helpers that identify common URI-reference forms:

- `Uri::isAbsolute()`
- `Uri::isNetworkPathReference()`
- `Uri::isAbsolutePathReference()`
- `Uri::isRelativePathReference()`
- `Uri::isSameDocumentReference()`

## URI Components

Use the URI component helpers to inspect or modify URI parts:

- `Uri::isDefaultPort()` checks whether a port is the default for the scheme.
- `Uri::composeComponents()` builds a URI string from individual components.
- `Uri::fromParts()` creates a URI from parsed parts.
- `Uri::withQueryValue()` returns a URI with one query value set.
- `Uri::withQueryValues()` returns a URI with multiple query values set.
- `Uri::withoutQueryValue()` returns a URI with a query value removed.

```php
use GuzzleHttp\Psr7\Uri;

$uri = new Uri('https://example.com/search?q=old');
$uri = Uri::withQueryValue($uri, 'q', 'new');

echo $uri;
// https://example.com/search?q=new
```

## Cross-Origin Detection

`GuzzleHttp\Psr7\UriComparator::isCrossOrigin()` checks whether two URIs have different origin components.

## Reference Resolution

`GuzzleHttp\Psr7\UriResolver` resolves and relativizes URI references according to RFC 3986.

- `UriResolver::resolve()` resolves a URI reference against a base URI.
- `UriResolver::removeDotSegments()` removes `.` and `..` path segments.
- `UriResolver::relativize()` returns a relative reference from one URI to another when possible.

```php
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriResolver;

$base = new Uri('https://example.com/a/b/');
$target = new Uri('../c');

echo UriResolver::resolve($base, $target);
// https://example.com/a/c
```

## Normalization and Comparison

`GuzzleHttp\Psr7\UriNormalizer` normalizes and compares URIs using RFC 3986 normalization rules.

- `UriNormalizer::normalize()` returns a normalized URI.
- `UriNormalizer::isEquivalent()` checks whether two URIs are equivalent after normalization.

Normalization options include capitalizing percent encodings, decoding unreserved characters, removing default ports, removing dot segments, removing duplicate slashes, and sorting query parameters. Some normalizations may change URI semantics, so choose flags carefully.
