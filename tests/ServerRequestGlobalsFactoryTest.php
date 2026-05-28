<?php

declare(strict_types=1);

namespace GuzzleHttp\Tests\Psr7;

use GuzzleHttp\Psr7\ServerRequestGlobalsFactory;
use GuzzleHttp\Psr7\UploadedFile;
use PHPUnit\Framework\TestCase;

/**
 * @covers \GuzzleHttp\Psr7\ServerRequestGlobalsFactory
 */
class ServerRequestGlobalsFactoryTest extends TestCase
{
    public function testFromArraysHydratesRequestWithoutMutatingSuperglobals(): void
    {
        $_SERVER = [
            'REQUEST_METHOD' => 'DELETE',
            'REQUEST_URI' => '/global',
            'HTTP_HOST' => 'global.example',
        ];
        $_GET = ['global' => 'query'];
        $_POST = ['global' => 'post'];
        $_COOKIE = ['global' => 'cookie'];
        $_FILES = [];

        $serverGlobals = $_SERVER;
        $queryGlobals = $_GET;
        $postGlobals = $_POST;
        $cookieGlobals = $_COOKIE;
        $fileGlobals = $_FILES;

        $server = [
            'REQUEST_METHOD' => 'post',
            'REQUEST_URI' => '/local?foo=bar',
            'HTTP_HOST' => 'local.example',
            'SERVER_PROTOCOL' => 'HTTP/2',
            'CONTENT_TYPE' => 'application/json',
        ];

        $request = ServerRequestGlobalsFactory::fromArrays(
            $server,
            ['foo' => 'bar'],
            ['name' => 'Pesho'],
            ['sid' => 'abc'],
            [
                'file' => [
                    'name' => 'MyFile.txt',
                    'type' => 'text/plain',
                    'tmp_name' => '/tmp/php/php1h4j1o',
                    'error' => UPLOAD_ERR_OK,
                    'size' => 123,
                ],
            ]
        );

        self::assertSame('POST', $request->getMethod());
        self::assertSame('2', $request->getProtocolVersion());
        self::assertSame('http://local.example/local?foo=bar', (string) $request->getUri());
        self::assertSame('/local?foo=bar', $request->getRequestTarget());
        self::assertSame('local.example', $request->getHeaderLine('Host'));
        self::assertSame('application/json', $request->getHeaderLine('Content-Type'));
        self::assertSame($server, $request->getServerParams());
        self::assertSame(['foo' => 'bar'], $request->getQueryParams());
        self::assertSame(['name' => 'Pesho'], $request->getParsedBody());
        self::assertSame(['sid' => 'abc'], $request->getCookieParams());
        self::assertEquals([
            'file' => new UploadedFile(
                '/tmp/php/php1h4j1o',
                123,
                UPLOAD_ERR_OK,
                'MyFile.txt',
                'text/plain'
            ),
        ], $request->getUploadedFiles());

        self::assertSame($serverGlobals, $_SERVER);
        self::assertSame($queryGlobals, $_GET);
        self::assertSame($postGlobals, $_POST);
        self::assertSame($cookieGlobals, $_COOKIE);
        self::assertSame($fileGlobals, $_FILES);
    }

    public function testGetUriFromServerParamsIgnoresSuperglobal(): void
    {
        $_SERVER = [
            'REQUEST_URI' => '/global',
            'HTTP_HOST' => 'global.example',
        ];

        $uri = ServerRequestGlobalsFactory::getUriFromServerParams([
            'REQUEST_URI' => '/local?foo=bar',
            'HTTP_HOST' => 'local.example',
        ]);

        self::assertSame('http://local.example/local?foo=bar', (string) $uri);
    }

    public function testFromArraysBuildsHeadersFromServerWhenHeaderProviderIsMissing(): void
    {
        $request = ServerRequestGlobalsFactory::fromArrays(
            [
                'REQUEST_URI' => '/',
                'HTTP_HOST' => 'www.example.org',
                'HTTP_ACCEPT_LANGUAGE' => 'en-US',
                'HTTP_CONTENT_TYPE' => 'ignored/content-type',
                'HTTP_CONTENT_LENGTH' => '999',
                'HTTP_CONTENT_MD5' => 'ignored-content-md5',
                'CONTENT_TYPE' => 'application/json',
                'CONTENT_LENGTH' => '14',
                'CONTENT_MD5' => 'Q2hlY2sgSW50ZWdyaXR5IQ==',
                'HTTP_X_EMPTY' => '',
            ],
            [],
            [],
            [],
            []
        );

        self::assertSame(['www.example.org'], $request->getHeader('Host'));
        self::assertSame(['en-US'], $request->getHeader('Accept-Language'));
        self::assertSame(['application/json'], $request->getHeader('Content-Type'));
        self::assertSame(['14'], $request->getHeader('Content-Length'));
        self::assertSame(['Q2hlY2sgSW50ZWdyaXR5IQ=='], $request->getHeader('Content-Md5'));
        self::assertSame([''], $request->getHeader('X-Empty'));
    }

    public function testFromArraysPrefersProvidedHeadersOverServerHeaders(): void
    {
        $request = ServerRequestGlobalsFactory::fromArrays(
            [
                'REQUEST_URI' => '/',
                'HTTP_HOST' => 'server.example',
                'HTTP_X_FALLBACK' => 'fallback',
            ],
            [],
            [],
            [],
            [],
            static function (): array {
                return [
                    'Host' => 'native.example',
                    'X-Native' => 'native',
                    'X-Int' => 123,
                    'X-False' => false,
                    'X-Array' => ['bad'],
                    'X-Object' => new \stdClass(),
                ];
            }
        );

        self::assertSame('server.example', $request->getUri()->getHost());
        self::assertSame('native.example', $request->getHeaderLine('Host'));
        self::assertSame('native', $request->getHeaderLine('X-Native'));
        self::assertSame('123', $request->getHeaderLine('X-Int'));
        self::assertSame('', $request->getHeaderLine('X-False'));
        self::assertFalse($request->hasHeader('X-Array'));
        self::assertFalse($request->hasHeader('X-Object'));
        self::assertFalse($request->hasHeader('X-Fallback'));
    }

