<?php

declare(strict_types=1);

namespace GuzzleHttp\Tests\Psr7\Psr7Integration;

use GuzzleHttp\Psr7\ServerRequest;
use Http\Psr7Test\ServerRequestIntegrationTest;

final class ServerRequestTest extends ServerRequestIntegrationTest
{
    public function createSubject()
    {
        return new ServerRequest('GET', '/', [], null, '1.1', $_SERVER);
    }
}
