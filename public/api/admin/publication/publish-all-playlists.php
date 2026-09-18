<?php

require_once __DIR__ . '/../Authorization.php';
\FreeTV\Admin\requireRole('admin');

header('Content-Type: application/json');

function respondToAllPlaylistsPublication(int $status, array $payload): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    respondToAllPlaylistsPublication(405, ['success' => false, 'message' => 'Method not allowed']);
}

require_once __DIR__ . '/../../../../vendor/autoload.php';
require_once __DIR__ . '/../Database.php';
require_once __DIR__ . '/../Settings.php';
require_once __DIR__ . '/AllPlaylistsPublicationService.php';

use FreeTV\Admin\Publication\AllPlaylistsPublicationService;
use FreeTV\Admin\Publication\PublicationException;

try {
    $result = (new AllPlaylistsPublicationService())->publish();
    respondToAllPlaylistsPublication(200, [
        'success' => true,
        'message' => $result['no_op']
            ? 'No unpublished show or playlist changes'
            : 'All changed show and playlist content published successfully',
        'publication' => $result,
    ]);
} catch (PublicationException $exception) {
    error_log('All Playlists Publication Error: ' . $exception->getMessage());
    respondToAllPlaylistsPublication($exception->getHttpStatus(), [
        'success' => false,
        'message' => $exception->getMessage(),
    ]);
} catch (Throwable $exception) {
    error_log('All Playlists Publication Error: ' . $exception->getMessage());
    respondToAllPlaylistsPublication(500, [
        'success' => false,
        'message' => 'Show and playlist content publication failed',
    ]);
}
