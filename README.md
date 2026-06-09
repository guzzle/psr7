# Guzzle PSR-7

`guzzlehttp/psr7` is a PSR-7 HTTP message implementation for PHP. It provides request, response, URI, uploaded file, and stream objects that work with Guzzle and any other library using the PSR-7 interfaces.

Use this package directly when you need to create or inspect PSR-7 messages without sending HTTP requests. If you only want to make HTTP requests, install [`guzzlehttp/guzzle`](https://github.com/guzzle/guzzle) instead; it already depends on this package.

## Installation

```bash
composer require guzzlehttp/psr7
```

## Version Guidance

| Version | Status       | PHP Version  |
|---------|--------------|--------------|
| 3.x     | Experimental | >=7.4,<8.6   |
| 2.x     | Latest       | >=7.2.5,<8.6 |
| 1.x     | End of Life  | >=5.4,<8.2   |

## Quick Start

```php
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Utils;

$request = new Request('GET', 'https://example.com/api');
$response = new Response(200, ['Content-Type' => 'text/plain'], 'OK');
$stream = Utils::streamFor('request or response body');

echo $request->getMethod();
echo $response->getStatusCode();
echo $stream;
```

PSR-7 objects are immutable. Methods such as `withHeader()` and `withUri()` return a changed copy instead of modifying the original object.

```php
$request = $request->withHeader('Accept', 'application/json');
```

## Documentation

- [Full documentation](docs/index.md)
- [Streams and decorators](docs/index.md#appendstream)
- [Static API helpers](docs/index.md#static-api)
- [Additional URI methods](docs/index.md#additional-uri-methods)
- [Upgrade guide](UPGRADING.md)

## Security

If you discover a security vulnerability within this package, please send an email to security@tidelift.com. All security vulnerabilities will be promptly addressed. Please do not disclose security-related issues publicly until a fix has been announced. Please see [Security Policy](https://github.com/guzzle/psr7/security/policy) for more information.

## License

Guzzle is made available under the MIT License (MIT). Please see [License File](LICENSE) for more information.

## For Enterprise

Available as part of the Tidelift Subscription

The maintainers of Guzzle and thousands of other packages are working with Tidelift to deliver commercial support and maintenance for the open source dependencies you use to build your applications. Save time, reduce risk, and improve code health, while paying the maintainers of the exact dependencies you use. [Learn more.](https://tidelift.com/subscription/pkg/packagist-guzzlehttp-psr7?utm_source=packagist-guzzlehttp-psr7&utm_medium=referral&utm_campaign=enterprise&utm_term=repo)
