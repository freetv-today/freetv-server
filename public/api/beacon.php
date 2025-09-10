<?php

// Beacon analytics endpoint
header('Content-Type: application/json');

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'POST only']);
    exit;
}

// Load config.json to check if appdata is enabled
$configFile = realpath(__DIR__ . '/../config.json');
if (!file_exists($configFile)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Missing config.json']);
    exit;
}
$config = json_decode(file_get_contents($configFile), true);
if (empty($config['appdata'])) {
    // Analytics disabled
    echo json_encode(['success' => false, 'error' => 'Analytics disabled']);
    exit;
}

// Get and validate input
$jsonData = file_get_contents('php://input');
$visitData = json_decode($jsonData, true);
if (!is_array($visitData)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid JSON']);
    exit;
}

// Prepare log file
$logDir = realpath(__DIR__ . '/../logs');
if (!$logDir) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Missing logs directory']);
    exit;
}
$logFile = $logDir . '/appdata.json';
if (!file_exists($logFile)) {
    file_put_contents($logFile, "[]");
}

// Append session data (token, start, end, lastVisit, version, etc.)
$entry = [
    'token' => $visitData['token'] ?? null,
    'start' => $visitData['start'] ?? null,
    'end' => $visitData['end'] ?? null,
    'lastVisit' => $visitData['lastVisit'] ?? null,
];

// If recentShows is present and is an array, include it
if (isset($visitData['recentShows']) && is_array($visitData['recentShows']) && count($visitData['recentShows']) > 0) {
    $entry['recentShows'] = $visitData['recentShows'];
}

// Read, append, and write log file with file lock
$success = false;
for ($i = 0; $i < 3; $i++) { // retry up to 3 times
    $fp = fopen($logFile, 'c+');
    if ($fp && flock($fp, LOCK_EX)) {
        $log = stream_get_contents($fp);
        $data = $log ? json_decode($log, true) : [];
        if (!is_array($data)) {
            $data = [];
        }
        $data[] = $entry;
        ftruncate($fp, 0);
        rewind($fp);
        fwrite($fp, json_encode($data, JSON_PRETTY_PRINT));
        fflush($fp);
        flock($fp, LOCK_UN);
        fclose($fp);
        $success = true;
        break;
    } elseif ($fp) {
        fclose($fp);
    }
    usleep(100000); // wait 100ms before retry
}

if ($success) {
    echo json_encode(['success' => true]);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Could not write log']);
}
