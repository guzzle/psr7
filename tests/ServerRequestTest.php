<?php

declare(strict_types=1);

namespace GuzzleHttp\Tests\Psr7;

use GuzzleHttp\Psr7\ServerRequest;
use GuzzleHttp\Psr7\UploadedFile;
use GuzzleHttp\Psr7\Uri;
use PHPUnit\Framework\TestCase;

/**
 * @covers \GuzzleHttp\Psr7\ServerRequest
 */
class ServerRequestTest extends TestCase
{
    public static function dataNormalizeFiles(): iterable
    {
        return [
            'Single file' => [
                [
                    'file' => [
                        'name' => 'MyFile.txt',
                        'type' => 'text/plain',
                        'tmp_name' => '/tmp/php/php1h4j1o',
                        'error' => '0',
                        'size' => '123',
                    ],
                ],
                [
                    'file' => new UploadedFile(
                        '/tmp/php/php1h4j1o',
                        123,
                        UPLOAD_ERR_OK,
                        'MyFile.txt',
                        'text/plain'
                    ),
                ],
            ],
            'Empty file' => [
                [
                    'image_file' => [
                        'name' => '',
                        'type' => '',
                        'tmp_name' => '',
                        'error' => '4',
                        'size' => '0',
                    ],
                ],
                [
                    'image_file' => new UploadedFile(
                        '',
                        0,
                        UPLOAD_ERR_NO_FILE,
                        '',
                        ''
                    ),
                ],
            ],
            'Already Converted' => [
                [
                    'file' => new UploadedFile(
                        '/tmp/php/php1h4j1o',
                        123,
                        UPLOAD_ERR_OK,
                        'MyFile.txt',
                        'text/plain'
                    ),
                ],
                [
                    'file' => new UploadedFile(
                        '/tmp/php/php1h4j1o',
                        123,
                        UPLOAD_ERR_OK,
                        'MyFile.txt',
                        'text/plain'
                    ),
                ],
            ],
            'Already Converted array' => [
                [
                    'file' => [
                        new UploadedFile(
                            '/tmp/php/php1h4j1o',
                            123,
                            UPLOAD_ERR_OK,
                            'MyFile.txt',
                            'text/plain'
                        ),
                        new UploadedFile(
                            '',
                            0,
                            UPLOAD_ERR_NO_FILE,
                            '',
                            ''
                        ),
                    ],
                ],
                [
                    'file' => [
                        new UploadedFile(
                            '/tmp/php/php1h4j1o',
                            123,
                            UPLOAD_ERR_OK,
                            'MyFile.txt',
                            'text/plain'
                        ),
                        new UploadedFile(
                            '',
                            0,
                            UPLOAD_ERR_NO_FILE,
                            '',
                            ''
                        ),
                    ],
                ],
            ],
            'Multiple files' => [
                [
                    'text_file' => [
                        'name' => 'MyFile.txt',
                        'type' => 'text/plain',
                        'tmp_name' => '/tmp/php/php1h4j1o',
                        'error' => '0',
                        'size' => '123',
                    ],
                    'image_file' => [
                        'name' => '',
                        'type' => '',
                        'tmp_name' => '',
                        'error' => '4',
                        'size' => '0',
                    ],
                ],
                [
                    'text_file' => new UploadedFile(
                        '/tmp/php/php1h4j1o',
                        123,
                        UPLOAD_ERR_OK,
                        'MyFile.txt',
                        'text/plain'
                    ),
                    'image_file' => new UploadedFile(
                        '',
                        0,
                        UPLOAD_ERR_NO_FILE,
                        '',
                        ''
                    ),
                ],
            ],
            'Nested files' => [
                [
                    'file' => [
                        'name' => [
                            0 => 'MyFile.txt',
                            1 => 'Image.png',
                        ],
                        'type' => [
                            0 => 'text/plain',
                            1 => 'image/png',
                        ],
                        'tmp_name' => [
                            0 => '/tmp/php/hp9hskjhf',
                            1 => '/tmp/php/php1h4j1o',
                            2 => '/tmp/php/w0ensl4ar',
                        ],
                        'error' => [
                            0 => '0',
                            1 => '0',
                        ],
                        'size' => [
                            0 => '123',
                            1 => '7349',
                        ],
                    ],
                    'minimum_data' => [
                        'tmp_name' => [
                            0 => '/tmp/php/hp9hskjhf',
                        ],
                    ],
                    'nested' => [
                        'name' => [
                            'other' => 'Flag.txt',
                            'test' => [
                                0 => 'Stuff.txt',
                                1 => '',
                            ],
                        ],
                        'type' => [
                            'other' => 'text/plain',
                            'test' => [
                                0 => 'text/plain',
                                1 => '',
                            ],
                        ],
                        'tmp_name' => [
                            'other' => '/tmp/php/hp9hskjhf',
                            'test' => [
                                0 => '/tmp/php/asifu2gp3',
                                1 => '',
                            ],
                        ],
                        'error' => [
                            'other' => '0',
                            'test' => [
                                0 => '0',
                                1 => '4',
                            ],
                        ],
                        'size' => [
                            'other' => '421',
                            'test' => [
                                0 => '32',
                                1 => '0',
                            ],
                        ],
                    ],
                ],
                [
                    'file' => [
                        0 => new UploadedFile(
                            '/tmp/php/hp9hskjhf',
                            123,
                            UPLOAD_ERR_OK,
                            'MyFile.txt',
                            'text/plain'
                        ),
                        1 => new UploadedFile(
                            '/tmp/php/php1h4j1o',
                            7349,
                            UPLOAD_ERR_OK,
                            'Image.png',
                            'image/png'
                        ),
                        2 => new UploadedFile(
                            '/tmp/php/w0ensl4ar',
                            null,
                            UPLOAD_ERR_OK
                        ),
                    ],
                    'minimum_data' => [
                        0 => new UploadedFile(
                            '/tmp/php/hp9hskjhf',
                            0,
                            UPLOAD_ERR_OK
                        ),
                    ],
                    'nested' => [
                        'other' => new UploadedFile(
                            '/tmp/php/hp9hskjhf',
                            421,
                            UPLOAD_ERR_OK,
                            'Flag.txt',
                            'text/plain'
                        ),
                        'test' => [
                            0 => new UploadedFile(
                                '/tmp/php/asifu2gp3',
                                32,
                                UPLOAD_ERR_OK,
                                'Stuff.txt',
                                'text/plain'
                            ),
                            1 => new UploadedFile(
                                '',
                                0,
                                UPLOAD_ERR_NO_FILE,
                                '',
                                ''
                            ),
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @dataProvider dataNormalizeFiles
     */
    public function testNormalizeFiles($files, $expected): void
    {
        $result = ServerRequest::normalizeFiles($files);

        self::assertEquals($expected, $result);
    }

    public function testNormalizeFilesRaisesException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid value in files specification');
        ServerRequest::normalizeFiles(['test' => 'something']);
    }

    public static function dataGetUriFromGlobals(): iterable
    {
        $server = [
            'REQUEST_URI' => '/blog/article.php?id=10&user=foo',
            'SERVER_PORT' => '443',
            'SERVER_ADDR' => '217.112.82.20',
            'SERVER_NAME' => 'www.example.org',
            'SERVER_PROTOCOL' => 'HTTP/1.1',
            'REQUEST_METHOD' => 'POST',
            'QUERY_STRING' => 'id=10&user=foo',
            'DOCUMENT_ROOT' => '/path/to/your/server/root/',
            'HTTP_HOST' => 'www.example.org',
            'HTTPS' => 'on',
            'REMOTE_ADDR' => '193.60.168.69',
            'REMOTE_PORT' => '5390',
            'SCRIPT_NAME' => '/blog/article.php',
            'SCRIPT_FILENAME' => '/path/to/your/server/root/blog/article.php',
            'PHP_SELF' => '/blog/article.php',
        ];

        return [
            'HTTPS request' => [
                'https://www.example.org/blog/article.php?id=10&user=foo',
                $server,
            ],
            'HTTPS request with different on value' => [
                'https://www.example.org/blog/article.php?id=10&user=foo',
                array_merge($server, ['HTTPS' => '1']),
            ],
            'HTTP request' => [
                'http://www.example.org/blog/article.php?id=10&user=foo',
                array_merge($server, ['HTTPS' => 'off', 'SERVER_PORT' => '80']),
            ],
            'HTTP_HOST missing -> fallback to SERVER_NAME' => [
                'https://www.example.org/blog/article.php?id=10&user=foo',
                array_merge($server, ['HTTP_HOST' => null]),
            ],
            'HTTP_HOST and SERVER_NAME missing -> fallback to SERVER_ADDR' => [
                'https://217.112.82.20/blog/article.php?id=10&user=foo',
                array_merge($server, ['HTTP_HOST' => null, 'SERVER_NAME' => null]),
            ],
            'Query string with ?' => [
                'https://www.example.org/path?continue=https://example.com/path?param=1',
                array_merge($server, ['REQUEST_URI' => '/path?continue=https://example.com/path?param=1', 'QUERY_STRING' => '']),
            ],
            'No query String' => [
                'https://www.example.org/blog/article.php',
                array_merge($server, ['REQUEST_URI' => '/blog/article.php', 'QUERY_STRING' => '']),
            ],
            'Host header with port' => [
                'https://www.example.org:8324/blog/article.php?id=10&user=foo',
                array_merge($server, ['HTTP_HOST' => 'www.example.org:8324']),
            ],
            'IPv6 local loopback address' => [
                'https://[::1]:8000/blog/article.php?id=10&user=foo',
                array_merge($server, ['HTTP_HOST' => '[::1]:8000']),
            ],
            'Invalid host' => [
                'https://localhost/blog/article.php?id=10&user=foo',
                array_merge($server, ['HTTP_HOST' => 'a:b']),
            ],
            'Different port with SERVER_PORT' => [
                'https://www.example.org:8324/blog/article.php?id=10&user=foo',
                array_merge($server, ['SERVER_PORT' => '8324']),
            ],
            'REQUEST_URI missing query string' => [
                'https://www.example.org/blog/article.php?id=10&user=foo',
                array_merge($server, ['REQUEST_URI' => '/blog/article.php']),
            ],
            'Empty server variable' => [
                'http://localhost',
                [],
            ],
        ];
    }

    /**
     * @dataProvider dataGetUriFromGlobals
     */
    public function testGetUriFromGlobals($expected, $serverParams): void
    {
        $_SERVER = $serverParams;

        self::assertEquals(new Uri($expected), ServerRequest::getUriFromGlobals());
    }

    public function testFromGlobals(): void
    {
        $_SERVER = [
            'REQUEST_URI' => '/blog/article.php?id=10&user=foo',
            'SERVER_PORT' => '443',
            'SERVER_ADDR' => '217.112.82.20',
            'SERVER_NAME' => 'www.example.org',
            'SERVER_PROTOCOL' => 'HTTP/1.1',
            'REQUEST_METHOD' => 'POST',
            'QUERY_STRING' => 'id=10&user=foo',
            'DOCUMENT_ROOT' => '/path/to/your/server/root/',
            'CONTENT_TYPE' => 'text/plain',
            'HTTP_HOST' => 'www.example.org',
            'HTTP_ACCEPT' => 'text/html',
            'HTTP_REFERRER' => 'https://example.com',
            'HTTP_USER_AGENT' => 'My User Agent',
            'HTTPS' => 'on',
            'REMOTE_ADDR' => '193.60.168.69',
            'REMOTE_PORT' => '5390',
            'SCRIPT_NAME' => '/blog/article.php',
            'SCRIPT_FILENAME' => '/path/to/your/server/root/blog/article.php',
            'PHP_SELF' => '/blog/article.php',
        ];

        $_COOKIE = [
            'logged-in' => 'yes!',
        ];

        $_POST = [
            'name' => 'Pesho',
            'email' => 'pesho@example.com',
        ];

        $_GET = [
            'id' => 10,
            'user' => 'foo',
        ];

        $_FILES = [
            'file' => [
                'name' => 'MyFile.txt',
                'type' => 'text/plain',
                'tmp_name' => '/tmp/php/php1h4j1o',
                'error' => UPLOAD_ERR_OK,
                'size' => 123,
            ],
        ];

        $server = ServerRequest::fromGlobals();

        self::assertSame('POST', $server->getMethod());
        self::assertEquals([
            'Host' => ['www.example.org'],
            'Content-Type' => ['text/plain'],
            'Accept' => ['text/html'],
            'Referrer' => ['https://example.com'],
            'User-Agent' => ['My User Agent'],
        ], $server->getHeaders());
        self::assertSame('', (string) $server->getBody());
        self::assertSame('1.1', $server->getProtocolVersion());
        self::assertSame($_COOKIE, $server->getCookieParams());
        self::assertSame($_POST, $server->getParsedBody());
        self::assertSame($_GET, $server->getQueryParams());

        self::assertEquals(
            new Uri('https://www.example.org/blog/article.php?id=10&user=foo'),
            $server->getUri()
        );

        $expectedFiles = [
            'file' => new UploadedFile(
                '/tmp/php/php1h4j1o',
                123,
                UPLOAD_ERR_OK,
                'MyFile.txt',
                'text/plain'
            ),
        ];

        self::assertEquals($expectedFiles, $server->getUploadedFiles());
    }

    public function testUploadedFiles(): void
    {
        $request1 = new ServerRequest('GET', '/');

        $files = [
            'file' => new UploadedFile('test', 123, UPLOAD_ERR_OK),
        ];

        $request2 = $request1->withUploadedFiles($files);

        self::assertNotSame($request2, $request1);
        self::assertSame([], $request1->getUploadedFiles());
        self::assertSame($files, $request2->getUploadedFiles());
    }

    public function testServerParams(): void
    {
        $params = ['name' => 'value'];

        $request = new ServerRequest('GET', '/', [], null, '1.1', $params);
        self::assertSame($params, $request->getServerParams());
    }

    public function testCookieParams(): void
    {
        $request1 = new ServerRequest('GET', '/');

        $params = ['name' => 'value'];

        $request2 = $request1->withCookieParams($params);

        self::assertNotSame($request2, $request1);
        self::assertEmpty($request1->getCookieParams());
        self::assertSame($params, $request2->getCookieParams());
    }

    public function testQueryParams(): void
    {
        $request1 = new ServerRequest('GET', '/');

        $params = ['name' => 'value'];

        $request2 = $request1->withQueryParams($params);

        self::assertNotSame($request2, $request1);
        self::assertEmpty($request1->getQueryParams());
        self::assertSame($params, $request2->getQueryParams());
    }

    public function testParsedBody(): void
    {
        $request1 = new ServerRequest('GET', '/');

        $params = ['name' => 'value'];

        $request2 = $request1->withParsedBody($params);

        self::assertNotSame($request2, $request1);
        self::assertEmpty($request1->getParsedBody());
        self::assertSame($params, $request2->getParsedBody());
    }

    public function testAttributes(): void
    {
        $request1 = new ServerRequest('GET', '/');

        $request2 = $request1->withAttribute('name', 'value');
        $request3 = $request2->withAttribute('other', 'otherValue');
        $request4 = $request3->withoutAttribute('other');
        $request5 = $request3->withoutAttribute('unknown');

        self::assertNotSame($request2, $request1);
        self::assertNotSame($request3, $request2);
        self::assertNotSame($request4, $request3);
        self::assertSame($request5, $request3);

        self::assertSame([], $request1->getAttributes());
        self::assertNull($request1->getAttribute('name'));
        self::assertSame(
            'something',
            $request1->getAttribute('name', 'something'),
            'Should return the default value'
        );

        self::assertSame('value', $request2->getAttribute('name'));
        self::assertSame(['name' => 'value'], $request2->getAttributes());
        self::assertSame(['name' => 'value', 'other' => 'otherValue'], $request3->getAttributes());
        self::assertSame(['name' => 'value'], $request4->getAttributes());
    }

    public function testNullAttribute(): void
    {
        $request = (new ServerRequest('GET', '/'))->withAttribute('name', null);

        self::assertSame(['name' => null], $request->getAttributes());
        self::assertNull($request->getAttribute('name', 'different-default'));

        $requestWithoutAttribute = $request->withoutAttribute('name');

        self::assertSame([], $requestWithoutAttribute->getAttributes());
        self::assertSame('different-default', $requestWithoutAttribute->getAttribute('name', 'different-default'));
    }
}
