<?php

session_start();
if (!isset($_SESSION['admin'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Proxy endpoint for serving playlist files with no-cache headers
// This ensures admin dashboard always gets fresh data

header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Get the requested file parameter
$filename = isset($_GET['file']) ? basename($_GET['file']) : null;

if (!$filename) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing file parameter']);
    exit;
}

// Construct the full path to the playlist file
$filePath = __DIR__ . '/../../playlists/' . $filename;

// Check if file exists
if (!file_exists($filePath)) {
    http_response_code(404);
    echo json_encode(['error' => 'Playlist file not found']);
    exit;
}

// Check if it's a valid JSON file
if (pathinfo($filename, PATHINFO_EXTENSION) !== 'json') {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid file type']);
    exit;
}

// Serve the file content
$content = file_get_contents($filePath);
if ($content === false) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to read playlist file']);
    exit;
}

// Output the JSON content
echo $content;
