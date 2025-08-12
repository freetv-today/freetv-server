<?php

$playlists_dir = '.';

$files = glob($playlists_dir . '/*.json');

$playlists = [];

foreach ($files as $file) {
    $filename = basename($file);
    if ($filename === 'index.json') continue;
    
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
usort($playlists, function($a, $b) {
    return strcmp($a['filename'], $b['filename']);
});

$index = [
    'default' => 'freetv.json',
    'playlists' => $playlists
];

$json = json_encode($index, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

$written = file_put_contents($playlists_dir . '/index.json', $json);

if ($written !== false) {
    echo "index.json has been updated successfully.\n";
} else {
    echo "Failed to update index.json.\n";
}