<?php

header('Content-Type: application/json');

require_once __DIR__ . '/Authorization.php';
\FreeTV\Admin\requireRole('editor');

ini_set('display_errors', 0);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/Database.php';

use FreeTV\Admin\Database;

$requestBody = file_get_contents('php://input');
$requestObject = json_decode($requestBody);
if (json_last_error() !== JSON_ERROR_NONE || !is_object($requestObject)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON request body']);
    exit;
}

$input = get_object_vars($requestObject);
$playlist = $input['playlist'] ?? null;
$identifier = $input['identifier'] ?? null;

if (
    !is_string($playlist)
    || trim($playlist) === ''
    || !is_string($identifier)
    || trim($identifier) === ''
) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing or invalid playlist or identifier']);
    exit;
}

if (
    !preg_match('/^[a-zA-Z0-9_-]+\.json$/', $playlist)
    || strcasecmp($playlist, 'index.json') === 0
) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid playlist filename']);
    exit;
}

try {
    $capsule = Database::init();
    $connection = $capsule->getConnection();

    $playlistRow = Database::table('playlists')
        ->where('filename', $playlist)
        ->first(['id', 'filename']);

    if (!$playlistRow || $playlistRow->filename !== $playlist) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Playlist not found']);
        exit;
    }

    $showDeleted = $connection->transaction(function () use (
        $connection,
        $playlistRow,
        $identifier
    ) {
        $showRow = Database::table('playlist_shows')
            ->where('playlist_id', $playlistRow->id)
            ->where('identifier', $identifier)
            ->lockForUpdate()
            ->first(['id', 'identifier']);

        if (!$showRow || $showRow->identifier !== $identifier) {
            return false;
        }

        $deletedRows = Database::table('playlist_shows')
            ->where('id', $showRow->id)
            ->where('playlist_id', $playlistRow->id)
            ->delete();

        if ($deletedRows !== 1) {
            throw new \RuntimeException('Delete did not affect exactly one playlist show');
        }

        Database::table('playlists')
            ->where('id', $playlistRow->id)
            ->update(['lastupdated' => $connection->raw('CURRENT_TIMESTAMP')]);

        return true;
    });

    if (!$showDeleted) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Show not found']);
        exit;
    }

    echo json_encode(['success' => true, 'message' => 'Show deleted']);
} catch (\Throwable $e) {
    error_log('Delete Show API Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
