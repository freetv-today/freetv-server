<?php

session_start();
if (!isset($_SESSION['admin'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}
header('Content-Type: application/json');
// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'failed', 'message' => 'Method not allowed'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$imdb = isset($input['imdb']) ? trim($input['imdb']) : '';
if ($imdb === '') {
    http_response_code(400);
    echo json_encode(['status' => 'failed', 'message' => 'IMDB ID not provided'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

$temp_path = __DIR__ . '/../../temp/' . $imdb . '.jpg';
$thumbs_path = __DIR__ . '/../../thumbs/' . $imdb . '.jpg';
if (!file_exists($temp_path)) {
    http_response_code(404);
    echo json_encode(['status' => 'failed', 'message' => 'Temp thumbnail not found'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

if (!is_dir(dirname($thumbs_path))) {
    mkdir(dirname($thumbs_path), 0777, true);
}

if (!rename($temp_path, $thumbs_path)) {
    http_response_code(500);
    echo json_encode(['status' => 'failed', 'message' => 'Failed to move thumbnail'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

// Success
http_response_code(200);
echo json_encode(['status' => 'success', 'message' => 'Thumbnail saved', 'thumb_url' => '/thumbs/' . $imdb . '.jpg'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
