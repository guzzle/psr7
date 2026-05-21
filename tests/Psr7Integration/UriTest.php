<?php

declare(strict_types=1);

namespace GuzzleHttp\Tests\Psr7\Psr7Integration;

use GuzzleHttp\Psr7\Uri;
use Http\Psr7Test\UriIntegrationTest;

final class UriTest extends UriIntegrationTest
{
    public function createUri($uri)
    {
        return new Uri($uri);
    }
}
