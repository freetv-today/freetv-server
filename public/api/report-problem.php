<?php

header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 0);

$messageMethodNotAllowed = 'Method not allowed';
$messageTooManyRequests = 'You are submitting problem reports too quickly. Please wait awhile before attempting to report another show title.';
$messageSuccess = 'Thank you! Your problem report has been received.';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => $messageMethodNotAllowed]);
    exit;
}

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/admin/Database.php';

use FreeTV\Admin\Database;

$requestBody = file_get_contents('php://input');
$requestObject = json_decode($requestBody);
if (json_last_error() !== JSON_ERROR_NONE || !is_object($requestObject)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON request body']);
    exit;
}

$input = get_object_vars($requestObject);
$playlist = $input['playlist'] ?? null;
$identifier = $input['identifier'] ?? null;

if (
    !is_string($playlist)
    || trim($playlist) === ''
    || !is_string($identifier)
    || trim($identifier) === ''
) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing or invalid playlist or identifier']);
    exit;
}

if (
    !preg_match('/^[a-zA-Z0-9_-]+\.json$/', $playlist)
    || strcasecmp($playlist, 'index.json') === 0
) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid playlist filename']);
    exit;
}

$ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
if (!is_string($ipAddress) || $ipAddress === '' || strlen($ipAddress) > 45) {
    $ipAddress = 'unknown';
}

try {
    $capsule = Database::init();
    $connection = $capsule->getConnection();

    $playlistRow = Database::table('playlists')
        ->where('filename', $playlist)
        ->first(['id', 'filename']);

    if (!$playlistRow || $playlistRow->filename !== $playlist) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Playlist not found']);
        exit;
    }

    $showRow = Database::table('playlist_shows')
        ->where('playlist_id', $playlistRow->id)
        ->where('identifier', $identifier)
        ->first(['id', 'playlist_id', 'identifier', 'title', 'category', 'imdb']);

    if (!$showRow || $showRow->identifier !== $identifier) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Show not found']);
        exit;
    }

    $lockName = 'freetv:report-ip:' . substr(hash('sha256', $ipAddress), 0, 40);
    $lockAcquired = false;

    try {
        $lockResult = $connection->selectOne(
            'SELECT GET_LOCK(?, 10) AS acquired',
            [$lockName]
        );

        if (!$lockResult || (int) $lockResult->acquired !== 1) {
            throw new \RuntimeException('Could not acquire problem report IP lock');
        }
        $lockAcquired = true;

        $attemptCount = $connection->transaction(function () use (
            $connection,
            $ipAddress
        ) {
            $connection->table('problem_report_ips')
                ->where('ip_address', $ipAddress)
                ->whereRaw('attempted_at < CURRENT_TIMESTAMP - INTERVAL 5 MINUTE')
                ->delete();

            $connection->table('problem_report_ips')->insert([
                'ip_address' => $ipAddress,
                'attempted_at' => $connection->raw('CURRENT_TIMESTAMP'),
            ]);

            return $connection->table('problem_report_ips')
                ->where('ip_address', $ipAddress)
                ->whereRaw('attempted_at >= CURRENT_TIMESTAMP - INTERVAL 5 MINUTE')
                ->count();
        });
    } finally {
        if ($lockAcquired) {
            try {
                $connection->selectOne('SELECT RELEASE_LOCK(?) AS released', [$lockName]);
            } catch (\Throwable $releaseException) {
                error_log('Problem Report IP Lock Release Error: ' . $releaseException->getMessage());
            }
        }
    }

    if ($attemptCount > 2) {
        http_response_code(429);
        echo json_encode(['success' => false, 'message' => $messageTooManyRequests]);
        exit;
    }

    // The IP attempt commits independently by design. If this durable report
    // transaction fails, that attempt remains counted without being linked to
    // a report or identifier.
    $connection->transaction(function () use (
        $connection,
        $playlistRow,
        $showRow
    ) {
        $affectedRows = $connection->affectingStatement(
            <<<'SQL'
                INSERT INTO problem_reports (
                    playlist_id,
                    playlist_show_id,
                    identifier,
                    title,
                    category,
                    imdb,
                    status,
                    report_count,
                    first_reported_at,
                    last_reported_at
                )
                VALUES (?, ?, ?, ?, ?, ?, 'reported', 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
                ON DUPLICATE KEY UPDATE
                    playlist_show_id = VALUES(playlist_show_id),
                    title = VALUES(title),
                    category = VALUES(category),
                    imdb = VALUES(imdb),
                    status = 'reported',
                    report_count = report_count + 1,
                    last_reported_at = CURRENT_TIMESTAMP
                SQL,
            [
                $playlistRow->id,
                $showRow->id,
                $showRow->identifier,
                $showRow->title,
                $showRow->category,
                $showRow->imdb,
            ]
        );

        if ($affectedRows < 1) {
            throw new \RuntimeException('Problem report upsert did not affect a row');
        }
    });

    echo json_encode(['success' => true, 'message' => $messageSuccess]);
} catch (\Throwable $e) {
    error_log('Report Problem API Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
