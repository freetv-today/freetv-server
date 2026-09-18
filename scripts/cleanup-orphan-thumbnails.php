#!/usr/bin/env php
<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(1);
}

$arguments = $argv;
array_shift($arguments);
if ($arguments !== [] && $arguments !== ['--apply']) {
    fwrite(STDERR, "Usage: php scripts/cleanup-orphan-thumbnails.php [--apply]\n");
    exit(2);
}
$apply = $arguments === ['--apply'];

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../public/api/admin/ThumbnailOrphanCleanupService.php';

use FreeTV\Admin\ThumbnailOrphanCleanupException;
use FreeTV\Admin\ThumbnailOrphanCleanupService;

try {
    $result = (new ThumbnailOrphanCleanupService())->run($apply);
    echo json_encode(
        $result,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
    ) . PHP_EOL;
    if ($apply && $result['failed'] !== []) {
        exit(1);
    }
} catch (ThumbnailOrphanCleanupException $exception) {
    fwrite(STDERR, 'Thumbnail orphan cleanup failed: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
} catch (Throwable $exception) {
    fwrite(STDERR, "Thumbnail orphan cleanup failed: unexpected server error\n");
    exit(1);
}
