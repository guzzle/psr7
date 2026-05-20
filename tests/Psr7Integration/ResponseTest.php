<?php

declare(strict_types=1);

namespace GuzzleHttp\Tests\Psr7\Psr7Integration;

use GuzzleHttp\Psr7\Response;
use Http\Psr7Test\ResponseIntegrationTest;

final class ResponseTest extends ResponseIntegrationTest
{
    public function createSubject()
    {
        return new Response();
    }
}
