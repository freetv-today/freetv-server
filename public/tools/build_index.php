<?php

// Dev path for localhost
require_once __DIR__ . '/../api/playlist_utils.php';

// Live path for production
// require_once '/home/path/to/api/playlist_utils.php';

// Wrapper for rebuilding index.json after playlist files change.
// Most PHP and Javascript functions call playlist_utils.php directly

// Secret key for web access
$SECRET_KEY = '12345678'; // Change this to your own secret key
if (php_sapi_name() !== 'cli') {
    if (!isset($_GET['key']) || $_GET['key'] !== $SECRET_KEY) {
        http_response_code(403);
        exit('Forbidden: Invalid or missing key');
    }
}

$ok = rebuild_index();
if ($ok) {
    echo "Playlist index has been rebuilt successfully.\n";
} else {
    echo "Failed to update playlist index.\n";
}
