<?php

require_once __DIR__ . '/../Authorization.php';
\FreeTV\Admin\requireRole('admin');

header('Content-Type: application/json');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    header('Allow: GET');
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

require_once __DIR__ . '/../../../../vendor/autoload.php';
require_once __DIR__ . '/../Database.php';
require_once __DIR__ . '/../Settings.php';
require_once __DIR__ . '/PublicationException.php';
require_once __DIR__ . '/PublicationTimestamp.php';
require_once __DIR__ . '/PublicationSemanticHasher.php';
require_once __DIR__ . '/PublicationSemanticDelta.php';
require_once __DIR__ . '/PlaylistPublicationSerializer.php';
require_once __DIR__ . '/ConfigPublicationSerializer.php';
require_once __DIR__ . '/PublicationStatusService.php';

use FreeTV\Admin\Publication\PublicationStatusService;

try {
    echo json_encode(
        ['success' => true, 'status' => (new PublicationStatusService())->status()],
        JSON_UNESCAPED_SLASHES
    );
} catch (Throwable $exception) {
    error_log('Publication Status Error: ' . $exception->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Could not determine publication status']);
}
