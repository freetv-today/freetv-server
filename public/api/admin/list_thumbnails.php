<?php

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/Authorization.php';
\FreeTV\Admin\requireRole('editor');

function respond(int $status, array $payload): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    header('Allow: GET');
    respond(405, ['success' => false, 'message' => 'Method not allowed']);
}

$playlist = $_GET['playlist'] ?? null;
if (!is_string($playlist) || trim($playlist) === '') {
    respond(400, ['success' => false, 'message' => 'Missing or invalid playlist filename']);
}

require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/ThumbnailService.php';

use FreeTV\Admin\ThumbnailService;

try {
    $overview = (new ThumbnailService())->getPlaylistOverview(trim($playlist));
    if ($overview === null) {
        respond(404, ['success' => false, 'message' => 'Playlist not found']);
    }

    respond(200, array_merge(['success' => true], $overview));
} catch (\Throwable $e) {
    error_log('List Thumbnails API Error: ' . $e->getMessage());
    respond(500, ['success' => false, 'message' => 'Database error']);
}
