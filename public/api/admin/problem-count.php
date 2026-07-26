<?php

header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

session_start();
if (!isset($_SESSION['admin'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

ini_set('display_errors', 0);

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/Database.php';

use FreeTV\Admin\Database;

$playlist = $_GET['playlist'] ?? null;

if (!is_string($playlist) || trim($playlist) === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing or invalid playlist']);
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
    Database::init();

    $playlistRow = Database::table('playlists')
        ->where('filename', $playlist)
        ->first(['id', 'filename']);

    if (!$playlistRow || $playlistRow->filename !== $playlist) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Playlist not found']);
        exit;
    }

    $reported = Database::table('problem_reports')
        ->where('playlist_id', $playlistRow->id)
        ->where('status', 'reported')
        ->count();

    $disabled = Database::table('playlist_shows')
        ->where('playlist_id', $playlistRow->id)
        ->where('status', 'disabled')
        ->count();

    $reported = (int) $reported;
    $disabled = (int) $disabled;

    echo json_encode([
        'success' => true,
        'reported' => $reported,
        'disabled' => $disabled,
        'total' => $reported + $disabled,
    ]);
} catch (\Throwable $e) {
    error_log('Problem Count API Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
