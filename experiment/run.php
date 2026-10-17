<?php
// Temporary CI experiment: does InflateStream keep decoded bytes and report failures when a
// source read fails part way through a filtered read? Exits 1 when a checked scenario fails.
error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);
require __DIR__.'/../vendor/autoload.php';

use GuzzleHttp\Psr7;

const EXPECTED = "{\"n\":\"A\"}\n{\"n\":\"B\"}\n{\"n\":\"C\"}\n";

function segments(): array
{
    $ctx = deflate_init(ZLIB_ENCODING_GZIP);
    $out = [];
    foreach (["{\"n\":\"A\"}\n", "{\"n\":\"B\"}\n", "{\"n\":\"C\"}\n"] as $i => $line) {
        $out[] = deflate_add($ctx, $line, $i === 2 ? ZLIB_FINISH : ZLIB_SYNC_FLUSH);
    }

    return $out;
}

function show(string $s): string
{
    return $s === '' ? "''" : json_encode($s);
}

function describe(\Throwable $e): string
{
    $name = substr(strrchr('\\'.get_class($e), '\\'), 1);
    $prev = $e->getPrevious();

    return $name.'('.$e->getMessage().($prev ? ' <- '.substr(strrchr('\\'.get_class($prev), '\\'), 1).': '.$prev->getMessage() : '').')';
}

/** Reads until EOF, retrying after failures, and records what the consumer saw. */
function consume(Psr7\InflateStream $body, string $mode, int $max, ?callable $between = null): array
{
    $got = '';
    $events = [];
    $t0 = microtime(true);
    for ($i = 0; $i < $max && !$body->eof(); $i++) {
        try {
            $d = $mode === 'read' ? $body->read(8192) : $body->getContents();
            $events[] = sprintf('%.1f:%s', microtime(true) - $t0, show($d));
            $got .= $d;
        } catch (\Throwable $e) {
            $events[] = sprintf('%.1f:%s', microtime(true) - $t0, describe($e));
        }
        if ($between) {
            $between($got);
        }
    }

    return [$got, implode(' | ', $events)];
}

function scripted(array $script): Psr7\FnStream
{
    return new Psr7\FnStream([
        'isReadable' => function () { return true; },
        'isWritable' => function () { return false; },
        'isSeekable' => function () { return false; },
        'eof' => function () use (&$script) { return $script === []; },
        'read' => function ($length) use (&$script) {
            $next = array_shift($script);
            if ($next instanceof \Exception) {
                throw $next;
            }

            return $next === null ? '' : $next;
        },
        'getMetadata' => function ($key = null) { return $key === 'timed_out' ? false : null; },
    ]);
}

