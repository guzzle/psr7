#!/usr/bin/env php
<?php

declare(strict_types=1);

// 1. Load db.json
$dbPath = __DIR__ . '/../vendor/jshttp/mime-db/db.json';
$db = json_decode(file_get_contents($dbPath), true);

// 2. Build extension -> mime-type map
$mimeTypes = [];
foreach ($db as $mimeType => $data) {
    if (!isset($data['extensions'])) {
        continue;
    }
    foreach ($data['extensions'] as $ext) {
        // First mime-type wins (don't overwrite)
        if (!isset($mimeTypes[$ext])) {
            $mimeTypes[$ext] = $mimeType;
        }
    }
}

// 3. Sort alphabetically by extension
ksort($mimeTypes, SORT_STRING);

// 4. Generate PHP code
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

// 5. Write output
file_put_contents(__DIR__ . '/../src/MimeType.php', $output);

echo "Generated src/MimeType.php with " . count($mimeTypes) . " extensions.\n";
