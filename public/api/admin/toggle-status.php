<?php

session_start();
if (!isset($_SESSION['admin'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

ini_set('display_errors', 0);
header('Content-Type: application/json');

require_once __DIR__ . '/playlist_utils.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$playlist = isset($input['playlist']) ? basename($input['playlist']) : null;
$imdb = isset($input['imdb']) ? $input['imdb'] : null;

if (!$playlist || !$imdb) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing playlist or imdb']);
    exit;
}

$playlistPath = __DIR__ . '/../../playlists/' . $playlist;
if (!file_exists($playlistPath)) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Playlist not found']);
    exit;
}

// Load playlist JSON
$data = json_decode(file_get_contents($playlistPath), true);
if (!$data || !isset($data['shows']) || !is_array($data['shows'])) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Invalid playlist data']);
    exit;
}

$found = false;
$newStatus = null;
foreach ($data['shows'] as &$item) {
    if (isset($item['imdb']) && $item['imdb'] === $imdb) {
        if (!isset($item['status']) || $item['status'] === 'active') {
            $item['status'] = 'disabled';
        } else {
            $item['status'] = 'active';
        }
        $newStatus = $item['status'];
        $found = true;
        break;
    }
}
if (!$found) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Show not found']);
    exit;
}

// Update lastupdated timestamp
$data['lastupdated'] = gmdate('Y-m-d\TH:i:s.\0\0\0\Z');

// Save JSON
if (file_put_contents($playlistPath, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) === false) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to save playlist']);
    exit;
}

// Rebuild index.json
rebuild_index(__DIR__ . '/../../playlists');

echo json_encode(['success' => true, 'message' => 'Status updated', 'status' => $newStatus]);
