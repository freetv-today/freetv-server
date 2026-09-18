<?php

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/Authorization.php';
\FreeTV\Admin\requireRole('editor');

ini_set('display_errors', 0);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/Database.php';

use FreeTV\Admin\Database;

$requestObject = json_decode(file_get_contents('php://input'));
if (json_last_error() !== JSON_ERROR_NONE || !is_object($requestObject)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON request body']);
    exit;
}

$input = get_object_vars($requestObject);
$playlist = $input['playlist'] ?? null;
$reportIdValue = $input['reportId'] ?? null;
$reportIdInputIsInteger = is_int($reportIdValue)
    || (is_string($reportIdValue) && preg_match('/^[1-9][0-9]*$/', $reportIdValue));
$reportId = $reportIdInputIsInteger
    ? filter_var($reportIdValue, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
    : false;

if (!is_string($playlist) || trim($playlist) === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing or invalid playlist']);
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

if ($reportId === false) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid report ID']);
    exit;
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

    $result = $connection->transaction(function () use ($playlistRow, $reportId) {
        $report = Database::table('problem_reports')
            ->where('id', $reportId)
            ->where('playlist_id', $playlistRow->id)
            ->lockForUpdate()
            ->first(['id', 'status', 'report_count']);

        if (!$report) {
            return null;
        }

        if ($report->status === 'addressed') {
            return ['alreadyAddressed' => true, 'reportCount' => (int) $report->report_count];
        }

        if ($report->status !== 'reported') {
            throw new \RuntimeException('Unexpected problem report status');
        }

        $updatedRows = Database::table('problem_reports')
            ->where('id', $report->id)
            ->where('playlist_id', $playlistRow->id)
            ->where('status', 'reported')
            ->update(['status' => 'addressed']);

        if ($updatedRows !== 1) {
            throw new \RuntimeException('Address update did not affect exactly one report');
        }

        return ['alreadyAddressed' => false, 'reportCount' => (int) $report->report_count];
    });

    if ($result === null) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Report not found']);
        exit;
    }

    echo json_encode([
        'success' => true,
        'message' => $result['alreadyAddressed'] ? 'Problem was already addressed' : 'Problem marked as OK',
        'reportId' => (int) $reportId,
        'status' => 'addressed',
        'reportCount' => $result['reportCount'],
        'alreadyAddressed' => $result['alreadyAddressed'],
    ]);
} catch (\Throwable $e) {
    error_log('Mark Problem Addressed API Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
