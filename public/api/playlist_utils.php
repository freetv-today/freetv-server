<?php

// playlist_utils.php - Shared playlist utility functions

// Prevent direct access via HTTP
if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'])) {
    http_response_code(403);
    header('Content-Type: text/plain');
    exit('Forbidden: Direct access is not allowed.');
}

function rebuild_index($playlists_dir = null)
{

    if (!$playlists_dir) {
        $playlists_dir = __DIR__ . '/../playlists';
    }
    $files = glob($playlists_dir . '/*.json');
    $playlists = [];
    foreach ($files as $file) {
        $filename = basename($file);
        if ($filename === 'index.json') {
            continue;
        }
        $content = file_get_contents($file);
        $data = json_decode($content, true);
        if ($data) {
            $playlists[] = [
                'filename' => $filename,
                'dbtitle' => $data['dbtitle'] ?? 'Unknown',
                'lastupdated' => $data['lastupdated'] ?? '',
                'author' => $data['author'] ?? 'Unknown'
            ];
        }
    }
    // Sort by filename ascending
    usort($playlists, function ($a, $b) {

        return strcmp($a['filename'], $b['filename']);
    });
    $index = [
        'default' => 'freetv.json',
        'playlists' => $playlists
    ];
    $json = json_encode($index, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    $written = file_put_contents($playlists_dir . '/index.json', $json);
    return $written !== false;
}

// If accessed via HTTP, run the rebuild and return JSON
if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'])) {
    if (php_sapi_name() !== 'cli') {
        header('Content-Type: application/json');
        $success = rebuild_index();
        echo json_encode(['success' => $success]);
    }
}
