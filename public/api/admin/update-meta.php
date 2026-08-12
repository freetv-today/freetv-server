<?php

header('Content-Type: application/json');

require_once __DIR__ . '/Authorization.php';
\FreeTV\Admin\requireRole('editor');

function respond(int $status, array $payload): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    respond(405, ['success' => false, 'message' => 'Method not allowed']);
}

require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/Database.php';

use FreeTV\Admin\Database;

$requestObject = json_decode(file_get_contents('php://input'));
if (json_last_error() !== JSON_ERROR_NONE || !is_object($requestObject)) {
    respond(400, ['success' => false, 'message' => 'Invalid JSON request body']);
}

if (
    !property_exists($requestObject, 'playlist')
    || !is_string($requestObject->playlist)
    || trim($requestObject->playlist) === ''
    || !property_exists($requestObject, 'meta')
    || !is_object($requestObject->meta)
) {
    respond(400, ['success' => false, 'message' => 'Missing or invalid playlist or meta data']);
}

$playlist = trim($requestObject->playlist);
if (
    basename($playlist) !== $playlist
    || !preg_match('/^[a-zA-Z0-9_-]+\.json$/', $playlist)
    || strcasecmp($playlist, 'index.json') === 0
) {
    respond(400, ['success' => false, 'message' => 'Invalid playlist filename']);
}

$meta = get_object_vars($requestObject->meta);
$makeDefault = false;
if (array_key_exists('is_default', $meta)) {
    if (!is_bool($meta['is_default'])) {
        respond(400, ['success' => false, 'message' => 'is_default must be a boolean']);
    }
    if ($meta['is_default'] !== true) {
        respond(400, ['success' => false, 'message' => 'The default playlist cannot be cleared directly']);
    }
    $makeDefault = true;
    unset($meta['is_default']);
}

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
        respond(400, ['success' => false, 'message' => "Invalid metadata field: {$field}"]);
    }
    if (!is_string($value)) {
        respond(400, ['success' => false, 'message' => "Invalid metadata value: {$field}"]);
    }

    $valueLength = function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
    if ($valueLength > $allowedFields[$field]) {
        respond(400, ['success' => false, 'message' => "Metadata value is too long: {$field}"]);
    }
}

try {
    $capsule = Database::init();
    $connection = $capsule->getConnection();

    $result = $connection->transaction(function () use (
        $connection,
        $playlist,
        $meta,
        $nullableFields,
        $makeDefault
    ): string {
        $playlistRows = Database::table('playlists')
            ->orderBy('id')
            ->lockForUpdate()
            ->get([
                'id',
                'filename',
                'dbtitle',
                'dbversion',
                'author',
                'email',
                'link',
                'is_default',
            ]);

        $playlistRow = $playlistRows->first(
            fn($row) => $row->filename === $playlist
        );
        if (!$playlistRow) {
            return 'not_found';
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

        $defaultChanged = $makeDefault && !(bool) $playlistRow->is_default;
        if ($changedValues === [] && !$defaultChanged) {
            return 'unchanged';
        }

        if ($makeDefault) {
            Database::table('playlists')
                ->where('id', '<>', $playlistRow->id)
                ->where('is_default', 1)
                ->update(['is_default' => 0]);
            $changedValues['is_default'] = 1;
        }

        $changedValues['lastupdated'] = $connection->raw('CURRENT_TIMESTAMP');
        $updatedRows = Database::table('playlists')
            ->where('id', $playlistRow->id)
            ->update($changedValues);

        if ($updatedRows !== 1) {
            throw new \RuntimeException('Playlist metadata update did not affect exactly one row');
        }

        return 'updated';
    });

    if ($result === 'not_found') {
        respond(404, ['success' => false, 'message' => 'Playlist not found']);
    }
    if ($result === 'unchanged') {
        respond(400, ['success' => false, 'message' => 'No values were changed.']);
    }

    respond(200, ['success' => true, 'message' => 'Meta data updated']);
} catch (\Throwable $e) {
    error_log('Update Playlist Metadata API Error: ' . $e->getMessage());
    respond(500, ['success' => false, 'message' => 'Database error']);
}
