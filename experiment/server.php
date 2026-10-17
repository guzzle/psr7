<?php
// Temporary CI experiment server: answers one request with three gzip NDJSON lines sent a gap apart.
// Usage: php server.php <port> <gap seconds> [tls pem]
$port = (int) $argv[1];
$gap = (float) $argv[2];
$tls = isset($argv[3]) && $argv[3] !== '' ? $argv[3] : null;
$server = $tls
    ? stream_socket_server("tls://127.0.0.1:$port", $errno, $errstr, STREAM_SERVER_BIND | STREAM_SERVER_LISTEN, stream_context_create(['ssl' => ['local_cert' => $tls, 'verify_peer' => false]]))
    : stream_socket_server("tcp://127.0.0.1:$port", $errno, $errstr);
if (!$server) {
    fwrite(STDOUT, "listen failed: $errstr\n");
    exit(1);
}
fwrite(STDOUT, "listening\n");
fflush(STDOUT);

$conn = @stream_socket_accept($server, 30);
if (!$conn) {
    exit(1);
}
$req = '';
while (strpos($req, "\r\n\r\n") === false && !feof($conn)) {
    $req .= fread($conn, 8192);
}
preg_match('#^GET /(\S*) #', $req, $m);
$mode = isset($m[1]) ? $m[1] : 'gzip-cl';
$chunked = strpos($mode, 'chunked') !== false;

$ctx = deflate_init(ZLIB_ENCODING_GZIP);
$segments = [];
foreach (["{\"n\":\"A\"}\n", "{\"n\":\"B\"}\n", "{\"n\":\"C\"}\n"] as $i => $line) {
    $segments[] = deflate_add($ctx, $line, $i === 2 ? ZLIB_FINISH : ZLIB_SYNC_FLUSH);
}

$head = "HTTP/1.1 200 OK\r\nContent-Type: application/x-ndjson\r\nConnection: close\r\nContent-Encoding: gzip\r\n";
$head .= $chunked ? "Transfer-Encoding: chunked\r\n" : 'Content-Length: '.strlen(implode('', $segments))."\r\n";
$out = $head."\r\n";
foreach ($segments as $i => $seg) {
    if ($i > 0) {
        usleep((int) ($gap * 1e6));
    }
    @fwrite($conn, $out.($chunked ? dechex(strlen($seg))."\r\n".$seg."\r\n" : $seg));
    $out = '';
}
if ($chunked) {
    @fwrite($conn, "0\r\n\r\n");
}
fclose($conn);
