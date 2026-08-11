<?php

header('Content-Type: application/json');

require_once __DIR__ . '/Authorization.php';
\FreeTV\Admin\requireRole('editor');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/ThumbnailService.php';

use FreeTV\Admin\Database;
use FreeTV\Admin\ThumbnailService;

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON request body']);
    exit;
}

$playlist = $input['playlist'] ?? null;
$originalIdentifier = $input['originalIdentifier'] ?? null;
$show = $input['show'] ?? null;
$add = $input['add'] ?? false;

if (array_key_exists('add', $input) && !is_bool($input['add'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid add flag']);
    exit;
}

if (
    (!is_string($playlist) && !is_int($playlist))
    || trim((string) $playlist) === ''
    || !is_array($show)
) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Missing or invalid playlist or show data'
    ]);
    exit;
}

if (
    !$add
    && (!is_string($originalIdentifier) || trim($originalIdentifier) === '')
) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Missing or invalid originalIdentifier'
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

if (!ThumbnailService::isValidImdb($show['imdb'])) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Must be a valid IMDb ID such as tt0052520'
    ]);
    exit;
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

function isDuplicateIdentifierException(\Throwable $e): bool
{
    if (!$e instanceof \Illuminate\Database\QueryException) {
        return false;
    }

    $driverErrorCode = isset($e->errorInfo[1]) ? (int) $e->errorInfo[1] : null;

    return $driverErrorCode === 1062
        && strpos($e->getMessage(), 'uq_playlist_shows_playlist_identifier') !== false;
}

try {
    $capsule = Database::init();
    $connection = $capsule->getConnection();

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

    $showValues = [
        'category' => (string) $show['category'],
        'status' => (string) $show['status'],
        'identifier' => (string) $show['identifier'],
        'title' => (string) $show['title'],
        'description' => (string) $show['desc'],
        'start_year' => (string) $show['start'],
        'end_year' => (string) $show['end'],
        'imdb' => (string) $show['imdb'],
    ];
    $databaseCurrentTimestamp = $connection->raw('CURRENT_TIMESTAMP');

    if ($add) {
        $addResult = $connection->transaction(function () use (
            $playlistRow,
            $showValues,
            $databaseCurrentTimestamp
        ) {
            $lockedPlaylist = Database::table('playlists')
                ->where('id', $playlistRow->id)
                ->lockForUpdate()
                ->first(['id']);

            if (!$lockedPlaylist) {
                return 'playlist_not_found';
            }

            $duplicateExists = Database::table('playlist_shows')
                ->where('playlist_id', $playlistRow->id)
                ->where('identifier', $showValues['identifier'])
                ->exists();

            if ($duplicateExists) {
                return 'duplicate';
            }

            $maxSortOrder = Database::table('playlist_shows')
                ->where('playlist_id', $playlistRow->id)
                ->max('sort_order');
            $sortOrder = $maxSortOrder === null ? 0 : (int) $maxSortOrder + 1;

            Database::table('playlist_shows')->insert(array_merge($showValues, [
                'playlist_id' => $playlistRow->id,
                'sort_order' => $sortOrder,
            ]));

            Database::table('playlists')
                ->where('id', $playlistRow->id)
                ->update(['lastupdated' => $databaseCurrentTimestamp]);

            return 'added';
        });

        if ($addResult === 'playlist_not_found') {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Playlist not found']);
            exit;
        }

        if ($addResult === 'duplicate') {
            http_response_code(409);
            echo json_encode([
                'success' => false,
                'message' => 'A show with this identifier already exists in the selected playlist'
            ]);
            exit;
        }

        echo json_encode(['success' => true, 'message' => 'Show added']);
        exit;
    }

    $showUpdated = $connection->transaction(function () use (
        $playlistRow,
        $originalIdentifier,
        $showValues,
        $databaseCurrentTimestamp
    ) {
        $existingShow = Database::table('playlist_shows')
            ->where('playlist_id', $playlistRow->id)
            ->where('identifier', $originalIdentifier)
            ->lockForUpdate()
            ->first(['id']);

        if (!$existingShow) {
            return false;
        }

        Database::table('playlist_shows')
            ->where('id', $existingShow->id)
            ->where('playlist_id', $playlistRow->id)
            ->update($showValues);

        Database::table('playlists')
            ->where('id', $playlistRow->id)
            ->update(['lastupdated' => $databaseCurrentTimestamp]);

        return true;
    });

    if (!$showUpdated) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Show not found']);
        exit;
    }

    echo json_encode(['success' => true, 'message' => 'Show updated']);
} catch (\Throwable $e) {
    if ($add && isDuplicateIdentifierException($e)) {
        http_response_code(409);
        echo json_encode([
            'success' => false,
            'message' => 'A show with this identifier already exists in the selected playlist'
        ]);
        exit;
    }

    error_log('Update Show API Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
