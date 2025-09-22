<?php

require_once __DIR__ . '/playlist_utils.php';

session_start();
if (!isset($_SESSION['admin'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// delete-reported-problem.php
// Clone of delete-show.php, will be customized to also remove from errors.json

header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$playlist = $input['playlist'] ?? null;
$identifier = $input['identifier'] ?? null;
if (!$playlist || !$identifier) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing playlist or identifier']);
    exit;
}

// --- Begin: delete from playlist (same as delete-show.php) ---
$playlistPath = __DIR__ . '/../../playlists/' . basename($playlist);
if (!file_exists($playlistPath)) {
    http_response_code(404);
    echo json_encode(['error' => 'Playlist not found']);
    exit;
}
$playlistData = json_decode(file_get_contents($playlistPath), true);
if (!is_array($playlistData) || !isset($playlistData['shows'])) {
    http_response_code(500);
    echo json_encode(['error' => 'Invalid playlist data']);
    exit;
}
$shows = $playlistData['shows'];
$showsBefore = count($shows);
$shows = array_values(array_filter($shows, function ($show) use ($identifier) {
    // Remove by identifier (should be unique)
    return !isset($show['identifier']) || $show['identifier'] !== $identifier;
}));
$playlistData['shows'] = $shows;
$playlistData['lastupdated'] = gmdate('Y-m-d\TH:i:s.000\Z');
file_put_contents($playlistPath, json_encode($playlistData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
// Re-index playlists index.json
require_once __DIR__ . '/playlist_utils.php';
rebuild_index();
// --- End: delete from playlist ---

// --- Begin: delete from errors.json ---
$errorsPath = __DIR__ . '/../../logs/errors.json';
if (file_exists($errorsPath)) {
    $errorsData = json_decode(file_get_contents($errorsPath), true);
    if (isset($errorsData['reports']) && is_array($errorsData['reports'])) {
        $before = count($errorsData['reports']);
        $errorsData['reports'] = array_values(array_filter($errorsData['reports'], function ($report) use ($playlist, $identifier) {

            // Remove if playlist and identifier match
            return !(
                isset($report['playlist'], $report['identifier']) &&
                $report['playlist'] === $playlist &&
                $report['identifier'] === $identifier
            );
        }));
        $after = count($errorsData['reports']);
        if ($after !== $before) {
            file_put_contents($errorsPath, json_encode($errorsData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }
    }
}
// --- End: delete from errors.json ---

echo json_encode(['success' => true, 'removedFromPlaylist' => $showsBefore - count($shows)]);
