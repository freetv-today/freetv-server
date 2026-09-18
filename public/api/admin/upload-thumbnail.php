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

$imdb = $_POST['imdb'] ?? null;
$operation = $_POST['operation'] ?? null;
$previousUndoToken = $_POST['previous_undo_token'] ?? null;
if (!is_string($imdb) || !ThumbnailService::isValidImdb($imdb)) {
    respond(400, ['success' => false, 'message' => 'Invalid IMDb ID']);
}
if (!is_string($operation) || !in_array($operation, ['upload', 'replace'], true)) {
    respond(400, ['success' => false, 'message' => 'Operation must be upload or replace']);
}
if ($previousUndoToken !== null && !is_string($previousUndoToken)) {
    respond(400, ['success' => false, 'message' => 'Invalid previous undo token']);
}

$upload = $_FILES['image'] ?? null;
if (!is_array($upload)) {
    respond(400, ['success' => false, 'message' => 'A JPEG image is required']);
}

$uploadError = $upload['error'] ?? UPLOAD_ERR_NO_FILE;
if ($uploadError === UPLOAD_ERR_INI_SIZE || $uploadError === UPLOAD_ERR_FORM_SIZE) {
    respond(413, ['success' => false, 'message' => 'The uploaded JPEG exceeds the 10 MB limit']);
}
if ($uploadError !== UPLOAD_ERR_OK) {
    respond(400, ['success' => false, 'message' => 'The JPEG upload did not complete']);
}

$sourcePath = $upload['tmp_name'] ?? null;
$sourceSize = $upload['size'] ?? null;
if (!is_string($sourcePath) || !is_int($sourceSize) || !is_uploaded_file($sourcePath)) {
    respond(400, ['success' => false, 'message' => 'The uploaded file is invalid']);
}

try {
    $result = (new ThumbnailUploadService())->store(
        $imdb,
        $sourcePath,
        $sourceSize,
        $operation,
        $previousUndoToken
    );
    $result['exists'] = true;
    try {
        $result['global_usage'] = (new ThumbnailService())->getGlobalUsage($imdb);
    } catch (\Throwable $e) {
        error_log('Thumbnail Upload Usage Lookup Error: ' . $e->getMessage());
        $result['global_usage'] = ['show_count' => 0, 'playlist_count' => 0];
    }
    respond(200, array_merge(['success' => true], $result));
} catch (ThumbnailUploadException $e) {
    respond($e->getHttpStatus(), ['success' => false, 'message' => $e->getMessage()]);
} catch (\Throwable $e) {
    error_log('Thumbnail Upload API Error: ' . $e->getMessage());
    respond(500, ['success' => false, 'message' => 'Could not save the thumbnail']);
}
