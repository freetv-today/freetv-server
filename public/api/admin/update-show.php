<?php

header('Content-Type: application/json');

session_start();
if (!isset($_SESSION['admin'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/Database.php';

use FreeTV\Admin\Database;

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON request body']);
    exit;
}

$playlist = $input['playlist'] ?? null;
$originalIdentifier = $input['originalIdentifier'] ?? null;
$show = $input['show'] ?? null;

if (
    (!is_string($playlist) && !is_int($playlist))
    || trim((string) $playlist) === ''
    || !is_string($originalIdentifier)
    || trim($originalIdentifier) === ''
    || !is_array($show)
) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Missing or invalid playlist, originalIdentifier, or show data'
    ]);
    exit;
}

$requiredShowFields = [
    'category',
    'status',
    'identifier',
    'title',
    'desc',
    'start',
    'end',
    'imdb',
];

foreach ($requiredShowFields as $field) {
    if (
        !array_key_exists($field, $show)
        || !is_string($show[$field])
        || trim($show[$field]) === ''
    ) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => "Missing or invalid show field: {$field}"
        ]);
        exit;
    }
}

if (!in_array($show['status'], ['active', 'disabled'], true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid show status']);
    exit;
}

if (
    array_key_exists('group', $show)
    && (!is_string($show['group']) || trim($show['group']) !== '')
) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Show groups are not supported by the database schema'
    ]);
    exit;
}

try {
    Database::init();

    $playlistQuery = Database::table('playlists');
    $playlistValue = trim((string) $playlist);

    if (ctype_digit($playlistValue) && (int) $playlistValue > 0) {
        $playlistRow = $playlistQuery->where('id', (int) $playlistValue)->first();
    } else {
        $playlistRow = $playlistQuery->where('filename', $playlistValue)->first();
    }

    if (!$playlistRow) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Playlist not found']);
        exit;
    }

    $showQuery = Database::table('playlist_shows')
        ->where('playlist_id', $playlistRow->id)
        ->where('identifier', $originalIdentifier);

    if (!$showQuery->first()) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Show not found']);
        exit;
    }

    $showQuery->update([
        'category' => (string) $show['category'],
        'status' => (string) $show['status'],
        'identifier' => (string) $show['identifier'],
        'title' => (string) $show['title'],
        'description' => (string) $show['desc'],
        'start_year' => (string) $show['start'],
        'end_year' => (string) $show['end'],
        'imdb' => (string) $show['imdb'],
    ]);

    echo json_encode(['success' => true, 'message' => 'Show updated']);
} catch (\Throwable $e) {
    error_log('Update Show API Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
