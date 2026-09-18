<?php

require_once __DIR__ . '/../Authorization.php';
\FreeTV\Admin\requireRole('admin');

header('Content-Type: application/json');

function respondToPublicationRequest(int $status, array $payload): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    respondToPublicationRequest(405, ['success' => false, 'message' => 'Method not allowed']);
}

require_once __DIR__ . '/../../../../vendor/autoload.php';
require_once __DIR__ . '/../Database.php';
require_once __DIR__ . '/PublicationException.php';
require_once __DIR__ . '/PublicationTimestamp.php';
require_once __DIR__ . '/PlaylistPublicationSerializer.php';
require_once __DIR__ . '/PlaylistIndexSerializer.php';
require_once __DIR__ . '/PlaylistPublicationService.php';

use FreeTV\Admin\Publication\PlaylistPublicationService;
use FreeTV\Admin\Publication\PublicationException;

$request = json_decode(file_get_contents('php://input'));
if (json_last_error() !== JSON_ERROR_NONE || !is_object($request)) {
    respondToPublicationRequest(400, ['success' => false, 'message' => 'Invalid JSON request body']);
}
if (
    !property_exists($request, 'filename')
    || !is_string($request->filename)
    || trim($request->filename) === ''
) {
    respondToPublicationRequest(400, ['success' => false, 'message' => 'Missing or invalid playlist filename']);
}

try {
    $result = (new PlaylistPublicationService())->publish(trim($request->filename));
    respondToPublicationRequest(200, [
        'success' => true,
        'message' => 'Playlist published successfully',
        'publication' => $result,
    ]);
} catch (PublicationException $exception) {
    error_log('Playlist Publication Error: ' . $exception->getMessage());
    respondToPublicationRequest($exception->getHttpStatus(), [
        'success' => false,
        'message' => $exception->getMessage(),
    ]);
} catch (Throwable $exception) {
    error_log('Playlist Publication Error: ' . $exception->getMessage());
    respondToPublicationRequest(500, [
        'success' => false,
        'message' => 'Playlist publication failed',
    ]);
}
