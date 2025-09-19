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

// Prepare activity log directory
$logsDir = realpath(__DIR__ . '/../logs');
if (!$logsDir) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Missing logs directory']);
    exit;
}
$activityDir = $logsDir . '/activity';
if (!is_dir($activityDir)) {
    if (!mkdir($activityDir, 0777, true)) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Could not create activity directory']);
        exit;
    }
}

// Require a valid token
$token = $visitData['token'] ?? null;
if (!$token) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing token']);
    exit;
}

$logFile = $activityDir . '/' . preg_replace('/[^A-Z0-9\-]/i', '', $token) . '.json';

// Write the latest visitData as a single JSON object
$success = false;
for ($i = 0; $i < 3; $i++) { // retry up to 3 times
    $fp = fopen($logFile, 'c+');
    if ($fp && flock($fp, LOCK_EX)) {
        // Optionally, merge with existing data if needed
        $existing = stream_get_contents($fp);
        $existingData = $existing ? json_decode($existing, true) : [];
        // Merge: for now, just overwrite with latest visitData
        ftruncate($fp, 0);
        rewind($fp);
        fwrite($fp, json_encode($visitData, JSON_PRETTY_PRINT));
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
