<?php

// Report Problem
// Works with src/components/Navigation/ButtonShowTitleNav.jsx to
// allow users to report problems titles which need to be removed

// Centralized error/success messages
$MSG_METHOD_NOT_ALLOWED = 'Method not allowed';
$MSG_MISSING_FIELDS = 'Missing required fields';
$MSG_TOO_MANY_REQUESTS = 'You are submitting problem reports too quickly. Please wait awhile before attempting to report another show title.';
$MSG_ALREADY_REPORTED = 'You have already reported this title.';
$MSG_SUCCESS = 'Thank you! Your problem report has been received.';
$MSG_WRITE_ERROR = 'Could not write to log file.';
$MSG_API_ERROR = 'Could not connect to Internet Archive API server';

header('Content-Type: application/json; charset=utf-8');

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => $MSG_METHOD_NOT_ALLOWED]);
    exit;
}

// Get input JSON
$input = json_decode(file_get_contents('php://input'), true);
if (!$input || !isset($input['title'], $input['identifier'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $MSG_MISSING_FIELDS]);
    exit;
}

$title = $input['title'];
$category = $input['category'] ?? '';
$identifier = $input['identifier'];
$imdb = $input['imdb'] ?? '';
$playlist = $input['playlist'] ?? '';
$date = gmdate('Y-m-d\TH:i:s.v\Z');
$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$logDir = $_SERVER['DOCUMENT_ROOT'] . '/logs';
$logFile = $logDir . '/errors.json';
$ipLogFile = $logDir . '/report-ip-log.json';

// For playlist file path
$playlistFile = '';
if ($playlist && preg_match('/^[\w\-\.]+\.json$/', $playlist)) {
    $playlistFile = $_SERVER['DOCUMENT_ROOT'] . '/playlists/' . $playlist;
}

// Construct API URL for Internet Archive
$url = "https://archive.org/metadata/{$identifier}/is_dark";

// DEV USE: for testing to make API lookup fail
// $url = "https://this-domain-does-not-exist-123456789.com/metadata/{$identifier}/is_dark";

// Fetch API response from Internet Archive server
// (@ suppresses warnings if fails)

$archiveApiError = false;
$isDark = false;
$data = null;
$response = @file_get_contents($url);
if ($response === false) {
    // API error: log report with archiveApiError flag, but do not attempt to disable
    $archiveApiError = true;
} else {
    $data = json_decode($response, true);
    if (isset($data['result']) && $data['result'] === true) {
        $isDark = true;
    } elseif (isset($data['error']) && str_contains($data['error'], "Couldn't get 'is_dark'")) {
        $isDark = false; // file is not flagged as "is_dark"
    } elseif (isset($data['error'])) {
        // Other errors (e.g., item not found): treat as not dark, but log error
        $archiveApiError = true;
    }
}

// If title is flagged as "is_dark" from the Internet Archive, try to disable in playlist
if ($isDark && $playlistFile && file_exists($playlistFile)) {
    $playlistData = json_decode(file_get_contents($playlistFile), true);
    if (isset($playlistData['shows']) && is_array($playlistData['shows'])) {
        $found = false;
        foreach ($playlistData['shows'] as &$show) {
            if (isset($show['identifier']) && $show['identifier'] === $identifier) {
                $show['status'] = 'disabled';
                $found = true;
            }
        }
        unset($show);
        if ($found) {
            // Update lastupdated
            $playlistData['lastupdated'] = gmdate('Y-m-d\TH:i:s.v\Z');
            file_put_contents($playlistFile, json_encode($playlistData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            // Call rebuild_index
            require_once __DIR__ . '/playlist_utils.php';
            rebuild_index();
        }
    }
}

// Ensure log directory exists
if (!is_dir($logDir)) {
    mkdir($logDir, 0777, true);
}

// Load or initialize errors log
if (file_exists($logFile)) {
    $errors = json_decode(file_get_contents($logFile), true);
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($errors)) {
        $errors = ['reports' => []];
    } elseif (!isset($errors['reports']) || !is_array($errors['reports'])) {
        $errors = ['reports' => []];
    }
} else {
    $errors = ['reports' => []];
}

// Load or initialize IP log
if (file_exists($ipLogFile)) {
    $ipLog = json_decode(file_get_contents($ipLogFile), true);
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($ipLog)) {
        $ipLog = ['ips' => []];
    } elseif (!isset($ipLog['ips']) || !is_array($ipLog['ips'])) {
        $ipLog = ['ips' => []];
    }
} else {
    $ipLog = ['ips' => []];
}

// Check for future: IP blacklist (not implemented, but placeholder)
// if (isset($ipLog['blacklist']) && in_array($ip, $ipLog['blacklist'])) {
//     http_response_code(403);
//     echo json_encode(['success' => false, 'message' => 'You are not allowed to submit reports.']);
//     exit;
// }

