# PSR-7 Messages

This package provides PSR-7 request, response, server request, uploaded file, URI, and stream implementations. Use these objects when you need HTTP messages that can move between Guzzle, PSR-18 clients, PSR-15 middleware, and other PSR-7 compatible libraries.

## Requests

```php
use GuzzleHttp\Psr7\Request;

$request = new Request('GET', 'https://example.com/users/123', [
    'Accept' => 'application/json',
]);

echo $request->getMethod();
echo $request->getUri();
```

PSR-7 messages are immutable. Methods such as `withHeader()` and `withUri()` return a modified copy.

```php
$jsonRequest = $request->withHeader('Accept', 'application/json');
```

## Responses

```php
use GuzzleHttp\Psr7\Response;

$response = new Response(200, ['Content-Type' => 'application/json'], '{"ok":true}');

echo $response->getStatusCode();
echo $response->getHeaderLine('Content-Type');
echo $response->getBody();
```

## URIs

```php
use GuzzleHttp\Psr7\Uri;

$uri = new Uri('https://example.com/users?active=1');

echo $uri->getHost();
echo $uri->getQuery();
```

For URI-specific helper methods, see [URI Helpers](uri.md).

## HTTP Method Casing

HTTP method names are case-sensitive in PSR-7. Requests created explicitly with
`Request`, `ServerRequest`, `withMethod()`, `Message::parseRequest()`, or the
PSR-17 factories preserve the method string as provided. `ServerRequest::fromGlobals()`
normalizes `$_SERVER['REQUEST_METHOD']` to uppercase for compatibility when
hydrating requests from PHP server globals.
