<?php

// add-playlist.php - Handles creation of new playlists

require_once __DIR__ . '/playlist_utils.php';
header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
if (!$data) {
    echo json_encode(['success' => false, 'message' => 'Invalid JSON']);
    exit;
}

// Required fields
$title = trim($data['dbtitle'] ?? '');
$filename = trim($data['filename'] ?? '');
$author = trim($data['author'] ?? '');
$email = trim($data['email'] ?? '');
$version = trim($data['dbversion'] ?? '');
$link = trim($data['link'] ?? '');
if (!$title || !$filename || !$author || !$email) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

// Validate filename: only letters, numbers, dashes, underscores, optional .json extension
$base = $filename;
if (preg_match('/\.(json)$/i', $filename)) {
    $base = preg_replace('/\.(json)$/i', '', $filename);
}

if (!preg_match('/^[a-zA-Z0-9_-]+$/', $base)) {
    echo json_encode(['success' => false, 'message' => 'Invalid file name. Only letters, numbers, dashes, and underscores are allowed.']);
    exit;
}

if (strtolower($base) === 'index') {
    echo json_encode(['success' => false, 'message' => 'File name index.json is not allowed.']);
    exit;
}

$finalFilename = $base . '.json';
$playlistsDir = realpath(__DIR__ . '/../../playlists');
if (!$playlistsDir) {
    echo json_encode(['success' => false, 'message' => 'Playlists directory not found.']);
    exit;
}
$targetFile = $playlistsDir . DIRECTORY_SEPARATOR . $finalFilename;
if (file_exists($targetFile)) {
    echo json_encode(['success' => false, 'message' => 'File name already exists! Please choose a different name']);
    exit;
}

// Build playlist data
$playlist = [
    'lastupdated' => gmdate('Y-m-d\TH:i:s.\0\0\0\Z'),
    'dbtitle' => $title,
    'filename' => $finalFilename,
    'dbversion' => $version,
    'author' => $author,
    'email' => $email,
    'link' => $link,
    'shows' => []
];
$json = json_encode($playlist, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
if (file_put_contents($targetFile, $json) === false) {
    echo json_encode(['success' => false, 'message' => 'Failed to write playlist file.']);
    exit;
}

// Rebuild index
rebuild_index($playlistsDir);
echo json_encode(['success' => true, 'filename' => $finalFilename]);
