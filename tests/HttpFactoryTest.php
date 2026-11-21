<?php

declare(strict_types=1);

namespace GuzzleHttp\Tests\Psr7;

use GuzzleHttp\Psr7\FnStream;
use GuzzleHttp\Psr7\HttpFactory;
use PHPUnit\Framework\TestCase;

class HttpFactoryTest extends TestCase
{
    /**
     * @dataProvider deprecatedUriStringProvider
     */
    public function testCreateServerRequestDoesNotTriggerDeprecations(string $uri, string $expectedHost): void
    {
        $deprecations = [];
        set_error_handler(static function (int $errno, string $errstr) use (&$deprecations): bool {
            $deprecations[] = $errstr;

            return true;
        }, E_USER_DEPRECATED);

        try {
            $request = (new HttpFactory())->createServerRequest('GET', $uri);
        } finally {
            restore_error_handler();
        }

        self::assertSame($expectedHost, $request->getUri()->getHost());
        self::assertSame([], $deprecations);
    }

    public static function deprecatedUriStringProvider(): iterable
    {
        yield 'non-ASCII host' => ['https://яндекс.рф/', 'яндекс.рф'];
        yield 'host and port' => ['example.com:8080/path', 'example.com'];
    }

    public function testCreateStreamFromFileEscapesInvalidMode(): void
    {
        $factory = new HttpFactory();

        try {
            $factory->createStreamFromFile(__FILE__, "\n");
            self::fail('Expected InvalidArgumentException was not thrown.');
        } catch (\InvalidArgumentException $e) {
            self::assertSame('Invalid file opening mode: \\x0A', $e->getMessage());
            self::assertInstanceOf(\RuntimeException::class, $e->getPrevious());
        }
    }

    public function testCreateUploadedFileRejectsInvalidInferredSize(): void
    {
        $factory = new HttpFactory();
        $stream = new FnStream([
            'getSize' => static function (): int {
                return -1;
            },
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Uploaded file size must be a non-negative integer or null');

        $factory->createUploadedFile($stream);
    }
}
