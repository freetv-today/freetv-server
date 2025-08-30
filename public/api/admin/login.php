<?php

session_start();
header('Content-Type: application/json');
$data = json_decode(file_get_contents('php://input'), true);
$user = $data['user'] ?? '';
$pass = $data['pass'] ?? '';
$users = json_decode(file_get_contents(__DIR__ . '/../../assets/apdata.key'), true)['users'];
$found = null;
foreach ($users as $u) {
    if ($u['username'] === $user && $u['status'] === 'active') {
        $found = $u;
        break;
    }
}
if ($found && password_verify($pass, $found['password'])) {
    $_SESSION['admin'] = [
        'username' => $found['username'],
        'role' => $found['role']
    ];
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid username or password.']);
}
