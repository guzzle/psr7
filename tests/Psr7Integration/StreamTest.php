<?php

declare(strict_types=1);

namespace GuzzleHttp\Tests\Psr7\Psr7Integration;

use GuzzleHttp\Psr7\Utils;
use Http\Psr7Test\StreamIntegrationTest;

final class StreamTest extends StreamIntegrationTest
{
    public function createStream($data)
    {
        return Utils::streamFor($data);
    }
}
