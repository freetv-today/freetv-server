<?php

require_once __DIR__ . '/Authorization.php';
\FreeTV\Admin\requireRole('viewer');

header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/Database.php';

use FreeTV\Admin\Database;

try {
    Database::init();

    $active = Database::table('playlist_shows')->where('status', 'active')->count();
    $disabled = Database::table('playlist_shows')->where('status', 'disabled')->count();

    echo json_encode([
        'success' => true,
        'active' => (int) $active,
        'disabled' => (int) $disabled,
    ]);
} catch (\Throwable $e) {
    error_log('Database Show Count API Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
