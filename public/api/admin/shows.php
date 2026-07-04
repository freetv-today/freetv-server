<?php
require_once __DIR__ . '/../../../vendor/autoload.php';
require_once 'config.php';
require_once __DIR__ . '/Database.php';

header('Content-Type: application/json');

$playlistId = $_GET['playlist_id'] ?? null;
$category = $_GET['category'] ?? null;
$status = $_GET['status'] ?? null;

use FreeTV\Admin\Database;

try {
    Database::init();

    $query = Database::table('playlist_shows');

    if ($playlistId) {
        $query->where('playlist_id', $playlistId);
    }
    if ($category) {
        $query->where('category', $category);
    }
    if ($status) {
        $query->where('status', $status);
    }

    $shows = $query->orderBy('sort_order')
                   ->orderBy('title')
                   ->get();

    echo json_encode([
        'success' => true,
        'shows' => $shows,
        'count' => count($shows)
    ]);
} catch (\Throwable $e) {
    error_log("Shows API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error'
    ]);
}