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

use FreeTV\Admin\Database;

$requestBody = file_get_contents('php://input');
$requestObject = json_decode($requestBody);
if (json_last_error() !== JSON_ERROR_NONE || !is_object($requestObject)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON request body']);
    exit;
}

if (
    !property_exists($requestObject, 'playlist')
    || !is_string($requestObject->playlist)
    || trim($requestObject->playlist) === ''
    || !property_exists($requestObject, 'meta')
    || !is_object($requestObject->meta)
) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing or invalid playlist or meta data']);
    exit;
}

$playlist = trim($requestObject->playlist);
if (
    basename($playlist) !== $playlist
    || !preg_match('/^[a-zA-Z0-9_-]+\.json$/', $playlist)
    || strcasecmp($playlist, 'index.json') === 0
) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid playlist filename']);
    exit;
}

$meta = get_object_vars($requestObject->meta);
$allowedFields = [
    'dbtitle' => 255,
    'dbversion' => 50,
    'author' => 255,
    'email' => 255,
    'link' => 255,
];
$nullableFields = ['dbversion', 'author', 'email', 'link'];

foreach ($meta as $field => $value) {
    if (!array_key_exists($field, $allowedFields)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => "Invalid metadata field: {$field}"]);
        exit;
    }

    if (!is_string($value)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => "Invalid metadata value: {$field}"]);
        exit;
    }

    $valueLength = function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
    if ($valueLength > $allowedFields[$field]) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => "Metadata value is too long: {$field}"]);
        exit;
    }
}

try {
    $capsule = Database::init();
    $connection = $capsule->getConnection();

    $playlistRow = Database::table('playlists')
        ->where('filename', $playlist)
        ->first([
            'id',
            'filename',
            'dbtitle',
            'dbversion',
            'author',
            'email',
            'link',
        ]);

    if (!$playlistRow || $playlistRow->filename !== $playlist) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Playlist not found']);
        exit;
    }

    $changedValues = [];
    foreach ($meta as $field => $value) {
        $normalizedValue = in_array($field, $nullableFields, true) && $value === ''
            ? null
            : $value;

        if ($playlistRow->{$field} !== $normalizedValue) {
            $changedValues[$field] = $normalizedValue;
        }
    }

    if ($changedValues === []) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'No values were changed.']);
        exit;
    }

    $changedValues['lastupdated'] = $connection->raw('CURRENT_TIMESTAMP');

    $updatedRows = Database::table('playlists')
        ->where('id', $playlistRow->id)
        ->update($changedValues);

    if ($updatedRows !== 1) {
        throw new \RuntimeException('Playlist metadata update did not affect exactly one row');
    }

    echo json_encode(['success' => true, 'message' => 'Meta data updated']);
} catch (\Throwable $e) {
    error_log('Update Playlist Metadata API Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
