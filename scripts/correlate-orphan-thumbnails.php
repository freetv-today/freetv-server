#!/usr/bin/env php
<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(1);
}

$arguments = $argv;
array_shift($arguments);
if (count($arguments) !== 2
    || trim($arguments[0]) === ''
    || trim($arguments[1]) === ''
) {
    fwrite(
        STDERR,
        "Usage: php scripts/correlate-orphan-thumbnails.php <results-json> <source-playlist-directory>\n"
    );
    exit(2);
}

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../public/api/admin/DarkThumbnailCorrelationService.php';

use FreeTV\Admin\DarkThumbnailCorrelationException;
use FreeTV\Admin\DarkThumbnailCorrelationService;

try {
    echo json_encode(
        (new DarkThumbnailCorrelationService())->correlate($arguments[0], $arguments[1]),
        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
    ) . PHP_EOL;
} catch (DarkThumbnailCorrelationException $exception) {
    fwrite(STDERR, 'Orphan thumbnail correlation failed: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
} catch (Throwable $exception) {
    fwrite(STDERR, "Orphan thumbnail correlation failed: unexpected server error\n");
    exit(1);
}
