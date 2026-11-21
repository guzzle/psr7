<?php

declare(strict_types=1);

namespace GuzzleHttp\Tests\Psr7;

use GuzzleHttp\Psr7\FnStream;
use GuzzleHttp\Psr7\HttpFactory;
use PHPUnit\Framework\TestCase;

class HttpFactoryTest extends TestCase
{
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
