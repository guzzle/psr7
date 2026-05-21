<?php

declare(strict_types=1);

namespace GuzzleHttp\Tests\Psr7\Psr7Integration;

use GuzzleHttp\Psr7\UploadedFile;
use GuzzleHttp\Psr7\Utils;
use Http\Psr7Test\UploadedFileIntegrationTest;

final class UploadedFileTest extends UploadedFileIntegrationTest
{
    public function createSubject()
    {
        $stream = Utils::streamFor('Foobar');

        return new UploadedFile(
            $stream,
            $stream->getSize(),
            UPLOAD_ERR_OK,
            'filename.txt',
            'text/plain'
        );
    }
}
