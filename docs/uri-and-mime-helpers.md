# URI and MIME Helpers

This page covers small helper methods for redacting URI user info, converting values into URI objects, and resolving MIME types from filenames or extensions. For URI resolution, normalization, and comparison helpers, see [URI Helpers](uri-helpers.md).

## `GuzzleHttp\Psr7\Utils::redactUserInfo`

`public static function redactUserInfo(UriInterface $uri): UriInterface`

Redact the user info part of a URI.

## `GuzzleHttp\Psr7\Utils::uriFor`

`public static function uriFor(string|UriInterface $uri): UriInterface`

Returns a `UriInterface` for the given value.

This function accepts a string or `UriInterface` and returns a `UriInterface` for the given value. If the value is already a `UriInterface`, it is returned as-is.

## `GuzzleHttp\Psr7\MimeType::fromFilename`

`public static function fromFilename(string $filename): string|null`

Determines the MIME type of a file by looking at its extension.

## `GuzzleHttp\Psr7\MimeType::fromExtension`

`public static function fromExtension(string $extension): string|null`

Maps a file extension to a MIME type.

## Related

- [URI Helpers](uri-helpers.md)
- [Header and Query Helpers](header-and-query-helpers.md)
- [Stream Helpers](stream-helpers.md)
