
<?php

session_start();
if (!isset($_SESSION['admin'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Endpoint for both "Add" and "Edit" show admin pages
require_once __DIR__ . '/../playlist_utils.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$playlist = isset($input['playlist']) ? basename($input['playlist']) : null;
$show = isset($input['show']) ? $input['show'] : null;

if (!$playlist || !$show || !isset($show['imdb'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing playlist or show data']);
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


$add = isset($input['add']) ? (bool)$input['add'] : false;
$found = false;
foreach ($data['shows'] as &$item) {
    if (isset($item['imdb']) && $item['imdb'] === $show['imdb']) {
        $item = $show;
        $found = true;
        break;
    }
}
if (!$found) {
    if ($add) {
        $data['shows'][] = $show;
    } else {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Show not found']);
        exit;
    }
}

// Update lastupdated timestamp
$data['lastupdated'] = gmdate('Y-m-d\TH:i:s.\0\0\0\Z');

// Save JSON
if (file_put_contents($playlistPath, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) === false) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to save playlist']);
    exit;
}

rebuild_index(__DIR__ . '/../../playlists');

echo json_encode(['success' => true, 'message' => 'Show updated']);
