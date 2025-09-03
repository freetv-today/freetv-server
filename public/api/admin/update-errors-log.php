<?php

// update-errors-log.php: Updates errors.json with new data (admin only)
// POST body: full errors.json object

$logFile = $_SERVER['DOCUMENT_ROOT'] . '/logs/errors.json';
$input = json_decode(file_get_contents('php://input'), true);
if (!$input || !isset($input['reports'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid data']);
    exit;
}
if (file_put_contents($logFile, json_encode($input, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) === false) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Could not write to log file.']);
    exit;
}
echo json_encode(['success' => true]);
