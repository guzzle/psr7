#!/usr/bin/env php
<?php

declare(strict_types=1);

$dbPath = __DIR__ . '/../vendor/jshttp/mime-db/db.json';
$db = json_decode(file_get_contents($dbPath), true);

$mimeTypes = [];
$mimeTypeSources = [];
foreach ($db as $mimeType => $data) {
    if (!isset($data['extensions'])) {
        continue;
    }
    $source = $data['source'] ?? null;
    foreach ($data['extensions'] as $ext) {
        // IANA source wins over non-IANA, otherwise first wins
        if (!isset($mimeTypes[$ext])) {
            $mimeTypes[$ext] = $mimeType;
            $mimeTypeSources[$ext] = $source;
        } elseif ($source === 'iana' && $mimeTypeSources[$ext] !== 'iana') {
            $mimeTypes[$ext] = $mimeType;
            $mimeTypeSources[$ext] = $source;
        }
    }
}

$overrides = [
    '3gpp' => 'video/3gpp',
    '7zip' => 'application/x-7z-compressed',
    'ac3' => 'audio/ac3',
    'bpmn' => 'application/octet-stream',
    'csr' => 'application/octet-stream',
    'deb' => 'application/x-debian-package',
    'dmg' => 'application/x-apple-diskimage',
    'dmn' => 'application/octet-stream',
    'dst' => 'application/octet-stream',
    'f4v' => 'video/mp4',
    'gzip' => 'application/gzip',
    'indd' => 'application/x-indesign',
    'iso' => 'application/x-iso9660-image',
    'jfif' => 'image/jpeg',
    'kdb' => 'application/octet-stream',
    'mp4' => 'video/mp4',
    'mpp' => 'application/vnd.ms-project',
    'mpg4' => 'video/mp4',
    'mts' => 'video/mp2t',
    'ndjson' => 'application/x-ndjson',
    'p7a' => 'application/x-pkcs7-signature',
    'p7e' => 'application/pkcs7-mime',
    'pem' => 'application/x-x509-user-cert',
    'phar' => 'application/octet-stream',
    'php3' => 'application/x-httpd-php',
    'php4' => 'application/x-httpd-php',
    'phps' => 'application/x-httpd-php-source',
    'phtml' => 'application/x-httpd-php',
    'ppa' => 'application/vnd.ms-powerpoint',
    'pv' => 'application/octet-stream',
    'pxf' => 'application/octet-stream',
    'ra' => 'audio/x-realaudio',
    'rsa' => 'application/x-pkcs7',
    'rtf' => 'text/rtf',
    'rv' => 'video/vnd.rn-realvideo',
    'sst' => 'application/octet-stream',
    'tgz' => 'application/gzip',
    'word' => 'application/msword',
    'xl' => 'application/vnd.ms-excel',
    'xsl' => 'application/xslt+xml',
    'z' => 'application/x-compress',
    'zsh' => 'text/x-scriptzsh',
];

foreach ($overrides as $ext => $mimeType) {
    $mimeTypes[$ext] = $mimeType;
}

ksort($mimeTypes, SORT_STRING);

$output = <<<'PHP'
<?php

declare(strict_types=1);

namespace GuzzleHttp\Psr7;

final class MimeType
{
    private const MIME_TYPES = [

PHP;

foreach ($mimeTypes as $ext => $mime) {
    $output .= sprintf("        '%s' => '%s',\n", $ext, $mime);
}

$output .= <<<'PHP'
    ];

    /**
     * Determines the mimetype of a file by looking at its extension.
     *
     * @see https://raw.githubusercontent.com/jshttp/mime-db/master/db.json
     */
    public static function fromFilename(string $filename): ?string
    {
        return self::fromExtension(pathinfo($filename, PATHINFO_EXTENSION));
    }

    /**
     * Maps a file extensions to a mimetype.
     *
     * @see https://raw.githubusercontent.com/jshttp/mime-db/master/db.json
     */
    public static function fromExtension(string $extension): ?string
    {
        return self::MIME_TYPES[strtolower($extension)] ?? null;
    }
}

PHP;

file_put_contents(__DIR__ . '/../src/MimeType.php', $output);

echo "Generated src/MimeType.php with " . count($mimeTypes) . " extensions.\n";
