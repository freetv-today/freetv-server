<?php

header('Content-Type: application/json');

session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$requestObject = json_decode(file_get_contents('php://input'));
if (json_last_error() !== JSON_ERROR_NONE || !is_object($requestObject)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON request body']);
    exit;
}

$input = get_object_vars($requestObject);
$username = $input['user'] ?? null;
$password = $input['pass'] ?? null;

if (!is_string($username) || trim($username) === '' || !is_string($password) || $password === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Username and password are required']);
    exit;
}

require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/Database.php';

use FreeTV\Admin\Database;

$sessionEstablished = false;

try {
    $capsule = Database::init();
    $connection = $capsule->getConnection();

    $found = Database::table('users')
        ->where('username', trim($username))
        ->where('status', 'active')
        ->first(['id', 'username', 'password_hash', 'role']);

    if (!$found || !password_verify($password, $found->password_hash)) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Invalid username or password.']);
        exit;
    }

    session_regenerate_id(true);
    $_SESSION['admin'] = [
        'id' => (int) $found->id,
        'username' => $found->username,
        'role' => $found->role,
    ];
    $sessionEstablished = true;

    Database::table('users')
        ->where('id', $found->id)
        ->update(['last_login_at' => $connection->raw('CURRENT_TIMESTAMP')]);

    echo json_encode(['success' => true, 'message' => 'Login successful']);
} catch (\Throwable $e) {
    if ($sessionEstablished) {
        unset($_SESSION['admin']);
    }
    error_log('Login DB Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
