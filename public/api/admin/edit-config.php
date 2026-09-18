<?php

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/Authorization.php';
\FreeTV\Admin\requireRole('admin');

function respond(int $status, array $payload): void
{
    http_response_code($status);
    echo json_encode($payload);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'] ?? '';
if (!in_array($method, ['GET', 'POST'], true)) {
    header('Allow: GET, POST');
    respond(405, ['success' => false, 'message' => 'Method not allowed']);
}

$settings = null;
if ($method === 'POST') {
    $requestObject = json_decode(file_get_contents('php://input'));
    if (json_last_error() !== JSON_ERROR_NONE || !is_object($requestObject)) {
        respond(400, ['success' => false, 'message' => 'Invalid JSON request body']);
    }

    $settings = get_object_vars($requestObject);
    if ($settings === []) {
        respond(400, ['success' => false, 'message' => 'No settings provided']);
    }
}

require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Settings.php';

use FreeTV\Admin\Settings;

try {
    if ($method === 'POST') {
        try {
            $result = Settings::write($settings);
        } catch (\InvalidArgumentException $e) {
            respond(400, ['success' => false, 'message' => $e->getMessage()]);
        }
    } else {
        $result = Settings::read();
    }

    respond(200, ['success' => true, 'settings' => $result]);
} catch (\Throwable $e) {
    error_log('Settings API Error: ' . $e->getMessage());
    respond(500, ['success' => false, 'message' => 'Database error']);
}
