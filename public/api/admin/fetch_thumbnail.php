<?php

session_start();
if (!isset($_SESSION['admin'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Set max execution time to 60 seconds
set_time_limit(60);

// Register shutdown handler for graceful timeout error
$timeout_error_sent = false;
register_shutdown_function(function () {
    global $timeout_error_sent;
    if ($timeout_error_sent) {
        return;
    }
    $error = error_get_last();
    if ($error && strpos($error['message'], 'Maximum execution time') !== false) {
        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'failed',
            'errorcode' => 504,
            'message' => 'Request timed out (max 60 seconds)'
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        $timeout_error_sent = true;
    }
});

// Accept ?imdb=... as GET parameter
$imdb_id = isset($_GET['imdb']) ? trim($_GET['imdb']) : '';
if ($imdb_id === '') {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'failed', 'errorcode' => 400, 'message' => 'IMDB ID not provided'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

$url = "https://www.imdb.com/title/" . $imdb_id . "/";
$headers = @get_headers($url);
if (!$headers || strpos($headers[0], '200') === false) {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'failed', 'errorcode' => 404, 'message' => 'IMDB page not found'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

$html = @file_get_contents($url);
if ($html === false) {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'failed', 'errorcode' => 500, 'message' => 'Failed to fetch IMDB page'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

$doc = new DOMDocument();
@$doc->loadHTML($html);
$metas = $doc->getElementsByTagName('meta');
$image_url = '';
foreach ($metas as $meta) {
    if ($meta->getAttribute('property') === 'og:image') {
        $image_url = $meta->getAttribute('content');
        break;
    }
}

if ($image_url) {
    $img_headers = @get_headers($image_url);
    if (!$img_headers || strpos($img_headers[0], '200') === false) {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'failed', 'errorcode' => 404, 'message' => 'Thumbnail image not found'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        exit;
    }
    $image_data = @file_get_contents($image_url);
    if ($image_data === false) {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'failed', 'errorcode' => 500, 'message' => 'Failed to fetch image'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        exit;
    }
    // Save to /public/temp/[imdb].jpg using relative filesystem path
    $temp_dir = __DIR__ . '/../../temp/';
    if (!is_dir($temp_dir)) {
        mkdir($temp_dir, 0777, true);
    }
    $image_path = $temp_dir . $imdb_id . '.jpg';
    $saved = file_put_contents($image_path, $image_data);
    if ($saved === false) {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'failed', 'errorcode' => 500, 'message' => 'Failed to save image to server'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        exit;
    }
    // Return the frontend-accessible URL of the saved image
    $local_image_url = '/temp/' . $imdb_id . '.jpg';
    header('Content-Type: application/json');
    echo json_encode(['status' => 'success', 'image_url' => $local_image_url], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
} else {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'failed', 'errorcode' => 404, 'message' => 'Image not found in IMDB page'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
}
