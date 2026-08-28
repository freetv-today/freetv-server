<?php

declare(strict_types=1);

require_once __DIR__ . '/Authorization.php';
\FreeTV\Admin\requireRole('admin');

function respondToSnapshotDownload(int $status, array $payload): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    header('Allow: GET');
    respondToSnapshotDownload(405, ['success' => false, 'message' => 'Method not allowed']);
}

require_once __DIR__ . '/ProductionContentSnapshotArchiveService.php';

$snapshotName = $_GET['snapshot'] ?? null;
if (!is_string($snapshotName)
    || !\FreeTV\Admin\ProductionContentSnapshotArchiveService::isValidSnapshotName($snapshotName)
) {
    respondToSnapshotDownload(400, ['success' => false, 'message' => 'Invalid snapshot name']);
}

if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

use FreeTV\Admin\ProductionContentSnapshotArchiveException;
use FreeTV\Admin\ProductionContentSnapshotArchiveService;

try {
    $archive = (new ProductionContentSnapshotArchiveService())->resolveArchive($snapshotName);
    $stream = fopen($archive['path'], 'rb');
    if ($stream === false) {
        throw new ProductionContentSnapshotArchiveException('Snapshot archive not found', 404);
    }

    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . $archive['name'] . '.zip"');
    header('Content-Length: ' . $archive['bytes']);
    header('Cache-Control: private, no-store');
    header('X-Content-Type-Options: nosniff');

    while (!feof($stream)) {
        $chunk = fread($stream, 1024 * 1024);
        if ($chunk === false) {
            error_log('Production Snapshot Download Error: Could not read snapshot archive');
            break;
        }
        echo $chunk;
    }
    fclose($stream);
    exit;
} catch (ProductionContentSnapshotArchiveException $exception) {
    error_log('Production Snapshot Download Error: ' . $exception->getMessage());
    respondToSnapshotDownload($exception->getHttpStatus(), [
        'success' => false,
        'message' => $exception->getHttpStatus() === 404
            ? 'Snapshot archive not found'
            : 'Could not download the snapshot archive',
    ]);
} catch (Throwable $exception) {
    error_log('Production Snapshot Download Error: ' . $exception->getMessage());
    respondToSnapshotDownload(500, [
        'success' => false,
        'message' => 'Could not download the snapshot archive',
    ]);
}