function socketPair(): array
{
    $domain = DIRECTORY_SEPARATOR === '\\' ? STREAM_PF_INET : STREAM_PF_UNIX;

    return stream_socket_pair($domain, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
}

$results = [];
$failed = false;
$report = function (string $name, string $status, string $detail, bool $checked = true) use (&$results, &$failed) {
    if ($checked && $status !== 'OK') {
        $failed = true;
    }
    $results[] = sprintf("%-34s %-16s %s", $name, $checked ? $status : 'info: '.$status, $detail);
};

// 1. Scripted source: segment, timeout-style failure, segment, failure, final segment.
foreach (['read' => true, 'getContents' => false] as $mode => $checked) {
    [$a, $b, $c] = segments();
    $timeout = new \RuntimeException('Unable to read from stream');
    $body = new Psr7\InflateStream(scripted([$a, $timeout, $b, $timeout, $c]));
    [$got, $events] = consume($body, $mode, 10);
    $report("scripted-retry-$mode", $got === EXPECTED ? 'OK' : 'DATA LOST', $events, $checked);
}

// 2. Same with a failure that is not a RuntimeException.
[$a, $b, $c] = segments();
$logic = new \LogicException('not a runtime failure');
[$got, $events] = consume(new Psr7\InflateStream(scripted([$a, $logic, $b, $logic, $c])), 'read', 10);
$report('scripted-retry-logic-exception', $got === EXPECTED ? 'OK' : 'DATA LOST', $events, false);

// 3. Terminal failure (connection reset style): segment, then the source fails and reports EOF.
$state = 0;
[$a] = segments();
$source = new Psr7\FnStream([
    'isReadable' => function () { return true; },
    'isWritable' => function () { return false; },
    'isSeekable' => function () { return false; },
    'eof' => function () use (&$state) { return $state > 1; },
    'read' => function ($length) use (&$state, $a) {
        ++$state;
        if ($state === 1) {
            return $a;
        }
        if ($state === 2) {
            throw new \RuntimeException('Unable to read from stream: connection reset');
        }

        return '';
    },
    'getMetadata' => function ($key = null) { return $key === 'timed_out' ? false : null; },
]);
[$got, $events] = consume(new Psr7\InflateStream($source), 'read', 5);
$sawError = strpos($events, 'Exception(') !== false;
$report('terminal-failure', $got !== "{\"n\":\"A\"}\n" ? 'DATA LOST' : ($sawError ? 'OK' : 'ERROR SWALLOWED'), $events);

// 4. Real socket with stream_set_timeout(): each segment is written only after the previous
// read has had the chance to time out.
foreach (['read' => true, 'getContents' => false] as $mode => $checked) {
    [$writer, $reader] = socketPair();
    stream_set_timeout($reader, 0, 200000);
    $pending = segments();
    fwrite($writer, array_shift($pending));
    $reads = 0;
    $body = new Psr7\InflateStream(new Psr7\Stream($reader));
    [$got, $events] = consume($body, $mode, 12, function (string $got) use (&$pending, $writer, &$reads) {
        // Feed the next segment once two reads have happened since the previous one.
        if (++$reads % 2 === 0 && $pending !== []) {
            fwrite($writer, array_shift($pending));
            if ($pending === []) {
                fclose($writer);
            }
        }
    });
    $report("socket-timeout-$mode", $got === EXPECTED ? 'OK' : 'DATA LOST', $events, $checked);
}

// 5. Guzzle 7.15 StreamHandler end to end, when Guzzle is installed.
if (class_exists(\GuzzleHttp\Handler\StreamHandler::class)) {
    $pem = null;
    if (function_exists('openssl_pkey_new')) {
        $key = @openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        $csr = $key ? @openssl_csr_new(['commonName' => '127.0.0.1'], $key, ['digest_alg' => 'sha256']) : false;
        $crt = $csr ? @openssl_csr_sign($csr, null, $key, 2, ['digest_alg' => 'sha256']) : false;
        if ($crt && openssl_x509_export($crt, $certPem) && openssl_pkey_export($key, $keyPem)) {
            $pem = sys_get_temp_dir().DIRECTORY_SEPARATOR.'psr7-experiment.pem';
            file_put_contents($pem, $certPem.$keyPem);
        }
    }
    $port = 18900;
    foreach ([['http', 'gzip-cl'], ['http', 'chunked-gzip'], ['https', 'gzip-cl']] as $case) {
        [$scheme, $path] = $case;
        $name = "guzzle-$scheme-$path";
        if ($scheme === 'https' && $pem === null) {
            $report($name, 'skipped', 'no openssl certificate', false);
            continue;
        }
        ++$port;
        $cmd = escapeshellarg(PHP_BINARY).' '.escapeshellarg(__DIR__.'/server.php').' '.$port.' 1.0 '.escapeshellarg($scheme === 'https' ? $pem : '');
        $proc = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        $line = trim((string) fgets($pipes[1]));
        if ($line !== 'listening') {
            $report($name, 'SERVER FAILED', $line.' '.stream_get_contents($pipes[2]));
            continue;
        }
        try {
            $response = (new \GuzzleHttp\Handler\StreamHandler())(new Psr7\Request('GET', "$scheme://127.0.0.1:$port/$path"), [
                'stream' => true,
                'decode_content' => true,
                'timeout' => 0.5,
                'verify' => false,
            ])->wait();
            $body = $response->getBody();
            if (!$body instanceof Psr7\InflateStream) {
                $report($name, 'UNEXPECTED BODY', get_class($body));
            } else {
                [$got, $events] = consume($body, 'read', 30);
                $report($name, $got === EXPECTED ? 'OK' : 'DATA LOST', $events);
            }
        } catch (\Throwable $e) {
            $report($name, 'REQUEST FAILED', describe($e));
        }
        foreach ($pipes as $pipe) {
            fclose($pipe);
        }
        proc_close($proc);
    }
}

echo 'PHP ', PHP_VERSION, ' on ', PHP_OS_FAMILY ?? PHP_OS, "\n", implode("\n", $results), "\n";
exit($failed ? 1 : 0);
