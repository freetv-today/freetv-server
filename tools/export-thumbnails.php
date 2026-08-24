<?php

$arguments = $argv;
array_shift($arguments);
if (count($arguments) !== 1 || trim($arguments[0]) === '') {
    fwrite(STDERR, "Usage: php tools/export-thumbnails.php <staging-directory>\n");
    exit(2);
}

$serverRoot = dirname(__DIR__);
require_once $serverRoot . '/public/api/admin/publication/ThumbnailExportService.php';

use FreeTV\Admin\Publication\ThumbnailExportException;
use FreeTV\Admin\Publication\ThumbnailExportService;

try {
    $manifest = (new ThumbnailExportService())->export($arguments[0]);
    fwrite(
        STDOUT,
        sprintf(
            "Thumbnail export complete: %d thumbnails, %d bytes\n",
            $manifest['dataset']['thumbnail_count'],
            $manifest['dataset']['total_bytes']
        )
    );
} catch (ThumbnailExportException $exception) {
    fwrite(STDERR, 'Thumbnail export failed: ' . $exception->getMessage() . "\n");
    exit(1);
} catch (Throwable $exception) {
    fwrite(STDERR, "Thumbnail export failed: unexpected server error\n");
    exit(1);
}
