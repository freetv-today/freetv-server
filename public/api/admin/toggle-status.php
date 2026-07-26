<?php

header('Content-Type: application/json');

session_start();
if (!isset($_SESSION['admin'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

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

    $newStatus = $connection->transaction(function () use (
        $connection,
        $playlistRow,
        $identifier
    ) {
        $showRow = Database::table('playlist_shows')
            ->where('playlist_id', $playlistRow->id)
            ->where('identifier', $identifier)
            ->lockForUpdate()
            ->first(['id', 'playlist_id', 'identifier', 'status']);

        if (!$showRow || $showRow->identifier !== $identifier) {
            return null;
        }

        if ($showRow->status === 'active') {
            $status = 'disabled';
        } elseif ($showRow->status === 'disabled') {
            $status = 'active';
        } else {
            error_log('Toggle Status Unexpected State: ' . json_encode([
                'playlist_id' => $showRow->playlist_id,
                'playlist_show_id' => $showRow->id,
                'identifier' => $showRow->identifier,
                'status' => $showRow->status,
            ]));
            throw new \RuntimeException('Unexpected playlist show status');
        }

        $updatedRows = Database::table('playlist_shows')
            ->where('id', $showRow->id)
            ->where('playlist_id', $playlistRow->id)
            ->where('identifier', $identifier)
            ->update(['status' => $status]);

        if ($updatedRows !== 1) {
            throw new \RuntimeException('Status update did not affect exactly one playlist show');
        }

        Database::table('playlists')
            ->where('id', $playlistRow->id)
            ->update(['lastupdated' => $connection->raw('CURRENT_TIMESTAMP')]);

        return $status;
    });

    if ($newStatus === null) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Show not found']);
        exit;
    }

    echo json_encode([
        'success' => true,
        'message' => 'Status updated',
        'status' => $newStatus,
    ]);
} catch (\Throwable $e) {
    error_log('Toggle Status API Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
