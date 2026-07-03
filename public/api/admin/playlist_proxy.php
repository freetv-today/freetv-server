<?php

session_start();
if (!isset($_SESSION['admin'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

require_once 'config.php';
use FreeTV\Admin\AdminConfig;

$filename = isset($_GET['file']) ? basename($_GET['file']) : null;
if (!$filename) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing file parameter']);
    exit;
}

if ($filename === 'index.json') {
    try {
        $config = AdminConfig::getInstance();
        $pdo = $config->getPdo();
        $stmt = $pdo->query('SELECT filename, dbtitle, lastupdated, author, email, link, is_default FROM playlists ORDER BY sort_order, id');
        $playlists = $stmt->fetchAll();
        $default = null;
        foreach ($playlists as $playlist) {
            if (!empty($playlist['is_default'])) {
                $default = $playlist['filename'];
                break;
            }
        }
        if ($default === null && isset($playlists[0]['filename'])) {
            $default = $playlists[0]['filename'];
        }
        echo json_encode(['default' => $default, 'playlists' => array_map(function ($playlist) {
            return [
                'filename' => $playlist['filename'],
                'dbtitle' => $playlist['dbtitle'],
                'lastupdated' => $playlist['lastupdated'],
                'author' => $playlist['author'],
                'email' => $playlist['email'],
                'link' => $playlist['link'],
            ];
        }, $playlists)]);
        exit;
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to load playlists from database']);
        exit;
    }
}

if (pathinfo($filename, PATHINFO_EXTENSION) !== 'json') {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid file type']);
    exit;
}

try {
    $config = AdminConfig::getInstance();
    $pdo = $config->getPdo();
    $stmt = $pdo->prepare('SELECT id, filename, dbtitle, dbversion, author, email, link, lastupdated FROM playlists WHERE filename = :filename LIMIT 1');
    $stmt->execute([':filename' => $filename]);
    $playlist = $stmt->fetch();

    if (!$playlist) {
        http_response_code(404);
        echo json_encode(['error' => 'Playlist not found']);
        exit;
    }

    $showStmt = $pdo->prepare('SELECT category, status, identifier, title, description, start_year, end_year, imdb, sort_order FROM playlist_shows WHERE playlist_id = :playlist_id ORDER BY sort_order, id');
    $showStmt->execute([':playlist_id' => $playlist['id']]);
    $shows = $showStmt->fetchAll();

    echo json_encode([
        'filename' => $playlist['filename'],
        'dbtitle' => $playlist['dbtitle'],
        'dbversion' => $playlist['dbversion'],
        'author' => $playlist['author'],
        'email' => $playlist['email'],
        'link' => $playlist['link'],
        'lastupdated' => $playlist['lastupdated'],
        'shows' => array_map(function ($show) {
            return [
                'category' => $show['category'],
                'status' => $show['status'],
                'identifier' => $show['identifier'],
                'title' => $show['title'],
                'desc' => $show['description'],
                'start' => $show['start_year'],
                'end' => $show['end_year'],
                'imdb' => $show['imdb'],
            ];
        }, $shows),
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to load playlist from database']);
}