// Always add this attempt to the IP log (even for duplicates)
$now = time();
$window = 300;
// 5 minutes
$maxReports = 2;
if (!isset($ipLog['ips'][$ip]) || !is_array($ipLog['ips'][$ip])) {
    $ipLog['ips'][$ip] = [];
}
// Remove timestamps older than 5 minutes
$ipLog['ips'][$ip] = array_filter($ipLog['ips'][$ip], function ($ts) use ($now, $window) {

    return (strtotime($ts) > ($now - $window));
});
$ipLog['ips'][$ip][] = $date;
// Save IP log immediately (in case of early exit)
file_put_contents($ipLogFile, json_encode($ipLog, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
// Check rate limit BEFORE logging to errors.json
if (count($ipLog['ips'][$ip]) > $maxReports) {
    http_response_code(429);
    echo json_encode([
        'success' => false,
        'message' => $MSG_TOO_MANY_REQUESTS
    ]);
    exit;
}

// 1. Check for duplicate report for this identifier from this IP
foreach ($errors['reports'] as &$report) {
    if (
        isset($report['identifier'], $report['status']) &&
        $report['identifier'] === $identifier &&
        $report['status'] === 'reported'
    ) {
    // If this IP has already reported this show, return error (but attempt is still counted)
        if (isset($report['reportingIps']) && in_array($ip, $report['reportingIps'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => $MSG_ALREADY_REPORTED]);
            exit;
        }
        // Not a duplicate, so add IP to reportingIps and increment reportCount
        if (!isset($report['reportingIps']) || !is_array($report['reportingIps'])) {
            $report['reportingIps'] = [];
        }
        $report['reportingIps'][] = $ip;
        $report['reportCount'] = isset($report['reportCount']) && is_numeric($report['reportCount'])
            ? $report['reportCount'] + 1
            : 2;
        $report['date'] = $date;
        if ($archiveApiError) {
            $report['archiveApiError'] = true;
        }
        if (file_put_contents($logFile, json_encode($errors, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) === false) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => $MSG_WRITE_ERROR]);
            exit;
        }
        // After logging, check rate limit
        if (count($ipLog['ips'][$ip]) > $maxReports) {
            http_response_code(429);
            echo json_encode([
            'success' => false,
            'message' => $MSG_TOO_MANY_REQUESTS
            ]);
            exit;
        }
        echo json_encode(['success' => true, 'message' => $MSG_SUCCESS]);
        exit;
    }
}
unset($report);
// 2. If not found, add new report
// Build new report entry
$reportEntry = [
    'playlist' => $playlist,
    'title' => $title,
    'category' => $category,
    'identifier' => $identifier,
    'imdb' => $imdb,
    'date' => $date,
    'reportingIps' => [$ip],
    'reportCount' => 1,
    'status' => 'reported'
];
if ($archiveApiError) {
    $reportEntry['archiveApiError'] = true;
}
$errors['reports'][] = $reportEntry;
if (file_put_contents($logFile, json_encode($errors, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) === false) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $MSG_WRITE_ERROR]);
    exit;
}
// After logging, check rate limit
if (count($ipLog['ips'][$ip]) > $maxReports) {
    http_response_code(429);
    echo json_encode([
        'success' => false,
        'message' => $MSG_TOO_MANY_REQUESTS
    ]);
    exit;
}
echo json_encode(['success' => true, 'message' => $MSG_SUCCESS]);
// Check for existing report for this identifier (status 'reported')
$found = false;
foreach ($errors['reports'] as &$report) {
    if (
        isset($report['identifier'], $report['status']) &&
        $report['identifier'] === $identifier &&
        $report['status'] === 'reported'
    ) {
        $found = true;
    // Check if this IP has already reported this show
        if (isset($report['reportingIps']) && in_array($ip, $report['reportingIps'])) {
            echo json_encode(['success' => false, 'message' => $MSG_ALREADY_REPORTED]);
            exit;
        }
        // Add IP to reportingIps and increment reportCount
        if (!isset($report['reportingIps']) || !is_array($report['reportingIps'])) {
            $report['reportingIps'] = [];
        }
        $report['reportingIps'][] = $ip;
        $report['reportCount'] = isset($report['reportCount']) && is_numeric($report['reportCount'])
            ? $report['reportCount'] + 1
            : 2;
    // If first time, set to 2 (since it was 1 before)
        // Optionally update date to most recent report
        $report['date'] = $date;
    // Save and return success
        if (file_put_contents($logFile, json_encode($errors, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) === false) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => $MSG_WRITE_ERROR]);
            exit;
        }
        echo json_encode(['success' => true, 'message' => $MSG_SUCCESS]);
        exit;
    }
}
unset($report);
// If not found, add new report
$errors['reports'][] = [
    'playlist' => $playlist,
    'title' => $title,
    'category' => $category,
    'identifier' => $identifier,
    'imdb' => $imdb,
    'date' => $date,
    'reportingIps' => [$ip],
    'reportCount' => 1,
    'status' => 'reported'
];
if (file_put_contents($logFile, json_encode($errors, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) === false) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $MSG_WRITE_ERROR]);
    exit;
}

echo json_encode(['success' => true, 'message' => $MSG_SUCCESS]);
