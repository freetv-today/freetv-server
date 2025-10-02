<?php

session_start();
if (!isset($_SESSION['admin'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
require_once __DIR__ . '/../playlist_utils.php';
$playlist = isset($input['filename'])
    ? basename(trim($input['filename'], '"'))
    : (isset($input['playlist'])
        ? basename(trim($input['playlist'], '"'))
        : null);
if (!$playlist) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing playlist filename']);
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

// Make a copy of the original shows array for comparison
$originalShows = $data['shows'];

// Sort shows by category, then by title within each category
usort($data['shows'], function ($a, $b) {
    $catCmp = strcmp(strtolower($a['category'] ?? ''), strtolower($b['category'] ?? ''));
    if ($catCmp !== 0) {
        return $catCmp;
    }
    return strcmp(strtolower($a['title'] ?? ''), strtolower($b['title'] ?? ''));
});

// Check if the sorted array is different from the original
if ($data['shows'] === $originalShows) {
    echo json_encode(['success' => true, 'message' => 'The data is already sorted. Nothing was changed.']);
    exit;
}

// Update lastupdated timestamp
$data['lastupdated'] = gmdate('Y-m-d\TH:i:s.000\Z');
// Save JSON
if (file_put_contents($playlistPath, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) === false) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to save playlist']);
    exit;
}

// Rebuild index.json after sorting
rebuild_index(__DIR__ . '/../../playlists');

echo json_encode(['success' => true, 'message' => 'Playlist sorted by category']);
