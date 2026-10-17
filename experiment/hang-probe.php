<?php
// Temporary CI experiment: one InflateStream read whose second source read throws after the
// first source read decoded a line.
error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);
require __DIR__.'/../vendor/autoload.php';

use GuzzleHttp\Psr7;

$ctx = deflate_init(ZLIB_ENCODING_GZIP);
$script = [deflate_add($ctx, "line\n", ZLIB_SYNC_FLUSH), new \RuntimeException('Unable to read from stream')];
$calls = 0;
$source = new Psr7\FnStream([
    'isReadable' => function () { return true; },
    'isWritable' => function () { return false; },
    'isSeekable' => function () { return false; },
    'eof' => function () { return false; },
    'read' => function ($length) use (&$script, &$calls) {
        ++$calls;
        $next = array_shift($script);
        if ($next instanceof \Exception) {
            throw $next;
        }

        return $next === null ? '' : $next;
    },
]);
echo 'PHP ', PHP_VERSION, ": reading\n";
$body = new Psr7\InflateStream($source);
try {
    $result = json_encode($body->read(8192));
} catch (\Throwable $e) {
    $result = get_class($e).': '.$e->getMessage();
}
echo "returned after $calls source reads: $result\n";
