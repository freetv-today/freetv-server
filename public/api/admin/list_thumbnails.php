<?php

header('Content-Type: application/json');
$thumbs_dir = __DIR__ . '/../../thumbs/'; // located in /public/thumbs/
$playlist_data = null;
$shows = [];
$with_thumbnails = [];
$missing_thumbnails = [];
// Get showData from localStorage via POST (since PHP can't access browser localStorage)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = file_get_contents('php://input');
    $json = json_decode($input, true);
    if (isset($json['shows']) && is_array($json['shows'])) {
        $shows = $json['shows'];
    }
}


$files = [];
if (is_dir($thumbs_dir)) {
    foreach (scandir($thumbs_dir) as $file) {
        if (preg_match('/^tt\d+\.jpg$/', $file)) {
            $files[] = $file;
        }
    }
}

// Build sets for comparison
$file_imdbs = array_map(function ($f) {
    return preg_replace('/\.jpg$/', '', $f);
}, $files);
$file_imdb_set = array_flip($file_imdbs);

// Deduplicate IMDB IDs in $shows
$unique_imdbs = [];
foreach ($shows as $show) {
    if (!isset($show['imdb'])) {
        continue;
    }
    $imdb = $show['imdb'];
    if (!isset($unique_imdbs[$imdb])) {
        $unique_imdbs[$imdb] = true;
    }
}

foreach (array_keys($unique_imdbs) as $imdb) {
    if (isset($file_imdb_set[$imdb])) {
        $with_thumbnails[] = $imdb;
    } else {
        $missing_thumbnails[] = $imdb;
    }
}

echo json_encode([
    'thumbnails' => $files,
    'with_thumbnails' => $with_thumbnails,
    'missing_thumbnails' => $missing_thumbnails
]);
