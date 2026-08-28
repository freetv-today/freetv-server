<?php

require_once __DIR__ . '/Authorization.php';
\FreeTV\Admin\requireRole('admin');

header('Content-Type: application/json');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    header('Allow: GET');
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/DataSnapshotStatusService.php';

use FreeTV\Admin\DataSnapshot\DataSnapshotStatusService;
use FreeTV\Admin\DataSnapshot\DataSnapshotSourceException;

try {
    echo json_encode(
        ['success' => true] + (new DataSnapshotStatusService())->status(),
        JSON_UNESCAPED_SLASHES
    );
} catch (InvalidArgumentException|DataSnapshotSourceException $exception) {
    error_log('Data Snapshot Source Error: ' . $exception->getMessage());
    http_response_code(502);
    echo json_encode([
        'success' => false,
        'message' => 'Could not load a valid official dataset snapshot',
    ]);
} catch (Throwable $exception) {
    error_log('Data Snapshot Status Error: ' . $exception->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Could not determine data snapshot status',
    ]);
}
