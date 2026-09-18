<?php

declare(strict_types=1);

require_once __DIR__ . '/Authorization.php';
\FreeTV\Admin\requireRole('admin');

header('Content-Type: application/json; charset=utf-8');

function respondToSnapshotCreate(int $status, array $payload): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    respondToSnapshotCreate(405, ['success' => false, 'message' => 'Method not allowed']);
}

if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/ProductionContentSnapshotService.php';
require_once __DIR__ . '/ProductionContentSnapshotArchiveService.php';

use FreeTV\Admin\ProductionContentSnapshotArchiveException;
use FreeTV\Admin\ProductionContentSnapshotArchiveService;
use FreeTV\Admin\ProductionContentSnapshotException;
use FreeTV\Admin\ProductionContentSnapshotService;

try {
    $snapshot = (new ProductionContentSnapshotService())->create();
    $snapshotName = basename($snapshot['path']);
    if (!ProductionContentSnapshotArchiveService::isValidSnapshotName($snapshotName)) {
        throw new RuntimeException('Snapshot service returned an invalid snapshot identifier');
    }
    (new ProductionContentSnapshotArchiveService())->create($snapshotName);

    respondToSnapshotCreate(201, [
        'success' => true,
        'status' => 'created',
        'snapshot' => [
            'name' => $snapshotName,
            'production_snapshot_at' => $snapshot['production_snapshot_at'],
            'capture_completed_at' => $snapshot['capture_completed_at'],
            'counts' => $snapshot['counts'],
            'download_available' => true,
        ],
    ]);
} catch (ProductionContentSnapshotArchiveException $exception) {
    error_log('Production Snapshot Archive Error: ' . $exception->getMessage());
    respondToSnapshotCreate(500, [
        'success' => false,
        'message' => 'Snapshot content was created, but its download archive could not be prepared',
    ]);
} catch (ProductionContentSnapshotException $exception) {
    error_log('Production Snapshot Creation Error: ' . $exception->getMessage());
    respondToSnapshotCreate(500, [
        'success' => false,
        'message' => 'Could not create the production content snapshot',
    ]);
} catch (Throwable $exception) {
    error_log('Production Snapshot Error: ' . $exception->getMessage());
    respondToSnapshotCreate(500, [
        'success' => false,
        'message' => 'Could not create the production content snapshot',
    ]);
}
