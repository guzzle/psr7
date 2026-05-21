<?php

declare(strict_types=1);

namespace GuzzleHttp\Tests\Psr7\Psr7Integration;

use GuzzleHttp\Psr7\Request;
use Http\Psr7Test\RequestIntegrationTest;

final class RequestTest extends RequestIntegrationTest
{
    public function createSubject()
    {
        return new Request('GET', '/');
    }
}
