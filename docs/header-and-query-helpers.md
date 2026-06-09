# Header and Query Helpers

This page covers helper methods for parsing structured header values, splitting list headers, and parsing or building query strings. For basic message header behavior, see [PSR-7 Messages](psr-7-messages.md).

## `GuzzleHttp\Psr7\Header::parse`

`public static function parse(string|array $header): array`

Parses semicolon-separated header parameters into associative arrays. Parameters without values receive an empty-string value.

## `GuzzleHttp\Psr7\Header::splitList`

`public static function splitList(string|string[] $header): string[]`

Splits an HTTP header defined to contain a comma-separated list into each individual value:

```php
$knownEtags = Header::splitList($request->getHeader('if-none-match'));
```

Example headers include `accept`, `cache-control`, and `if-none-match`.

## `GuzzleHttp\Psr7\Query::parse`

`public static function parse(string $str, int|bool $urlEncoding = true): array`

Parse a query string into an associative array.

If multiple values are found for the same key, the value of that key-value pair becomes an array. This function does not parse nested PHP style arrays into an associative array. For example, `foo[a]=1&foo[b]=2` will be parsed into `['foo[a]' => '1', 'foo[b]' => '2']`.

## `GuzzleHttp\Psr7\Query::build`

`public static function build(array $params, int|false $encoding = PHP_QUERY_RFC3986, bool $treatBoolsAsInts = true): string`

Build a query string from an array of key-value pairs.

This function can use the return value of `parse()` to build a query string. This function does not modify the provided keys when an array is encountered, unlike `http_build_query()`.

## `GuzzleHttp\Psr7\Utils::caselessRemove`

`public static function caselessRemove(array $keys, array $data): array`

Remove the items given by the keys from the data, case-insensitively.

## Related

- [PSR-7 Messages](psr-7-messages.md)
- [Message Helpers](message-helpers.md)
- [URI Helpers](uri-helpers.md)
