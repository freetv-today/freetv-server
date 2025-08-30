
<?php
session_start();
if (!isset($_SESSION['admin'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}
// public/api/admin/update-meta.php
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$playlist = isset($input['playlist']) ? basename($input['playlist']) : null;
$meta = isset($input['meta']) ? $input['meta'] : null;

if (!$playlist || !$meta || !is_array($meta)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing playlist or meta data']);
    exit;
}

$playlistPath = __DIR__ . '/../../playlists/' . $playlist;
if (!file_exists($playlistPath)) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Playlist not found']);
    exit;
}

// Load playlist JSON
$data = json_decode(file_get_contents($playlistPath), true);
if (!$data) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Invalid playlist data']);
    exit;
}

// Only update meta fields, not shows
$fields = ['dbtitle', 'dbversion', 'author', 'email', 'link'];
$changed = false;
foreach ($fields as $field) {
    if (isset($meta[$field]) && (!isset($data[$field]) || $data[$field] !== $meta[$field])) {
        $data[$field] = $meta[$field];
        $changed = true;
    }
}

if (!$changed) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'No values were changed.']);
    exit;
}

// Only update lastupdated if something changed
$data['lastupdated'] = gmdate('Y-m-d\TH:i:s.\0\0\0\Z');

// Save JSON
if (file_put_contents($playlistPath, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) === false) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to save playlist']);
    exit;
}

echo json_encode(['success' => true, 'message' => 'Meta data updated']);
