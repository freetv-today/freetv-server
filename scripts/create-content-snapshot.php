#!/usr/bin/env php
<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(1);
}

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../public/api/admin/ProductionContentSnapshotService.php';

use FreeTV\Admin\ProductionContentSnapshotService;

try {
    $result = (new ProductionContentSnapshotService())->create();
    echo 'Snapshot path: ' . $result['path'] . PHP_EOL;
    echo 'Production snapshot at: ' . $result['production_snapshot_at'] . PHP_EOL;
    echo 'Capture completed at: ' . $result['capture_completed_at'] . PHP_EOL;
    echo 'Counts: playlists=' . $result['counts']['playlists']
        . ' shows=' . $result['counts']['shows']
        . ' thumbnails=' . $result['counts']['thumbnails'] . PHP_EOL;
} catch (Throwable $exception) {
    fwrite(STDERR, 'Snapshot failed: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}
