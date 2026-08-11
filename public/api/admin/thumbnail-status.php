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

require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/ThumbnailService.php';

$imdb = $_GET['imdb'] ?? null;
if (!is_string($imdb) || !\FreeTV\Admin\ThumbnailService::isValidImdb($imdb)) {
    respond(400, ['success' => false, 'message' => 'Invalid IMDb ID']);
}

use FreeTV\Admin\ThumbnailService;

try {
    respond(200, array_merge(['success' => true], (new ThumbnailService())->getStatus($imdb)));
} catch (\Throwable $e) {
    error_log('Thumbnail Status API Error: ' . $e->getMessage());
    respond(500, ['success' => false, 'message' => 'Could not load thumbnail status']);
}
