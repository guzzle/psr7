<?php

declare(strict_types=1);

namespace GuzzleHttp\Tests\Psr7;

use GuzzleHttp\Psr7\FnStream;
use GuzzleHttp\Psr7\HttpFactory;
use PHPUnit\Framework\TestCase;

class HttpFactoryTest extends TestCase
{
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
