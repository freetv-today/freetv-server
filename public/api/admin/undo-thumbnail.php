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

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    respond(405, ['success' => false, 'message' => 'Method not allowed']);
}

require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/ThumbnailService.php';
require_once __DIR__ . '/ThumbnailUploadService.php';

use FreeTV\Admin\ThumbnailService;
use FreeTV\Admin\ThumbnailUploadException;
use FreeTV\Admin\ThumbnailUploadService;

$input = json_decode(file_get_contents('php://input'), true);
$token = is_array($input) ? ($input['undo_token'] ?? null) : null;
if (!is_string($token)) {
    respond(400, ['success' => false, 'message' => 'Invalid undo token']);
}

try {
    $result = (new ThumbnailUploadService())->undo($token);
    try {
        $result['global_usage'] = (new ThumbnailService())->getGlobalUsage($result['imdb']);
    } catch (\Throwable $e) {
        error_log('Thumbnail Undo Usage Lookup Error: ' . $e->getMessage());
        $result['global_usage'] = ['show_count' => 0, 'playlist_count' => 0];
    }
    respond(200, array_merge(['success' => true], $result));
} catch (ThumbnailUploadException $e) {
    respond($e->getHttpStatus(), ['success' => false, 'message' => $e->getMessage()]);
} catch (\Throwable $e) {
    error_log('Thumbnail Undo API Error: ' . $e->getMessage());
    respond(500, ['success' => false, 'message' => 'Could not undo the thumbnail change']);
}
