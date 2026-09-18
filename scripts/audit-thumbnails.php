#!/usr/bin/env php
<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(1);
}

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../public/api/admin/ThumbnailIntegrityService.php';

use FreeTV\Admin\ThumbnailIntegrityService;

try {
    echo json_encode(
        (new ThumbnailIntegrityService())->audit(),
        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
    ) . PHP_EOL;
} catch (Throwable $exception) {
    fwrite(STDERR, 'Thumbnail integrity audit failed: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}
