<?php

declare(strict_types=1);

namespace GuzzleHttp\Tests\Psr7;

use GuzzleHttp\Psr7\ServerRequest;
use GuzzleHttp\Psr7\UploadedFile;
use GuzzleHttp\Psr7\Uri;
use PHPUnit\Framework\TestCase;

/**
 * @covers \GuzzleHttp\Psr7\ServerRequest
 * @covers \GuzzleHttp\Psr7\UploadedFileNormalizer
 */
class ServerRequestTest extends TestCase
{
    public static function dataNormalizeFiles(): iterable
    {
        return [
            'No files' => [
                [],
                [],
            ],
            'Single file' => [
                [
                    'file' => [
                        'name' => 'MyFile.txt',
                        'type' => 'text/plain',
                        'tmp_name' => '/tmp/php/php1h4j1o',
                        'error' => UPLOAD_ERR_OK,
                        'size' => 123,
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
            'Single file without optional metadata' => [
                [
                    'file' => [
                        'tmp_name' => '/tmp/php/php1h4j1o',
                        'error' => UPLOAD_ERR_OK,
                        'size' => 123,
                    ],
                ],
                [
                    'file' => new UploadedFile(
                        '/tmp/php/php1h4j1o',
                        123,
                        UPLOAD_ERR_OK
                    ),
                ],
            ],
            'Empty file' => [
                [
                    'image_file' => [
                        'name' => '',
                        'type' => '',
                        'tmp_name' => '',
                        'error' => UPLOAD_ERR_NO_FILE,
                        'size' => 0,
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
                        'error' => UPLOAD_ERR_OK,
                        'size' => 123,
                    ],
                    'image_file' => [
                        'name' => '',
                        'type' => '',
                        'tmp_name' => '',
                        'error' => UPLOAD_ERR_NO_FILE,
                        'size' => 0,
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
                        ],
                        'error' => [
                            0 => UPLOAD_ERR_OK,
                            1 => UPLOAD_ERR_OK,
                        ],
                        'size' => [
                            0 => 123,
                            1 => 7349,
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
                            'other' => UPLOAD_ERR_OK,
                            'test' => [
                                0 => UPLOAD_ERR_OK,
                                1 => UPLOAD_ERR_NO_FILE,
                            ],
                        ],
                        'size' => [
                            'other' => 421,
                            'test' => [
                                0 => 32,
                                1 => 0,
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
            'Nested files without optional metadata' => [
                [
                    'file' => [
                        'tmp_name' => [
                            0 => '/tmp/php/hp9hskjhf',
                        ],
                        'error' => [
                            0 => UPLOAD_ERR_OK,
                        ],
                        'size' => [
                            0 => 123,
                        ],
                    ],
                ],
                [
                    'file' => [
                        0 => new UploadedFile(
                            '/tmp/php/hp9hskjhf',
                            123,
                            UPLOAD_ERR_OK
                        ),
                    ],
                ],
            ],
            'Nested files ignore metadata without a matching temporary file' => [
                [
                    'file' => [
                        'tmp_name' => [0 => '/tmp/php/hp9hskjhf'],
                        'error' => [0 => UPLOAD_ERR_OK, 1 => UPLOAD_ERR_NO_FILE],
                        'size' => [0 => 123, 1 => 0],
                        'name' => [0 => 'MyFile.txt', 1 => ''],
                        'type' => [0 => 'text/plain', 1 => ''],
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
                    ],
                ],
            ],
        ];
    }

    /**
     * @dataProvider dataNormalizeFiles
     */
    public function testNormalizeFiles(array $files, array $expected): void
    {
        $result = ServerRequest::normalizeFiles($files);

        self::assertEquals($expected, $result);
    }

    /**
     * @dataProvider invalidFileSpecifications
     */
    public function testNormalizeFilesRaisesException(array $files, string $expectedMessage): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage($expectedMessage);

        ServerRequest::normalizeFiles($files);
    }

    public static function invalidFileSpecifications(): iterable
    {
        yield 'invalid scalar' => [
            ['test' => 'something'],
            'Invalid value in files specification',
        ];

        yield 'single file missing size' => [
            ['file' => ['tmp_name' => '/tmp/php123', 'error' => UPLOAD_ERR_OK]],
            'Invalid file specification',
        ];

        yield 'single file missing error' => [
            ['file' => ['tmp_name' => '/tmp/php123', 'size' => 123]],
            'Invalid file specification',
        ];

        yield 'single file string error' => [
            ['file' => ['tmp_name' => '/tmp/php123', 'size' => 123, 'error' => '0']],
            'Uploaded file error must be a non-negative integer',
        ];

        yield 'single file float error' => [
            ['file' => ['tmp_name' => '/tmp/php123', 'size' => 123, 'error' => (float) \PHP_INT_MAX]],
            'Uploaded file error must be a non-negative integer',
        ];

        yield 'single file string size' => [
            ['file' => ['tmp_name' => '/tmp/php123', 'size' => '123', 'error' => UPLOAD_ERR_OK]],
            'Uploaded file size must be a non-negative integer',
        ];

        yield 'single file negative size' => [
            ['file' => ['tmp_name' => '/tmp/php123', 'size' => -1, 'error' => UPLOAD_ERR_OK]],
            'Uploaded file size must be a non-negative integer',
        ];

        yield 'nested file missing size array' => [
            ['file' => ['tmp_name' => [0 => '/tmp/php123'], 'error' => [0 => UPLOAD_ERR_OK]]],
            'Invalid file specification',
        ];

        yield 'nested file missing error array' => [
            ['file' => ['tmp_name' => [0 => '/tmp/php123'], 'size' => [0 => 123]]],
            'Invalid file specification',
        ];

        yield 'nested file string size' => [
            ['file' => ['tmp_name' => [0 => '/tmp/php123'], 'size' => [0 => '123'], 'error' => [0 => UPLOAD_ERR_OK]]],
            'Uploaded file size must be a non-negative integer',
        ];

        yield 'nested file scalar size' => [
            ['file' => ['tmp_name' => [0 => '/tmp/php123'], 'size' => 123, 'error' => [0 => UPLOAD_ERR_OK]]],
            'Invalid nested file specification',
        ];

        yield 'nested file scalar error' => [
            ['file' => ['tmp_name' => [0 => '/tmp/php123'], 'size' => [0 => 123], 'error' => UPLOAD_ERR_OK]],
            'Invalid nested file specification',
        ];

        yield 'nested file missing size key' => [
            [
                'file' => [
                    'tmp_name' => [0 => '/tmp/a', 1 => '/tmp/b'],
                    'size' => [0 => 123],
                    'error' => [0 => UPLOAD_ERR_OK, 1 => UPLOAD_ERR_OK],
                ],
            ],
            'matching keys',
        ];

        yield 'nested file missing error key' => [
            [
                'file' => [
                    'tmp_name' => [0 => '/tmp/a', 1 => '/tmp/b'],
                    'size' => [0 => 123, 1 => 456],
                    'error' => [0 => UPLOAD_ERR_OK],
                ],
            ],
            'matching keys',
        ];

        yield 'nested file scalar name' => [
            [
                'file' => [
                    'tmp_name' => [0 => '/tmp/a'],
                    'size' => [0 => 123],
                    'error' => [0 => UPLOAD_ERR_OK],
                    'name' => 'a.txt',
                ],
            ],
            'expected key "name" to be an array',
        ];
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
            'Host header with leading zero port' => [
                'https://www.example.org:8324/blog/article.php?id=10&user=foo',
                array_merge($server, ['HTTP_HOST' => 'www.example.org:008324']),
            ],
            'Host header with zero port falls back to SERVER_NAME' => [
                'https://www.example.org/blog/article.php?id=10&user=foo',
                array_merge($server, ['HTTP_HOST' => 'bad.example.org:0']),
            ],
            'Host header with zero padded zero port falls back to SERVER_NAME' => [
                'https://www.example.org/blog/article.php?id=10&user=foo',
                array_merge($server, ['HTTP_HOST' => 'bad.example.org:0000']),
            ],
            'IPv6 local loopback address' => [
                'https://[::1]:8000/blog/article.php?id=10&user=foo',
                array_merge($server, ['HTTP_HOST' => '[::1]:8000']),
            ],
            'IPv6 host with non-canonical spelling' => [
                'https://[::1]:8000/blog/article.php?id=10&user=foo',
                array_merge($server, ['HTTP_HOST' => '[::0:1]:8000']),
            ],
            'Invalid host' => [
                'https://www.example.org/blog/article.php?id=10&user=foo',
                array_merge($server, ['HTTP_HOST' => 'a:b']),
            ],
            'Host header with newline' => [
                'https://www.example.org/blog/article.php?id=10&user=foo',
                array_merge($server, ['HTTP_HOST' => "www.example.org\n.evil"]),
            ],
            'Host header with userinfo delimiter' => [
                'https://www.example.org/blog/article.php?id=10&user=foo',
                array_merge($server, ['HTTP_HOST' => 'trusted.example@evil.example']),
            ],
            'Host header with path delimiter' => [
                'https://www.example.org/blog/article.php?id=10&user=foo',
                array_merge($server, ['HTTP_HOST' => 'example.com/path']),
            ],
            'Host header with query delimiter' => [
                'https://www.example.org/blog/article.php?id=10&user=foo',
                array_merge($server, ['HTTP_HOST' => 'example.com?x=1']),
            ],
            'Host header with fragment delimiter' => [
                'https://www.example.org/blog/article.php?id=10&user=foo',
                array_merge($server, ['HTTP_HOST' => 'example.com#frag']),
            ],
            'Host header with backslash delimiter' => [
                'https://www.example.org/blog/article.php?id=10&user=foo',
                array_merge($server, ['HTTP_HOST' => 'example.com\\evil']),
            ],
            'Host header with percent-encoded delimiter' => [
                'https://www.example.org/blog/article.php?id=10&user=foo',
                array_merge($server, ['HTTP_HOST' => 'ex%2Fample.com']),
            ],
            'Host header with space' => [
                'https://www.example.org/blog/article.php?id=10&user=foo',
                array_merge($server, ['HTTP_HOST' => 'bad host']),
            ],
            'Host header with multiple ports' => [
                'https://www.example.org/blog/article.php?id=10&user=foo',
                array_merge($server, ['HTTP_HOST' => 'www.example.org:443:8324']),
            ],
            'Host header with ambiguous ports' => [
                'https://www.example.org/blog/article.php?id=10&user=foo',
                array_merge($server, ['HTTP_HOST' => 'example.com:80:90']),
            ],
            'Host header with invalid ip literal' => [
                'https://www.example.org/blog/article.php?id=10&user=foo',
                array_merge($server, ['HTTP_HOST' => '[bad]']),
            ],
            'Host header with unexpected opening bracket' => [
                'https://www.example.org/blog/article.php?id=10&user=foo',
                array_merge($server, ['HTTP_HOST' => 'foo[bar']),
            ],
            'Host header with unexpected closing bracket' => [
                'https://www.example.org/blog/article.php?id=10&user=foo',
                array_merge($server, ['HTTP_HOST' => 'foo]bar']),
            ],
            'Invalid HTTP_HOST and SERVER_NAME -> fallback to SERVER_ADDR' => [
                'https://217.112.82.20/blog/article.php?id=10&user=foo',
                array_merge($server, ['HTTP_HOST' => 'www.example.org:443:8324', 'SERVER_NAME' => 'bad host']),
            ],
            'Different port with SERVER_PORT' => [
                'https://www.example.org:8324/blog/article.php?id=10&user=foo',
                array_merge($server, ['SERVER_PORT' => '8324']),
            ],
            'SERVER_PORT with leading zeroes' => [
                'https://www.example.org:8324/blog/article.php?id=10&user=foo',
                array_merge($server, ['SERVER_PORT' => '008324']),
            ],
            'SERVER_PORT with maximum valid port' => [
                'https://www.example.org:65535/blog/article.php?id=10&user=foo',
                array_merge($server, ['SERVER_PORT' => '65535']),
            ],
            'HTTP_HOST port takes precedence over malformed SERVER_PORT' => [
                'https://www.example.org:8324/blog/article.php?id=10&user=foo',
                array_merge($server, ['HTTP_HOST' => 'www.example.org:8324', 'SERVER_PORT' => '+443']),
            ],
            'Non-string SERVER_PORT is ignored' => [
                'https://www.example.org/blog/article.php?id=10&user=foo',
                array_merge($server, ['SERVER_PORT' => ['443']]),
            ],
            'Non-string HTTP_HOST falls back to SERVER_NAME' => [
                'https://www.example.org/blog/article.php?id=10&user=foo',
                array_merge($server, ['HTTP_HOST' => ['www.example.org']]),
            ],
            'Non-string SERVER_NAME falls back to SERVER_ADDR' => [
                'https://217.112.82.20/blog/article.php?id=10&user=foo',
                array_merge($server, ['HTTP_HOST' => null, 'SERVER_NAME' => ['www.example.org']]),
            ],
            'REQUEST_URI missing query string' => [
                'https://www.example.org/blog/article.php?id=10&user=foo',
                array_merge($server, ['REQUEST_URI' => '/blog/article.php']),
            ],
            'Non-string REQUEST_URI is treated as missing' => [
                'https://www.example.org?id=10&user=foo',
                array_merge($server, ['REQUEST_URI' => ['bad']]),
            ],
            'Non-string QUERY_STRING is treated as missing' => [
                'https://www.example.org/blog/article.php',
                array_merge($server, ['REQUEST_URI' => '/blog/article.php', 'QUERY_STRING' => ['bad']]),
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
    public function testGetUriFromGlobals(string $expected, array $serverParams): void
    {
        $_SERVER = $serverParams;

        self::assertEquals(new Uri($expected), ServerRequest::getUriFromGlobals());
    }

    public static function dataGetUriFromGlobalsRequestTargetForms(): iterable
    {
        yield 'origin-form' => [
            ['REQUEST_URI' => '/admin?x=1', 'HTTP_HOST' => 'good.example'],
            'http://good.example/admin?x=1',
            'good.example',
            null,
            '/admin',
            'x=1',
        ];

        yield 'slashless origin-form is recovered' => [
            ['REQUEST_URI' => 'admin?x=1', 'HTTP_HOST' => 'good.example'],
            'http://good.example/admin?x=1',
            'good.example',
            null,
            '/admin',
            'x=1',
        ];

        yield 'query-only origin-form is recovered' => [
            ['REQUEST_URI' => '?x=1', 'HTTP_HOST' => 'good.example'],
            'http://good.example?x=1',
            'good.example',
            null,
            '',
            'x=1',
        ];

        yield 'absolute-form target supplies authority' => [
            ['REQUEST_URI' => 'http://up.example:8080/admin?x=1', 'HTTP_HOST' => 'good.example'],
            'http://up.example:8080/admin?x=1',
            'up.example',
            8080,
            '/admin',
            'x=1',
        ];

        yield 'absolute-form target supplies authority before malformed SERVER_PORT' => [
            ['REQUEST_URI' => 'http://up.example:8080/admin?x=1', 'HTTP_HOST' => 'good.example', 'SERVER_PORT' => '+443'],
            'http://up.example:8080/admin?x=1',
            'up.example',
            8080,
            '/admin',
            'x=1',
        ];

        yield 'absolute-form empty port target supplies authority before malformed SERVER_PORT' => [
            ['REQUEST_URI' => 'http://up.example:/admin', 'HTTP_HOST' => 'good.example', 'SERVER_PORT' => '+443'],
            'http://up.example/admin',
            'up.example',
            null,
            '/admin',
            '',
        ];

        yield 'absolute-form zero port target supplies authority before malformed SERVER_PORT' => [
            ['REQUEST_URI' => 'http://up.example:0/admin', 'HTTP_HOST' => 'good.example', 'SERVER_PORT' => '+443'],
            'http://up.example:0/admin',
            'up.example',
            0,
            '/admin',
            '',
        ];

        yield 'absolute-form zero padded zero port target supplies authority before malformed SERVER_PORT' => [
            ['REQUEST_URI' => 'http://up.example:0000/admin', 'HTTP_HOST' => 'good.example', 'SERVER_PORT' => '+443'],
            'http://up.example:0/admin',
            'up.example',
            0,
            '/admin',
            '',
        ];

        yield 'absolute-form ipv6 target supplies authority before malformed SERVER_PORT' => [
            ['REQUEST_URI' => 'http://[::1]:8080/admin?x=1', 'HTTP_HOST' => 'good.example', 'SERVER_PORT' => '+443'],
            'http://[::1]:8080/admin?x=1',
            '[::1]',
            8080,
            '/admin',
            'x=1',
        ];

        yield 'absolute-form userinfo target is normalized before malformed SERVER_PORT' => [
            ['REQUEST_URI' => 'http://trusted.example@evil.example/admin', 'HTTP_HOST' => 'trusted.example', 'SERVER_PORT' => '+443'],
            'http://evil.example/admin',
            'evil.example',
            null,
            '/admin',
            '',
        ];

        yield 'absolute-form target with percent-encoded host delimiter is treated as a path' => [
            ['REQUEST_URI' => 'http://ex%2Fample.com/x', 'HTTP_HOST' => 'good.example'],
            'http://good.example/http://ex%2Fample.com/x',
            'good.example',
            null,
            '/http://ex%2Fample.com/x',
            '',
        ];

        yield 'absolute-form userinfo target with password is normalized before malformed SERVER_PORT' => [
            ['REQUEST_URI' => 'http://user:pass@evil.example/admin', 'HTTP_HOST' => 'good.example', 'SERVER_PORT' => '+443'],
            'http://evil.example/admin',
            'evil.example',
            null,
            '/admin',
            '',
        ];

        yield 'absolute-form empty userinfo target is normalized before malformed SERVER_PORT' => [
            ['REQUEST_URI' => 'http://@evil.example/admin', 'HTTP_HOST' => 'good.example', 'SERVER_PORT' => '+443'],
            'http://evil.example/admin',
            'evil.example',
            null,
            '/admin',
            '',
        ];

        yield 'absolute-form userinfo target uses query string fallback after normalization' => [
            ['REQUEST_URI' => 'http://trusted.example@evil.example/admin', 'QUERY_STRING' => 'x=1', 'HTTP_HOST' => 'trusted.example', 'SERVER_PORT' => '+443'],
            'http://evil.example/admin?x=1',
            'evil.example',
            null,
            '/admin',
            'x=1',
        ];

        yield 'absolute-form userinfo target keeps request uri query after normalization' => [
            ['REQUEST_URI' => 'http://trusted.example@evil.example/admin?from_uri=1', 'QUERY_STRING' => 'from_query=1', 'HTTP_HOST' => 'trusted.example', 'SERVER_PORT' => '+443'],
            'http://evil.example/admin?from_uri=1',
            'evil.example',
            null,
            '/admin',
            'from_uri=1',
        ];

        yield 'absolute-form userinfo target strips fragment after normalization' => [
            ['REQUEST_URI' => 'http://trusted.example@evil.example/admin#frag', 'HTTP_HOST' => 'trusted.example', 'SERVER_PORT' => '+443'],
            'http://evil.example/admin',
            'evil.example',
            null,
            '/admin',
            '',
        ];

        yield 'absolute-form userinfo empty port target is normalized before malformed SERVER_PORT' => [
            ['REQUEST_URI' => 'http://user@evil.example:/admin', 'HTTP_HOST' => 'good.example', 'SERVER_PORT' => '+443'],
            'http://evil.example/admin',
            'evil.example',
            null,
            '/admin',
            '',
        ];

        yield 'absolute-form userinfo zero port target supplies authority before malformed SERVER_PORT' => [
            ['REQUEST_URI' => 'http://user@evil.example:0/admin', 'HTTP_HOST' => 'good.example', 'SERVER_PORT' => '+443'],
            'http://evil.example:0/admin',
            'evil.example',
            0,
            '/admin',
            '',
        ];

        yield 'absolute-form userinfo zero padded zero port target supplies authority before malformed SERVER_PORT' => [
            ['REQUEST_URI' => 'http://user@evil.example:0000/admin', 'HTTP_HOST' => 'good.example', 'SERVER_PORT' => '+443'],
            'http://evil.example:0/admin',
            'evil.example',
            0,
            '/admin',
            '',
        ];

        yield 'absolute-form ipv6 userinfo target supplies authority before malformed SERVER_PORT' => [
            ['REQUEST_URI' => 'http://user@[::1]:8080/admin', 'HTTP_HOST' => 'good.example', 'SERVER_PORT' => '+443'],
            'http://[::1]:8080/admin',
            '[::1]',
            8080,
            '/admin',
            '',
        ];

        yield 'absolute-form uses QUERY_STRING when request uri has no query' => [
            ['REQUEST_URI' => 'http://up.example/admin', 'QUERY_STRING' => 'x=1', 'HTTP_HOST' => 'good.example'],
            'http://up.example/admin?x=1',
            'up.example',
            null,
            '/admin',
            'x=1',
        ];

        yield 'absolute-form target uses QUERY_STRING before malformed SERVER_PORT' => [
            ['REQUEST_URI' => 'http://up.example/admin', 'QUERY_STRING' => 'x=1', 'HTTP_HOST' => 'good.example', 'SERVER_PORT' => '+443'],
            'http://up.example/admin?x=1',
            'up.example',
            null,
            '/admin',
            'x=1',
        ];

        yield 'absolute-form ignores empty QUERY_STRING when request uri has no query' => [
            ['REQUEST_URI' => 'http://up.example/admin', 'QUERY_STRING' => '', 'HTTP_HOST' => 'good.example'],
            'http://up.example/admin',
            'up.example',
            null,
            '/admin',
            '',
        ];

        yield 'asterisk-form has no uri path' => [
            ['REQUEST_METHOD' => 'OPTIONS', 'REQUEST_URI' => '*', 'HTTP_HOST' => 'good.example'],
            'http://good.example',
            'good.example',
            null,
            '',
            '',
        ];

        yield 'asterisk-form lowercase method is normalized from globals' => [
            ['REQUEST_METHOD' => 'options', 'REQUEST_URI' => '*', 'HTTP_HOST' => 'good.example'],
            'http://good.example',
            'good.example',
            null,
            '',
            '',
        ];

        yield 'asterisk-form mixed-case method is normalized from globals' => [
            ['REQUEST_METHOD' => 'OpTiOnS', 'REQUEST_URI' => '*', 'HTTP_HOST' => 'good.example'],
            'http://good.example',
            'good.example',
            null,
            '',
            '',
        ];

        yield 'connect authority-form supplies authority' => [
            ['REQUEST_METHOD' => 'CONNECT', 'REQUEST_URI' => 'up.example:443', 'HTTP_HOST' => 'good.example'],
            'http://up.example:443',
            'up.example',
            443,
            '',
            '',
        ];

        yield 'connect authority-form supplies authority before malformed SERVER_PORT' => [
            ['REQUEST_METHOD' => 'CONNECT', 'REQUEST_URI' => 'up.example:443', 'HTTP_HOST' => 'good.example', 'SERVER_PORT' => '+443'],
            'http://up.example:443',
            'up.example',
            443,
            '',
            '',
        ];

        yield 'connect authority-form https default port is normalized before malformed SERVER_PORT' => [
            ['REQUEST_METHOD' => 'CONNECT', 'REQUEST_URI' => 'up.example:443', 'HTTP_HOST' => 'good.example', 'SERVER_PORT' => '+443', 'HTTPS' => 'on'],
            'https://up.example',
            'up.example',
            null,
            '',
            '',
        ];

        yield 'connect authority-form ipv6 supplies authority before malformed SERVER_PORT' => [
            ['REQUEST_METHOD' => 'CONNECT', 'REQUEST_URI' => '[::1]:443', 'HTTP_HOST' => 'good.example', 'SERVER_PORT' => '+443'],
            'http://[::1]:443',
            '[::1]',
            443,
            '',
            '',
        ];

        yield 'connect authority-form lowercase method is normalized from globals' => [
            ['REQUEST_METHOD' => 'connect', 'REQUEST_URI' => 'up.example:443', 'HTTP_HOST' => 'good.example'],
            'http://up.example:443',
            'up.example',
            443,
            '',
            '',
        ];

        yield 'connect authority-form mixed-case method is normalized from globals' => [
            ['REQUEST_METHOD' => 'CoNnEcT', 'REQUEST_URI' => 'up.example:443', 'HTTP_HOST' => 'good.example'],
            'http://up.example:443',
            'up.example',
            443,
            '',
            '',
        ];

        yield 'connect authority-form with percent-encoded host delimiter is treated as a path' => [
            ['REQUEST_METHOD' => 'CONNECT', 'REQUEST_URI' => 'ex%2Fample.com:443', 'HTTP_HOST' => 'good.example'],
            'http://good.example/ex%2Fample.com:443',
            'good.example',
            null,
            '/ex%2Fample.com:443',
            '',
        ];

        yield 'request uri query wins over query string' => [
            ['REQUEST_URI' => '/admin?from_uri=1', 'QUERY_STRING' => 'from_query=1', 'HTTP_HOST' => 'good.example'],
            'http://good.example/admin?from_uri=1',
            'good.example',
            null,
            '/admin',
            'from_uri=1',
        ];

        yield 'explicit empty request uri query wins over query string' => [
            ['REQUEST_URI' => '/admin?', 'QUERY_STRING' => 'from_query=1', 'HTTP_HOST' => 'good.example'],
            'http://good.example/admin',
            'good.example',
            null,
            '/admin',
            '',
        ];
    }

    /**
     * @dataProvider dataGetUriFromGlobalsRequestTargetForms
     */
    public function testGetUriFromGlobalsRequestTargetForms(
        array $serverParams,
        string $expectedUri,
        string $expectedHost,
        ?int $expectedPort,
        string $expectedPath,
        string $expectedQuery
    ): void {
        $_SERVER = $serverParams;

        $uri = ServerRequest::getUriFromGlobals();

        self::assertSame($expectedUri, (string) $uri);
        self::assertSame($expectedHost, $uri->getHost());
        self::assertSame($expectedPort, $uri->getPort());
        self::assertSame($expectedPath, $uri->getPath());
        self::assertSame($expectedQuery, $uri->getQuery());
    }

    public static function dataFromGlobalsRequestTargetForms(): iterable
    {
        yield 'slashless origin-form is normalized' => [
            ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => 'admin?x=1', 'HTTP_HOST' => 'good.example'],
            '/admin?x=1',
            'http://good.example/admin?x=1',
        ];

        yield 'query-only origin-form is normalized' => [
            ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '?x=1', 'HTTP_HOST' => 'good.example'],
            '/?x=1',
            'http://good.example?x=1',
        ];

        yield 'absolute-form target is preserved' => [
            ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => 'http://up.example:8080/admin?x=1', 'HTTP_HOST' => 'good.example'],
            'http://up.example:8080/admin?x=1',
            'http://up.example:8080/admin?x=1',
        ];

        yield 'absolute-form target is preserved before malformed SERVER_PORT' => [
            ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => 'http://up.example:8080/admin?x=1', 'HTTP_HOST' => 'good.example', 'SERVER_PORT' => '+443'],
            'http://up.example:8080/admin?x=1',
            'http://up.example:8080/admin?x=1',
        ];

        yield 'absolute-form empty port target is normalized before malformed SERVER_PORT' => [
            ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => 'http://up.example:/admin', 'HTTP_HOST' => 'good.example', 'SERVER_PORT' => '+443'],
            'http://up.example/admin',
            'http://up.example/admin',
        ];

        yield 'absolute-form zero port target is preserved before malformed SERVER_PORT' => [
            ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => 'http://up.example:0/admin', 'HTTP_HOST' => 'good.example', 'SERVER_PORT' => '+443'],
            'http://up.example:0/admin',
            'http://up.example:0/admin',
        ];

        yield 'absolute-form zero padded zero port target is preserved before malformed SERVER_PORT' => [
            ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => 'http://up.example:0000/admin', 'HTTP_HOST' => 'good.example', 'SERVER_PORT' => '+443'],
            'http://up.example:0000/admin',
            'http://up.example:0/admin',
        ];

        yield 'absolute-form ipv6 target is preserved before malformed SERVER_PORT' => [
            ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => 'http://[::1]:8080/admin?x=1', 'HTTP_HOST' => 'good.example', 'SERVER_PORT' => '+443'],
            'http://[::1]:8080/admin?x=1',
            'http://[::1]:8080/admin?x=1',
        ];

        yield 'absolute-form userinfo target is stripped from request target before malformed SERVER_PORT' => [
            ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => 'http://trusted.example@evil.example/admin', 'HTTP_HOST' => 'trusted.example', 'SERVER_PORT' => '+443'],
            'http://evil.example/admin',
            'http://evil.example/admin',
        ];

