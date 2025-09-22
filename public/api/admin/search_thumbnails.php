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

$input = file_get_contents('php://input');
$json = json_decode($input, true);
$query = isset($json['query']) ? trim($json['query']) : '';
$shows = isset($json['shows']) && is_array($json['shows']) ? $json['shows'] : [];
if ($query === '') {
    echo json_encode(['status' => 'success', 'results' => []]);
    exit;
}

$thumbs_dir = __DIR__ . '/../../thumbs/';
$files = [];
if (is_dir($thumbs_dir)) {
    foreach (scandir($thumbs_dir) as $file) {
        if (preg_match('/^tt\d+\.jpg$/', $file)) {
            $files[] = $file;
        }
    }
}
$file_imdbs = array_map(function ($f) {

    return preg_replace('/\.jpg$/', '', $f);
}, $files);
$file_imdb_set = array_flip($file_imdbs);
// Deduplicate shows by IMDB ID
$unique_shows = [];
foreach ($shows as $show) {
    if (!isset($show['imdb'])) {
        continue;
    }
    $imdb = $show['imdb'];
    if (!isset($unique_shows[$imdb])) {
        $unique_shows[$imdb] = $show;
    }
}

// Search for matches
$results = [];
$q = strtolower($query);
foreach ($unique_shows as $show) {
    $imdb = isset($show['imdb']) ? $show['imdb'] : '';
    $title = isset($show['title']) ? $show['title'] : '';
    if (
        ($imdb && strpos(strtolower($imdb), $q) !== false) ||
        ($title && strpos(strtolower($title), $q) !== false)
    ) {
        $has_thumb = isset($file_imdb_set[$imdb]);
        $results[] = [
            'imdb' => $imdb,
            'title' => $title,
            'has_thumbnail' => $has_thumb
        ];
    }
}

// Return results
http_response_code(200);
echo json_encode([
    'status' => 'success',
    'results' => $results
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