    public function testFromArraysFallsBackToServerHeadersWhenHeaderProviderReturnsNonArray(): void
    {
        $request = ServerRequestGlobalsFactory::fromArrays(
            [
                'REQUEST_URI' => '/',
                'HTTP_HOST' => 'www.example.org',
                'HTTP_X_FALLBACK' => 'fallback',
            ],
            [],
            [],
            [],
            [],
            static function () {
                return false;
            }
        );

        self::assertSame(['fallback'], $request->getHeader('X-Fallback'));
    }

    public function testFromArraysDropsInvalidProvidedHostHeaderWhenUriFallsBack(): void
    {
        $request = ServerRequestGlobalsFactory::fromArrays(
            [
                'REQUEST_URI' => '/',
                'HTTP_HOST' => 'bad.example:443:8443',
                'SERVER_NAME' => 'good.example',
                'SERVER_PORT' => '443',
                'HTTPS' => 'on',
            ],
            [],
            [],
            [],
            [],
            static function (): array {
                return [
                    'Host' => 'bad.example:443:8443',
                    'X-Native' => 'native',
                ];
            }
        );

        self::assertSame('good.example', $request->getUri()->getHost());
        self::assertSame('good.example', $request->getHeaderLine('Host'));
        self::assertSame(['native'], $request->getHeader('X-Native'));
    }

    /**
     * @dataProvider authorizationHeaderFromServerProvider
     */
    public function testFromArraysBuildsAuthorizationHeaderFromServerFallback(array $serverParams, string $expected): void
    {
        $request = ServerRequestGlobalsFactory::fromArrays(
            array_merge([
                'REQUEST_URI' => '/',
                'HTTP_HOST' => 'www.example.org',
            ], $serverParams),
            [],
            [],
            [],
            []
        );

        self::assertSame([$expected], $request->getHeader('Authorization'));
    }

    public static function authorizationHeaderFromServerProvider(): iterable
    {
        yield 'HTTP_AUTHORIZATION has priority' => [
            [
                'HTTP_AUTHORIZATION' => 'Bearer direct',
                'REDIRECT_HTTP_AUTHORIZATION' => 'Bearer redirect',
                'PHP_AUTH_USER' => 'user',
                'PHP_AUTH_PW' => 'pass',
                'PHP_AUTH_DIGEST' => 'Digest digest',
            ],
            'Bearer direct',
        ];

        yield 'REDIRECT_HTTP_AUTHORIZATION fallback' => [
            [
                'REDIRECT_HTTP_AUTHORIZATION' => 'Bearer redirect',
                'PHP_AUTH_USER' => 'user',
                'PHP_AUTH_PW' => 'pass',
                'PHP_AUTH_DIGEST' => 'Digest digest',
            ],
            'Bearer redirect',
        ];

        yield 'PHP_AUTH_USER fallback' => [
            [
                'PHP_AUTH_USER' => 'user',
                'PHP_AUTH_PW' => 'pass',
                'PHP_AUTH_DIGEST' => 'Digest digest',
            ],
            'Basic '.base64_encode('user:pass'),
        ];

        yield 'PHP_AUTH_DIGEST fallback' => [
            [
                'PHP_AUTH_DIGEST' => 'Digest digest',
            ],
            'Digest digest',
        ];
    }

    /**
     * @dataProvider requestTargetFormsProvider
     */
    public function testFromArraysRequestTargetForms(
        array $serverParams,
        string $expectedRequestTarget,
        string $expectedUri
    ): void {
        $request = ServerRequestGlobalsFactory::fromArrays($serverParams, [], [], [], []);

        self::assertSame($expectedRequestTarget, $request->getRequestTarget());
        self::assertSame($expectedUri, (string) $request->getUri());
    }

    public static function requestTargetFormsProvider(): iterable
    {
        yield 'slashless origin-form is normalized' => [
            ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => 'admin?x=1', 'HTTP_HOST' => 'good.example'],
            '/admin?x=1',
            'http://good.example/admin?x=1',
        ];

        yield 'absolute-form target is preserved' => [
            ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => 'http://up.example:8080/admin?x=1', 'HTTP_HOST' => 'good.example'],
            'http://up.example:8080/admin?x=1',
            'http://up.example:8080/admin?x=1',
        ];

        yield 'absolute-form userinfo target is stripped' => [
            ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => 'http://user:pass@evil.example/admin', 'HTTP_HOST' => 'good.example', 'SERVER_PORT' => '+443'],
            'http://evil.example/admin',
            'http://evil.example/admin',
        ];

        yield 'asterisk-form target is preserved' => [
            ['REQUEST_METHOD' => 'OPTIONS', 'REQUEST_URI' => '*', 'HTTP_HOST' => 'good.example'],
            '*',
            'http://good.example',
        ];

        yield 'connect authority-form target is preserved' => [
            ['REQUEST_METHOD' => 'CONNECT', 'REQUEST_URI' => 'up.example:443', 'HTTP_HOST' => 'good.example'],
            'up.example:443',
            'http://up.example:443',
        ];

        yield 'query string is used when request uri is missing' => [
            ['REQUEST_METHOD' => 'GET', 'QUERY_STRING' => 'x=1', 'HTTP_HOST' => 'good.example'],
            '/?x=1',
            'http://good.example?x=1',
        ];
    }
}
