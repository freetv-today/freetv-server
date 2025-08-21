<?php
// CLI wrapper for rebuilding index.json from playlist files
require_once __DIR__ . '/../api/admin/playlist_utils.php';

$ok = rebuild_index(__DIR__);
if ($ok) {
    echo "index.json has been updated successfully.\n";
} else {
    echo "Failed to update index.json.\n";
}