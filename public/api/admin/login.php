<?php

require_once 'config.php';

session_start();
header('Content-Type: application/json');
$data = json_decode(file_get_contents('php://input'), true);
$user = $data['user'] ?? '';
$pass = $data['pass'] ?? '';
use FreeTV\Admin\AdminConfig;
$config = AdminConfig::getInstance();

try {
    $pdo = $config->getPdo();
    $stmt = $pdo->prepare('SELECT id, username, password_hash, role, status FROM users WHERE username = :username AND status = :status LIMIT 1');
    $stmt->execute([':username' => $user, ':status' => 'active']);
    $found = $stmt->fetch();

    if ($found && password_verify($pass, $found['password_hash'])) {
        $dt = new DateTime('now', new DateTimeZone('UTC'));
        $loginTime = $dt->format('Y-m-d H:i:s');
        $update = $pdo->prepare('UPDATE users SET last_login_at = :last_login_at WHERE id = :id');
        $update->execute([':last_login_at' => $loginTime, ':id' => $found['id']]);

        $_SESSION['admin'] = [
            'username' => $found['username'],
            'role' => $found['role']
        ];
        echo json_encode(['success' => true]);
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Invalid username or password.']);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error.']);
}
