<?php

require_once __DIR__ . '/../playlist_utils.php';

session_start();
if (!isset($_SESSION['admin'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? null;
$playlist = $input['playlist'] ?? null;
$identifier = $input['identifier'] ?? null;

if (!$action || !$playlist || !$identifier) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

if (!in_array($action, ['delete', 'mark-ok'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
    exit;
}

$playlistPath = __DIR__ . '/../../playlists/' . basename($playlist);
$errorsPath = __DIR__ . '/../../logs/errors.json';

// --- Handle playlist operations ---
$playlistChanged = false;
$showRemoved = false;

if (file_exists($playlistPath)) {
    $playlistData = json_decode(file_get_contents($playlistPath), true);
    if (is_array($playlistData) && isset($playlistData['shows'])) {
        $originalCount = count($playlistData['shows']);

        if ($action === 'delete') {
            // Remove the show completely
            $playlistData['shows'] = array_values(array_filter($playlistData['shows'], function ($show) use ($identifier) {
                return !isset($show['identifier']) || $show['identifier'] !== $identifier;
            }));
            $showRemoved = (count($playlistData['shows']) < $originalCount);
        } elseif ($action === 'mark-ok') {
            // Set status to 'active' (or remove status field)
            foreach ($playlistData['shows'] as &$show) {
                if (isset($show['identifier']) && $show['identifier'] === $identifier) {
                    $show['status'] = 'active';
                    $playlistChanged = true;
                    break;
                }
            }
            unset($show);
        }

        if ($showRemoved || $playlistChanged) {
            // Update timestamp and save
            $playlistData['lastupdated'] = gmdate('Y-m-d\TH:i:s.000\Z');
            if (file_put_contents($playlistPath, json_encode($playlistData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) !== false) {
                $playlistChanged = true;
                // Rebuild index
                rebuild_index();
            }
        }
    }
}

// --- Handle errors.json operations ---
$errorsChanged = false;
if (file_exists($errorsPath)) {
    $errorsData = json_decode(file_get_contents($errorsPath), true);
    if (isset($errorsData['reports']) && is_array($errorsData['reports'])) {
        foreach ($errorsData['reports'] as &$report) {
            if (
                isset($report['playlist'], $report['identifier']) &&
                $report['playlist'] === $playlist &&
                $report['identifier'] === $identifier &&
                $report['status'] === 'reported'
            ) {
                $report['status'] = 'addressed';
                $errorsChanged = true;
                break;
            }
        }
        unset($report);

        if ($errorsChanged) {
            file_put_contents($errorsPath, json_encode($errorsData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        }
    }
}

// --- Return response ---
$success = ($action === 'delete' && ($showRemoved || $errorsChanged)) ||
           ($action === 'mark-ok' && ($playlistChanged || $errorsChanged));

if ($success) {
    echo json_encode([
        'success' => true,
        'message' => $action === 'delete' ? 'Item deleted successfully' : 'Item marked as OK',
        'playlistChanged' => $playlistChanged || $showRemoved,
        'errorsChanged' => $errorsChanged
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'No changes were made'
    ]);
}
