<?php

require_once __DIR__ . '/Authorization.php';
\FreeTV\Admin\requireRole('editor');

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/Database.php';

use FreeTV\Admin\Database;

$data = json_decode(file_get_contents('php://input'), true);
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON request body']);
    exit;
}

$stringFields = ['dbtitle', 'dbversion', 'filename', 'author', 'email', 'link'];
foreach ($stringFields as $field) {
    if (array_key_exists($field, $data) && !is_string($data[$field])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => "Invalid field: {$field}"]);
        exit;
    }
}

$title = trim($data['dbtitle'] ?? '');
$filename = trim($data['filename'] ?? '');
$author = trim($data['author'] ?? '');
$email = trim($data['email'] ?? '');
$version = trim($data['dbversion'] ?? '');
$link = trim($data['link'] ?? '');

if ($title === '' || $filename === '' || $author === '' || $email === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

$base = preg_replace('/\.json$/i', '', $filename);
if (!preg_match('/^[a-zA-Z0-9_-]+$/', $base)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid file name. Only letters, numbers, dashes, and underscores are allowed.'
    ]);
    exit;
}

if (strtolower($base) === 'index') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'File name index.json is not allowed.']);
    exit;
}

$finalFilename = $base . '.json';
$playlistValues = [
    'filename' => $finalFilename,
    'dbtitle' => $title,
    'dbversion' => $version === '' ? null : $version,
    'author' => $author,
    'email' => $email,
    'link' => $link === '' ? null : $link,
    'is_default' => 0,
];

function isDuplicatePlaylistFilenameException(\Throwable $e): bool
{
    if (!$e instanceof \Illuminate\Database\QueryException) {
        return false;
    }

    $driverErrorCode = isset($e->errorInfo[1]) ? (int) $e->errorInfo[1] : null;

    return $driverErrorCode === 1062
        && strpos($e->getMessage(), 'uq_playlists_filename') !== false;
}

try {
    $capsule = Database::init();
    $connection = $capsule->getConnection();
    $lockName = 'freetv_add_playlist_order';
    $lockAcquired = false;

    try {
        $lockResult = $connection->selectOne(
            'SELECT GET_LOCK(?, 10) AS acquired',
            [$lockName]
        );

        if (!$lockResult || (int) $lockResult->acquired !== 1) {
            throw new \RuntimeException('Could not acquire Add Playlist ordering lock');
        }
        $lockAcquired = true;

        $insertResult = $connection->transaction(function () use (
            $connection,
            $playlistValues
        ) {
            $duplicateExists = Database::table('playlists')
                ->where('filename', $playlistValues['filename'])
                ->exists();

            if ($duplicateExists) {
                return 'duplicate';
            }

            $maxSortOrder = Database::table('playlists')->max('sort_order');
            $sortOrder = $maxSortOrder === null ? 0 : (int) $maxSortOrder + 1;

            Database::table('playlists')->insert(array_merge($playlistValues, [
                'lastupdated' => $connection->raw('CURRENT_TIMESTAMP'),
                'sort_order' => $sortOrder,
            ]));

            return 'inserted';
        });
    } finally {
        if ($lockAcquired) {
            try {
                $connection->selectOne('SELECT RELEASE_LOCK(?) AS released', [$lockName]);
            } catch (\Throwable $releaseException) {
                error_log('Add Playlist Lock Release Error: ' . $releaseException->getMessage());
            }
        }
    }

    if ($insertResult === 'duplicate') {
        http_response_code(409);
        echo json_encode([
            'success' => false,
            'message' => 'A playlist with this file name already exists'
        ]);
        exit;
    }

    echo json_encode([
        'success' => true,
        'filename' => $finalFilename,
    ]);
} catch (\Throwable $e) {
    if (isDuplicatePlaylistFilenameException($e)) {
        http_response_code(409);
        echo json_encode([
            'success' => false,
            'message' => 'A playlist with this file name already exists'
        ]);
        exit;
    }

    error_log('Add Playlist API Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
