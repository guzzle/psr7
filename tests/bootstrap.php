<?php

declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';

// Define the GuzzleHttp\Psr7 stream-function overrides before any test runs, so
// they bind ahead of the first call site. Loading them here rather than through
// composer autoload keeps them out of static analysis, which only bootstraps the
// composer autoloader.
require __DIR__.'/PhpStreamMock.php';
