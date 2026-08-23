<?php

$arguments = $argv;
array_shift($arguments);
if (count($arguments) !== 1 || trim($arguments[0]) === '') {
    fwrite(STDERR, "Usage: php tools/export-viewer-data.php <staging-directory>\n");
    exit(2);
}

$serverRoot = dirname(__DIR__);
$autoload = $serverRoot . '/vendor/autoload.php';
if (is_file($autoload)) {
    require_once $autoload;
}
require_once $serverRoot . '/public/api/admin/publication/DataExportService.php';

use FreeTV\Admin\Publication\DataExportService;
use FreeTV\Admin\Publication\PublicationException;

try {
    $manifest = (new DataExportService())->export($arguments[0]);
    fwrite(
        STDOUT,
        sprintf(
            "Data export complete: %d playlists, %d shows\n",
            $manifest['dataset']['playlist_count'],
            $manifest['dataset']['show_count']
        )
    );
} catch (PublicationException $exception) {
    fwrite(STDERR, 'Data export failed: ' . $exception->getMessage() . "\n");
    exit(1);
} catch (Throwable $exception) {
    fwrite(STDERR, "Data export failed: unexpected server error\n");
    exit(1);
}