        yield 'absolute-form userinfo target with password is stripped from request target before malformed SERVER_PORT' => [
            ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => 'http://user:pass@evil.example/admin', 'HTTP_HOST' => 'good.example', 'SERVER_PORT' => '+443'],
            'http://evil.example/admin',
            'http://evil.example/admin',
        ];

        yield 'absolute-form empty userinfo target is stripped from request target before malformed SERVER_PORT' => [
            ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => 'http://@evil.example/admin', 'HTTP_HOST' => 'good.example', 'SERVER_PORT' => '+443'],
            'http://evil.example/admin',
            'http://evil.example/admin',
        ];

        yield 'absolute-form userinfo target uses query string fallback after normalization' => [
            ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => 'http://trusted.example@evil.example/admin', 'QUERY_STRING' => 'x=1', 'HTTP_HOST' => 'trusted.example', 'SERVER_PORT' => '+443'],
            'http://evil.example/admin?x=1',
            'http://evil.example/admin?x=1',
        ];

        yield 'absolute-form userinfo target keeps request uri query after normalization' => [
            ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => 'http://trusted.example@evil.example/admin?from_uri=1', 'QUERY_STRING' => 'from_query=1', 'HTTP_HOST' => 'trusted.example', 'SERVER_PORT' => '+443'],
            'http://evil.example/admin?from_uri=1',
            'http://evil.example/admin?from_uri=1',
        ];

        yield 'absolute-form userinfo target strips fragment from request target' => [
            ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => 'http://trusted.example@evil.example/admin#frag', 'HTTP_HOST' => 'trusted.example', 'SERVER_PORT' => '+443'],
            'http://evil.example/admin',
            'http://evil.example/admin',
        ];

        yield 'absolute-form userinfo zero padded zero port target preserves raw port in request target' => [
            ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => 'http://user@evil.example:0000/admin', 'HTTP_HOST' => 'good.example', 'SERVER_PORT' => '+443'],
            'http://evil.example:0000/admin',
            'http://evil.example:0/admin',
        ];

        yield 'absolute-form userinfo default port target preserves raw port in request target' => [
            ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => 'http://user@evil.example:80/admin', 'HTTP_HOST' => 'good.example', 'SERVER_PORT' => '+443'],
            'http://evil.example:80/admin',
            'http://evil.example/admin',
        ];

        yield 'absolute-form uppercase userinfo target preserves safe raw casing in request target' => [
            ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => 'HTTP://user@EVIL.EXAMPLE/admin', 'HTTP_HOST' => 'good.example', 'SERVER_PORT' => '+443'],
            'HTTP://EVIL.EXAMPLE/admin',
            'http://evil.example/admin',
        ];

        yield 'absolute-form userinfo empty port target normalizes request target' => [
            ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => 'http://user@evil.example:/admin', 'HTTP_HOST' => 'good.example', 'SERVER_PORT' => '+443'],
            'http://evil.example/admin',
            'http://evil.example/admin',
        ];

        yield 'absolute-form ipv6 userinfo target preserves authority in request target' => [
            ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => 'http://user@[::1]:8080/admin', 'HTTP_HOST' => 'good.example', 'SERVER_PORT' => '+443'],
            'http://[::1]:8080/admin',
            'http://[::1]:8080/admin',
        ];

        yield 'absolute-form at sign in path is preserved' => [
            ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => 'http://evil.example/admin@user', 'HTTP_HOST' => 'good.example', 'SERVER_PORT' => '+443'],
            'http://evil.example/admin@user',
            'http://evil.example/admin@user',
        ];

        yield 'absolute-form at sign in query is preserved' => [
            ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => 'http://evil.example/admin?email=user@example.com', 'HTTP_HOST' => 'good.example', 'SERVER_PORT' => '+443'],
            'http://evil.example/admin?email=user@example.com',
            'http://evil.example/admin?email=user@example.com',
        ];

        yield 'absolute-form target uses query string fallback' => [
            ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => 'http://up.example/admin', 'QUERY_STRING' => 'x=1', 'HTTP_HOST' => 'good.example'],
            'http://up.example/admin?x=1',
            'http://up.example/admin?x=1',
        ];

        yield 'absolute-form target uses query string fallback before malformed SERVER_PORT' => [
            ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => 'http://up.example/admin', 'QUERY_STRING' => 'x=1', 'HTTP_HOST' => 'good.example', 'SERVER_PORT' => '+443'],
            'http://up.example/admin?x=1',
            'http://up.example/admin?x=1',
        ];

        yield 'absolute-form target ignores empty query string fallback' => [
            ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => 'http://up.example/admin', 'QUERY_STRING' => '', 'HTTP_HOST' => 'good.example'],
            'http://up.example/admin',
            'http://up.example/admin',
        ];

        yield 'asterisk-form target is preserved' => [
            ['REQUEST_METHOD' => 'OPTIONS', 'REQUEST_URI' => '*', 'HTTP_HOST' => 'good.example'],
            '*',
            'http://good.example',
        ];

        yield 'asterisk-form target with lowercase method is normalized from globals' => [
            ['REQUEST_METHOD' => 'options', 'REQUEST_URI' => '*', 'HTTP_HOST' => 'good.example'],
            '*',
            'http://good.example',
        ];

        yield 'asterisk-form target with mixed-case method is normalized from globals' => [
            ['REQUEST_METHOD' => 'OpTiOnS', 'REQUEST_URI' => '*', 'HTTP_HOST' => 'good.example'],
            '*',
            'http://good.example',
        ];

        yield 'connect authority-form target is preserved' => [
            ['REQUEST_METHOD' => 'CONNECT', 'REQUEST_URI' => 'up.example:443', 'HTTP_HOST' => 'good.example'],
            'up.example:443',
            'http://up.example:443',
        ];

        yield 'connect authority-form target is preserved before malformed SERVER_PORT' => [
            ['REQUEST_METHOD' => 'CONNECT', 'REQUEST_URI' => 'up.example:443', 'HTTP_HOST' => 'good.example', 'SERVER_PORT' => '+443'],
            'up.example:443',
            'http://up.example:443',
        ];

        yield 'connect authority-form https default port is normalized before malformed SERVER_PORT' => [
            ['REQUEST_METHOD' => 'CONNECT', 'REQUEST_URI' => 'up.example:443', 'HTTP_HOST' => 'good.example', 'SERVER_PORT' => '+443', 'HTTPS' => 'on'],
            'up.example:443',
            'https://up.example',
        ];

        yield 'connect authority-form ipv6 target is preserved before malformed SERVER_PORT' => [
            ['REQUEST_METHOD' => 'CONNECT', 'REQUEST_URI' => '[::1]:443', 'HTTP_HOST' => 'good.example', 'SERVER_PORT' => '+443'],
            '[::1]:443',
            'http://[::1]:443',
        ];

        yield 'connect authority-form target with lowercase method is normalized from globals' => [
            ['REQUEST_METHOD' => 'connect', 'REQUEST_URI' => 'up.example:443', 'HTTP_HOST' => 'good.example'],
            'up.example:443',
            'http://up.example:443',
        ];

        yield 'connect authority-form target with mixed-case method is normalized from globals' => [
            ['REQUEST_METHOD' => 'CoNnEcT', 'REQUEST_URI' => 'up.example:443', 'HTTP_HOST' => 'good.example'],
            'up.example:443',
            'http://up.example:443',
        ];

        yield 'query string is used when request uri is missing' => [
            ['REQUEST_METHOD' => 'GET', 'QUERY_STRING' => 'x=1', 'HTTP_HOST' => 'good.example'],
            '/?x=1',
            'http://good.example?x=1',
        ];
    }

    /**
     * @dataProvider dataFromGlobalsRequestTargetForms
     */
    public function testFromGlobalsRequestTargetForms(array $serverParams, string $expectedRequestTarget, string $expectedUri): void
    {
        $_SERVER = $serverParams;
        $_COOKIE = $_POST = $_GET = $_FILES = [];

        $request = ServerRequest::fromGlobals();

        self::assertSame($expectedRequestTarget, $request->getRequestTarget());
        self::assertSame($expectedUri, (string) $request->getUri());
    }

    public function testFromGlobalsKeepsValidHostHeaderWhenAbsoluteFormAuthorityDiffers(): void
    {
        if (\function_exists('apache_request_headers')) {
            self::markTestSkipped('apache_request_headers() is available.');
        }

        $_SERVER = [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => 'http://up.example:8080/admin?x=1',
            'HTTP_HOST' => 'good.example',
        ];
        $_COOKIE = $_POST = $_GET = $_FILES = [];

        $request = ServerRequest::fromGlobals();

        self::assertSame('up.example', $request->getUri()->getHost());
        self::assertSame('good.example', $request->getHeaderLine('Host'));
        self::assertSame('http://up.example:8080/admin?x=1', $request->getRequestTarget());
    }

    public function testFromGlobalsKeepsValidHostHeaderWhenConnectAuthorityDiffers(): void
    {
        if (\function_exists('apache_request_headers')) {
            self::markTestSkipped('apache_request_headers() is available.');
        }

        $_SERVER = [
            'REQUEST_METHOD' => 'CONNECT',
            'REQUEST_URI' => 'up.example:443',
            'HTTP_HOST' => 'good.example',
            'SERVER_PORT' => '+443',
        ];
        $_COOKIE = $_POST = $_GET = $_FILES = [];

        $request = ServerRequest::fromGlobals();

        self::assertSame('up.example', $request->getUri()->getHost());
        self::assertSame('up.example:443', $request->getRequestTarget());
        self::assertSame('good.example', $request->getHeaderLine('Host'));
        self::assertSame('http://up.example:443', (string) $request->getUri());
    }

    public function testFromGlobalsNormalizesAbsoluteFormUserInfoWithoutSynthesizingAuthorization(): void
    {
        if (\function_exists('apache_request_headers')) {
            self::markTestSkipped('apache_request_headers() is available.');
        }

        $_SERVER = [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => 'http://user:pass@evil.example/admin',
            'HTTP_HOST' => 'good.example',
            'SERVER_PORT' => '+443',
        ];
        $_COOKIE = $_POST = $_GET = $_FILES = [];

        $request = ServerRequest::fromGlobals();

        self::assertSame('http://evil.example/admin', (string) $request->getUri());
        self::assertSame('http://evil.example/admin', $request->getRequestTarget());
        self::assertSame('evil.example', $request->getUri()->getHost());
        self::assertSame('', $request->getUri()->getUserInfo());
        self::assertSame('good.example', $request->getHeaderLine('Host'));
        self::assertSame('', $request->getHeaderLine('Authorization'));
    }

    public function testFromGlobalsNormalizesEmptyAbsoluteFormUserInfoInRequestTarget(): void
    {
        if (\function_exists('apache_request_headers')) {
            self::markTestSkipped('apache_request_headers() is available.');
        }

        $_SERVER = [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => 'http://@evil.example/admin',
            'HTTP_HOST' => 'good.example',
            'SERVER_PORT' => '+443',
        ];
        $_COOKIE = $_POST = $_GET = $_FILES = [];

        $request = ServerRequest::fromGlobals();

        self::assertSame('http://evil.example/admin', (string) $request->getUri());
        self::assertSame('http://evil.example/admin', $request->getRequestTarget());
        self::assertSame('evil.example', $request->getUri()->getHost());
        self::assertSame('', $request->getUri()->getUserInfo());
    }

    public function testFromGlobalsNormalizesAbsoluteFormRequestTargetWithControlCharacter(): void
    {
        if (\function_exists('apache_request_headers')) {
            self::markTestSkipped('apache_request_headers() is available.');
        }

        $_SERVER = [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => "http://up.example/admin\x7Fpath",
            'HTTP_HOST' => 'good.example',
        ];
        $_COOKIE = $_POST = $_GET = $_FILES = [];

        $request = ServerRequest::fromGlobals();

        self::assertSame('http://up.example/admin%7Fpath', (string) $request->getUri());
        self::assertSame('http://up.example/admin%7Fpath', $request->getRequestTarget());
    }

    public static function dataInvalidServerPort(): iterable
    {
        yield 'empty' => [''];
        yield 'zero' => ['0'];
        yield 'zero padded zero' => ['0000'];
        yield 'negative' => ['-1'];
        yield 'leading plus' => ['+443'];
        yield 'out of range' => ['65536'];
        yield 'too large' => ['999999'];
        yield 'non numeric' => ['not-a-port'];
        yield 'trailing junk' => ['443abc'];
        yield 'leading whitespace' => [' 443'];
        yield 'decimal' => ['4.5'];
    }

    /**
     * @dataProvider dataInvalidServerPort
     */
    public function testGetUriFromGlobalsRejectsInvalidServerPort(string $serverPort): void
    {
        $_SERVER = [
            'REQUEST_URI' => '/blog/article.php?id=10&user=foo',
            'SERVER_PORT' => $serverPort,
            'SERVER_ADDR' => '217.112.82.20',
            'SERVER_NAME' => 'www.example.org',
            'SERVER_PROTOCOL' => 'HTTP/1.1',
            'REQUEST_METHOD' => 'POST',
            'QUERY_STRING' => 'id=10&user=foo',
            'HTTP_HOST' => 'www.example.org',
            'HTTPS' => 'on',
        ];

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid SERVER_PORT');

        ServerRequest::getUriFromGlobals();
    }

    public function testGetUriFromGlobalsRejectsInvalidServerPortForAsteriskForm(): void
    {
        $_SERVER = [
            'REQUEST_METHOD' => 'OPTIONS',
            'REQUEST_URI' => '*',
            'HTTP_HOST' => 'www.example.org',
            'SERVER_PORT' => '+443',
        ];

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid SERVER_PORT');

        ServerRequest::getUriFromGlobals();
    }

    public function testGetUriFromGlobalsRejectsInvalidServerPortWhenRequestUriIsMissing(): void
    {
        $_SERVER = [
            'HTTP_HOST' => 'www.example.org',
            'SERVER_PORT' => '+443',
        ];

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid SERVER_PORT');

        ServerRequest::getUriFromGlobals();
    }

    public function testGetUriFromGlobalsRejectsInvalidServerPortForMalformedAbsoluteFormFallback(): void
    {
        $_SERVER = [
            'REQUEST_URI' => 'http://up.example:bad/admin',
            'HTTP_HOST' => 'www.example.org',
            'SERVER_PORT' => '+443',
        ];

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid SERVER_PORT');

        ServerRequest::getUriFromGlobals();
    }

    public function testGetUriFromGlobalsAcceptsAbsoluteFormZeroPortBeforeMalformedServerPort(): void
    {
        $_SERVER = [
            'REQUEST_URI' => 'http://up.example:0/admin',
            'HTTP_HOST' => 'www.example.org',
            'SERVER_PORT' => '+443',
        ];

        $uri = ServerRequest::getUriFromGlobals();

        self::assertSame('http://up.example:0/admin', (string) $uri);
        self::assertSame('up.example', $uri->getHost());
        self::assertSame(0, $uri->getPort());
        self::assertSame('/admin', $uri->getPath());
    }

    public function testGetUriFromGlobalsAcceptsAbsoluteFormZeroPaddedZeroPortBeforeMalformedServerPort(): void
    {
        $_SERVER = [
            'REQUEST_URI' => 'http://up.example:0000/admin',
            'HTTP_HOST' => 'www.example.org',
            'SERVER_PORT' => '+443',
        ];

        $uri = ServerRequest::getUriFromGlobals();

        self::assertSame('http://up.example:0/admin', (string) $uri);
        self::assertSame('up.example', $uri->getHost());
        self::assertSame(0, $uri->getPort());
        self::assertSame('/admin', $uri->getPath());
    }

    public function testGetUriFromGlobalsRejectsInvalidServerPortForMalformedConnectFallback(): void
    {
        $_SERVER = [
            'REQUEST_METHOD' => 'CONNECT',
            'REQUEST_URI' => 'up.example:not-a-port',
            'HTTP_HOST' => 'www.example.org',
            'SERVER_PORT' => '+443',
        ];

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid SERVER_PORT');

        ServerRequest::getUriFromGlobals();
    }

    /**
     * @dataProvider dataFromGlobalsRejectsInvalidServerPortForRequestTargetFallback
     */
    public function testFromGlobalsRejectsInvalidServerPortForRequestTargetFallback(array $serverParams): void
    {
        $_SERVER = $serverParams + [
            'HTTP_HOST' => 'www.example.org',
            'SERVER_PORT' => '+443',
        ];
        $_COOKIE = $_POST = $_GET = $_FILES = [];

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid SERVER_PORT');

        ServerRequest::fromGlobals();
    }

    public static function dataFromGlobalsRejectsInvalidServerPortForRequestTargetFallback(): iterable
    {
        yield 'asterisk-form' => [
            [
                'REQUEST_METHOD' => 'OPTIONS',
                'REQUEST_URI' => '*',
            ],
        ];

        yield 'missing request uri' => [
            [],
        ];

        yield 'malformed absolute-form port' => [
            [
                'REQUEST_URI' => 'http://up.example:bad/admin',
            ],
        ];

        yield 'malformed connect port' => [
            [
                'REQUEST_METHOD' => 'CONNECT',
                'REQUEST_URI' => 'up.example:not-a-port',
            ],
        ];
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
            'CONTENT_LENGTH' => '123',
            'CONTENT_MD5' => 'Q2hlY2sgSW50ZWdyaXR5IQ==',
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
            'Content-Length' => ['123'],
            'Content-Md5' => ['Q2hlY2sgSW50ZWdyaXR5IQ=='],
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

    public function testFromGlobalsRepresentsEmptyFileInputAsNoFileUpload(): void
    {
        $_SERVER = [
            'REQUEST_METHOD' => 'POST',
            'HTTP_HOST' => 'www.example.org',
        ];

        $_FILES = [
            'test_file' => [
                'name' => '',
                'type' => '',
                'tmp_name' => '',
                'error' => UPLOAD_ERR_NO_FILE,
                'size' => 0,
            ],
        ];

        $server = ServerRequest::fromGlobals();

        $expectedFiles = [
            'test_file' => new UploadedFile(
                '',
                0,
                UPLOAD_ERR_NO_FILE,
                '',
                ''
            ),
        ];

        self::assertEquals($expectedFiles, $server->getUploadedFiles());
    }

    /**
     * @dataProvider requestMethodFromGlobalsProvider
     */
    public function testFromGlobalsNormalizesRequestMethod(string $requestMethod, string $expectedMethod): void
    {
        $_SERVER = [
            'REQUEST_METHOD' => $requestMethod,
            'REQUEST_URI' => '/',
            'HTTP_HOST' => 'www.example.org',
        ];
        $_COOKIE = $_POST = $_GET = $_FILES = [];

        $server = ServerRequest::fromGlobals();

        self::assertSame($expectedMethod, $server->getMethod());
    }

    public static function requestMethodFromGlobalsProvider(): iterable
    {
        yield 'lowercase' => ['post', 'POST'];
        yield 'mixed case' => ['OpTiOnS', 'OPTIONS'];
        yield 'custom method' => ['custom.method', 'CUSTOM.METHOD'];
    }

    public static function dataInvalidHostHeaderFromGlobals(): iterable
    {
        yield 'empty' => [''];
        yield 'userinfo delimiter' => ['trusted.example@evil.example'];
        yield 'path delimiter' => ['example.com/path'];
        yield 'query delimiter' => ['example.com?x=1'];
        yield 'fragment delimiter' => ['example.com#frag'];
        yield 'backslash delimiter' => ['example.com\\evil'];
        yield 'space' => ['bad host'];
        yield 'newline' => ["bad.example\r\nX-Evil: yes"];
        yield 'empty port' => ['bad.example:'];
        yield 'non numeric port' => ['bad.example:abc'];
        yield 'leading plus port' => ['bad.example:+443'];
        yield 'negative port' => ['bad.example:-1'];
        yield 'out of range port' => ['bad.example:65536'];
        yield 'multiple ports' => ['bad.example:443:8443'];
        yield 'zero port' => ['bad.example:0'];
        yield 'zero padded zero port' => ['bad.example:0000'];
        yield 'ipv6 zero port' => ['[::1]:0'];
        yield 'ipv6 zero padded zero port' => ['[::1]:0000'];
        yield 'ipv6 non numeric port' => ['[::1]:abc'];
        yield 'ipv6 out of range port' => ['[::1]:65536'];
        yield 'unexpected bracket suffix' => ['[::1]x'];
        yield 'invalid ip literal' => ['[bad]'];
        yield 'unexpected opening bracket' => ['foo[bar'];
        yield 'unexpected closing bracket' => ['foo]bar'];
    }

    /**
     * @dataProvider dataInvalidHostHeaderFromGlobals
     */
    public function testFromGlobalsDropsInvalidHostHeaderWhenUriFallsBack(string $host): void
    {
        if (\function_exists('apache_request_headers')) {
            self::markTestSkipped('apache_request_headers() is available.');
        }

        $_SERVER = [
            'REQUEST_URI' => '/',
            'HTTP_HOST' => $host,
            'SERVER_NAME' => 'good.example',
            'SERVER_PORT' => '443',
            'HTTPS' => 'on',
        ];

        $_COOKIE = $_POST = $_GET = $_FILES = [];

        $request = ServerRequest::fromGlobals();

        self::assertSame('good.example', $request->getUri()->getHost());
        self::assertSame('good.example', $request->getHeaderLine('Host'));
    }

    public function testFromGlobalsPreservesValidHostHeader(): void
    {
        if (\function_exists('apache_request_headers')) {
            self::markTestSkipped('apache_request_headers() is available.');
        }

        $_SERVER = [
            'REQUEST_URI' => '/',
            'HTTP_HOST' => 'www.example.org:8324',
            'SERVER_NAME' => 'good.example',
            'SERVER_PORT' => '443',
            'HTTPS' => 'on',
        ];

        $_COOKIE = $_POST = $_GET = $_FILES = [];

        $request = ServerRequest::fromGlobals();

        self::assertSame('www.example.org', $request->getUri()->getHost());
        self::assertSame(8324, $request->getUri()->getPort());
        self::assertSame('www.example.org:8324', $request->getHeaderLine('Host'));
    }

    public function testFromGlobalsPreservesLeadingZeroHostHeaderPort(): void
    {
        if (\function_exists('apache_request_headers')) {
            self::markTestSkipped('apache_request_headers() is available.');
        }

        $_SERVER = [
            'REQUEST_URI' => '/',
            'HTTP_HOST' => 'www.example.org:008324',
            'SERVER_NAME' => 'good.example',
            'SERVER_PORT' => '443',
            'HTTPS' => 'on',
        ];

        $_COOKIE = $_POST = $_GET = $_FILES = [];

        $request = ServerRequest::fromGlobals();

        self::assertSame('www.example.org', $request->getUri()->getHost());
        self::assertSame(8324, $request->getUri()->getPort());
        self::assertSame('www.example.org:008324', $request->getHeaderLine('Host'));
    }

    public function testFromGlobalsDerivesHostHeaderWhenHostHeaderMissing(): void
    {
        if (\function_exists('apache_request_headers')) {
            self::markTestSkipped('apache_request_headers() is available.');
        }

        $_SERVER = [
            'REQUEST_URI' => '/',
            'SERVER_NAME' => 'good.example',
            'SERVER_PORT' => '443',
            'HTTPS' => 'on',
        ];

        $_COOKIE = $_POST = $_GET = $_FILES = [];

        $request = ServerRequest::fromGlobals();

        self::assertSame('good.example', $request->getUri()->getHost());
        self::assertSame('good.example', $request->getHeaderLine('Host'));
    }

    public function testFromGlobalsBuildsHeadersFromServerWhenApacheRequestHeadersUnavailable(): void
    {
        if (\function_exists('apache_request_headers')) {
            self::markTestSkipped('apache_request_headers() is available.');
        }

        $_SERVER = [
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
        ];

        $_COOKIE = $_POST = $_GET = $_FILES = [];

        $server = ServerRequest::fromGlobals();

        self::assertEquals([
            'Host' => ['www.example.org'],
            'Accept-Language' => ['en-US'],
            'Content-Type' => ['application/json'],
            'Content-Length' => ['14'],
            'Content-Md5' => ['Q2hlY2sgSW50ZWdyaXR5IQ=='],
            'X-Empty' => [''],
        ], $server->getHeaders());
    }

    /**
     * @dataProvider dataAuthorizationHeaderFromServer
     */
    public function testFromGlobalsBuildsAuthorizationHeaderFromServerFallback(array $serverParams, string $expected): void
    {
        if (\function_exists('apache_request_headers')) {
            self::markTestSkipped('apache_request_headers() is available.');
        }

        $_SERVER = array_merge([
            'REQUEST_URI' => '/',
            'HTTP_HOST' => 'www.example.org',
        ], $serverParams);

        $_COOKIE = $_POST = $_GET = $_FILES = [];

        $server = ServerRequest::fromGlobals();

        self::assertSame([$expected], $server->getHeader('Authorization'));
    }

    public static function dataAuthorizationHeaderFromServer(): iterable
    {
        return [
            'HTTP_AUTHORIZATION has priority' => [
                [
                    'HTTP_AUTHORIZATION' => 'Bearer direct',
                    'REDIRECT_HTTP_AUTHORIZATION' => 'Bearer redirect',
                    'PHP_AUTH_USER' => 'user',
                    'PHP_AUTH_PW' => 'pass',
                    'PHP_AUTH_DIGEST' => 'Digest digest',
                ],
                'Bearer direct',
            ],
            'REDIRECT_HTTP_AUTHORIZATION fallback' => [
                [
                    'REDIRECT_HTTP_AUTHORIZATION' => 'Bearer redirect',
                    'PHP_AUTH_USER' => 'user',
                    'PHP_AUTH_PW' => 'pass',
                    'PHP_AUTH_DIGEST' => 'Digest digest',
                ],
                'Bearer redirect',
            ],
            'PHP_AUTH_USER fallback' => [
                [
                    'PHP_AUTH_USER' => 'user',
                    'PHP_AUTH_PW' => 'pass',
                    'PHP_AUTH_DIGEST' => 'Digest digest',
                ],
                'Basic '.base64_encode('user:pass'),
            ],
            'PHP_AUTH_USER fallback without password' => [
                [
                    'PHP_AUTH_USER' => 'user',
                ],
                'Basic '.base64_encode('user:'),
            ],
            'PHP_AUTH_DIGEST fallback' => [
                [
                    'PHP_AUTH_DIGEST' => 'Digest digest',
                ],
                'Digest digest',
            ],
        ];
    }

    public function testFromGlobalsIgnoresNonStringServerHeaders(): void
    {
        if (\function_exists('apache_request_headers')) {
            self::markTestSkipped('apache_request_headers() is available.');
        }

        $_SERVER = [
            'REQUEST_URI' => '/',
            'HTTP_HOST' => 'www.example.org',
            'HTTP_X_BAD' => ['not a string'],
            'CONTENT_TYPE' => ['not a string'],
            'HTTP_CONTENT_TYPE' => 'text/plain',
            'REDIRECT_HTTP_AUTHORIZATION' => ['not a string'],
            'PHP_AUTH_USER' => ['not a string'],
            'PHP_AUTH_DIGEST' => ['not a string'],
        ];

        $_COOKIE = $_POST = $_GET = $_FILES = [];

        $server = ServerRequest::fromGlobals();

        self::assertFalse($server->hasHeader('X-Bad'));
        self::assertFalse($server->hasHeader('Authorization'));
        self::assertSame(['text/plain'], $server->getHeader('Content-Type'));
    }

    /**
     * @runInSeparateProcess
     *
     * @preserveGlobalState disabled
     */
    public function testFromGlobalsPrefersApacheRequestHeadersWhenAvailable(): void
    {
        if (\function_exists('apache_request_headers')) {
            self::markTestSkipped('apache_request_headers() is already available.');
        }

        eval(<<<'PHP'
function apache_request_headers(): array
{
    return [
        'X-Native' => 'native',
        'X-Int' => 123,
        'X-False' => false,
        'X-Stringable' => new class {
            public function __toString(): string
            {
                return 'stringable';
            }
        },
        'X-Array' => ['bad'],
        'X-Object' => new \stdClass(),
        123 => 'numeric header',
    ];
}
PHP
        );

        $_SERVER = [
            'REQUEST_URI' => '/',
            'HTTP_HOST' => 'www.example.org',
            'HTTP_X_FALLBACK' => 'fallback',
        ];

        $_COOKIE = $_POST = $_GET = $_FILES = [];

        $server = ServerRequest::fromGlobals();

        self::assertSame(['native'], $server->getHeader('X-Native'));
        self::assertSame('123', $server->getHeaderLine('X-Int'));
        self::assertSame([''], $server->getHeader('X-False'));
        self::assertSame('stringable', $server->getHeaderLine('X-Stringable'));
        self::assertSame('numeric header', $server->getHeaderLine('123'));
        self::assertFalse($server->hasHeader('X-Array'));
        self::assertFalse($server->hasHeader('X-Object'));
        self::assertFalse($server->hasHeader('X-Fallback'));
    }

    /**
     * @runInSeparateProcess
     *
     * @preserveGlobalState disabled
     */
    public function testFromGlobalsDropsInvalidApacheHostHeaderWhenUriFallsBack(): void
    {
        if (\function_exists('apache_request_headers')) {
            self::markTestSkipped('apache_request_headers() is already available.');
        }

        eval('function apache_request_headers(): array { return ["Host" => "bad.example:443:8443", "X-Native" => "native"]; }');

        $_SERVER = [
            'REQUEST_URI' => '/',
            'HTTP_HOST' => 'bad.example:443:8443',
            'SERVER_NAME' => 'good.example',
            'SERVER_PORT' => '443',
            'HTTPS' => 'on',
        ];

        $_COOKIE = $_POST = $_GET = $_FILES = [];

        $server = ServerRequest::fromGlobals();

        self::assertSame('good.example', $server->getUri()->getHost());
        self::assertSame('good.example', $server->getHeaderLine('Host'));
        self::assertSame(['native'], $server->getHeader('X-Native'));
    }

    /**
     * @runInSeparateProcess
     *
     * @preserveGlobalState disabled
     */
    public function testFromGlobalsDropsZeroPortApacheHostHeaderWhenUriFallsBack(): void
    {
        if (\function_exists('apache_request_headers')) {
            self::markTestSkipped('apache_request_headers() is already available.');
        }

        eval('function apache_request_headers(): array { return ["Host" => "bad.example:0", "X-Native" => "native"]; }');

        $_SERVER = [
            'REQUEST_URI' => '/',
            'HTTP_HOST' => 'bad.example:0',
            'SERVER_NAME' => 'good.example',
            'SERVER_PORT' => '443',
            'HTTPS' => 'on',
        ];

        $_COOKIE = $_POST = $_GET = $_FILES = [];

        $server = ServerRequest::fromGlobals();

        self::assertSame('good.example', $server->getUri()->getHost());
        self::assertSame('good.example', $server->getHeaderLine('Host'));
        self::assertSame(['native'], $server->getHeader('X-Native'));
    }

    /**
     * @runInSeparateProcess
     *
     * @preserveGlobalState disabled
     */
    public function testFromGlobalsFallsBackWhenApacheRequestHeadersReturnsFalse(): void
    {
        if (\function_exists('apache_request_headers')) {
            self::markTestSkipped('apache_request_headers() is already available.');
        }

        eval('function apache_request_headers() { return false; }');

        $_SERVER = [
            'REQUEST_URI' => '/',
            'HTTP_HOST' => 'www.example.org',
            'HTTP_X_FALLBACK' => 'fallback',
        ];

        $_COOKIE = $_POST = $_GET = $_FILES = [];

        $server = ServerRequest::fromGlobals();

        self::assertSame(['fallback'], $server->getHeader('X-Fallback'));
    }

    public function testFromGlobalsDefaultsNonStringMethodAndProtocol(): void
    {
        $_SERVER = [
            'REQUEST_METHOD' => ['POST'],
            'SERVER_PROTOCOL' => ['HTTP/1.1'],
            'REQUEST_URI' => '/',
            'SERVER_PORT' => '80',
        ];

        $_COOKIE = $_POST = $_GET = $_FILES = [];

        $server = ServerRequest::fromGlobals();

        self::assertSame('GET', $server->getMethod());
        self::assertSame('1.1', $server->getProtocolVersion());
    }

    /**
     * @dataProvider invalidRequestMethodFromGlobalsProvider
     */
    public function testFromGlobalsRejectsInvalidStringMethod(string $method): void
    {
        $_SERVER = [
            'REQUEST_METHOD' => $method,
            'REQUEST_URI' => '/',
            'SERVER_PORT' => '80',
        ];
        $_COOKIE = $_POST = $_GET = $_FILES = [];

        $this->expectException(\InvalidArgumentException::class);

        ServerRequest::fromGlobals();
    }

    public static function invalidRequestMethodFromGlobalsProvider(): iterable
    {
        yield 'empty' => [''];
        yield 'space' => ['GET POST'];
        yield 'newline' => ["GET\r\nX-Injected: yes"];
        yield 'slash' => ['GET/'];
    }

    /**
     * @dataProvider invalidServerProtocolFromGlobalsProvider
     */
    public function testFromGlobalsRejectsInvalidStringServerProtocol(string $serverProtocol): void
    {
        $_SERVER = [
            'REQUEST_METHOD' => 'GET',
            'SERVER_PROTOCOL' => $serverProtocol,
            'REQUEST_URI' => '/',
            'SERVER_PORT' => '80',
        ];
        $_COOKIE = $_POST = $_GET = $_FILES = [];

        $this->expectException(\InvalidArgumentException::class);

        ServerRequest::fromGlobals();
    }

    public static function invalidServerProtocolFromGlobalsProvider(): iterable
    {
        yield 'empty' => [''];
        yield 'text' => ['HTTP/foo'];
        yield 'trailing space' => ['HTTP/1.1 '];
        yield 'newline' => ["HTTP/1.1\r\nX-Injected: yes"];
        yield 'repeated prefix' => ['HTTP/HTTP/1.1'];
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

    /**
     * @dataProvider validUploadedFilesProvider
     */
    public function testWithUploadedFilesAcceptsValidTrees(array $files): void
    {
        $request = new ServerRequest('GET', '/');

        $new = $request->withUploadedFiles($files);

        self::assertNotSame($request, $new);
        self::assertSame([], $request->getUploadedFiles());
        self::assertSame($files, $new->getUploadedFiles());
    }

    public static function validUploadedFilesProvider(): iterable
    {
        $file = new UploadedFile('test', 123, UPLOAD_ERR_OK);

        yield 'empty tree' => [[]];
        yield 'flat list' => [[$file, $file]];
        yield 'nested named tree' => [['files' => ['a' => $file, 'b' => [$file, $file]]]];
    }

    /**
     * @dataProvider invalidUploadedFilesProvider
     */
    public function testWithUploadedFilesRejectsInvalidTrees(array $files, string $expectedType): void
    {
        $original = ['file' => new UploadedFile('test', 123, UPLOAD_ERR_OK)];
        $request = (new ServerRequest('GET', '/'))->withUploadedFiles($original);

        try {
            $request->withUploadedFiles($files);
            self::fail('Uploaded file tree should have been rejected.');
        } catch (\InvalidArgumentException $e) {
            self::assertSame(
                sprintf('Invalid uploaded file tree; expected UploadedFileInterface instances but %s provided.', $expectedType),
                $e->getMessage()
            );
            self::assertSame($original, $request->getUploadedFiles());
        }
    }

    public static function invalidUploadedFilesProvider(): iterable
    {
        yield 'string leaf' => [['file' => 'not-a-file'], 'string'];
        yield 'integer leaf' => [['file' => 1], 'int'];
        yield 'null leaf' => [['file' => null], 'null'];
        yield 'object leaf' => [['file' => new \stdClass()], 'stdClass'];
        yield 'deeply nested invalid leaf' => [['files' => ['nested' => ['deep' => 'not-a-file']]], 'string'];
        yield 'valid then invalid' => [['a' => new UploadedFile('test', 123, UPLOAD_ERR_OK), 'b' => 'not-a-file'], 'string'];
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

    /**
     * @dataProvider validParsedBodyProvider
     *
     * @param array|object|null $value
     */
    public function testWithParsedBodyAcceptsValidValues($value): void
    {
        $request = new ServerRequest('GET', '/');

        $new = $request->withParsedBody($value);

        self::assertNotSame($request, $new);
        self::assertNull($request->getParsedBody());
        self::assertSame($value, $new->getParsedBody());
    }

    public static function validParsedBodyProvider(): iterable
    {
        yield 'null' => [null];
        yield 'array' => [['name' => 'value']];
        yield 'object' => [(object) ['name' => 'value']];
    }

    /**
     * @dataProvider invalidParsedBodyProvider
     *
     * @param bool|float|int|string $value
     */
    public function testWithParsedBodyRejectsInvalidValues($value): void
    {
        $request = (new ServerRequest('GET', '/'))->withParsedBody(['original' => 'value']);

        try {
            $request->withParsedBody($value);
            self::fail('Parsed body value should have been rejected.');
        } catch (\InvalidArgumentException $e) {
            self::assertSame('Parsed body must be an array, object, or null.', $e->getMessage());
            self::assertSame(['original' => 'value'], $request->getParsedBody());
        }
    }

    public static function invalidParsedBodyProvider(): iterable
    {
        yield 'integer' => [1];
        yield 'float' => [1.1];
        yield 'string' => ['body'];
        yield 'true' => [true];
        yield 'false' => [false];
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
