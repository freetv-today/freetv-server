<?php

require_once __DIR__ . '/../Authorization.php';
\FreeTV\Admin\requireRole('admin');

header('Content-Type: application/json');

function respondToConfigPublicationRequest(int $status, array $payload): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    respondToConfigPublicationRequest(405, ['success' => false, 'message' => 'Method not allowed']);
}

require_once __DIR__ . '/../../../../vendor/autoload.php';
require_once __DIR__ . '/../Database.php';
require_once __DIR__ . '/../Settings.php';
require_once __DIR__ . '/PublicationException.php';
require_once __DIR__ . '/PublicationTimestamp.php';
require_once __DIR__ . '/ConfigPublicationSerializer.php';
require_once __DIR__ . '/ConfigPublicationService.php';

use FreeTV\Admin\Publication\ConfigPublicationService;
use FreeTV\Admin\Publication\PublicationException;

try {
    $result = (new ConfigPublicationService())->publish();
    respondToConfigPublicationRequest(200, [
        'success' => true,
        'message' => 'Viewer settings published successfully',
        'publication' => $result,
    ]);
} catch (PublicationException $exception) {
    error_log('Config Publication Error: ' . $exception->getMessage());
    respondToConfigPublicationRequest($exception->getHttpStatus(), [
        'success' => false,
        'message' => $exception->getMessage(),
    ]);
} catch (Throwable $exception) {
    error_log('Config Publication Error: ' . $exception->getMessage());
    respondToConfigPublicationRequest(500, [
        'success' => false,
        'message' => 'Viewer settings publication failed',
    ]);
}
