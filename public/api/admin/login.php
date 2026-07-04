<?php
require_once __DIR__ . '/../../../vendor/autoload.php';
require_once 'config.php';
require_once __DIR__ . '/Database.php';

session_start();
header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$user = trim($data['user'] ?? '');
$pass = $data['pass'] ?? '';

use FreeTV\Admin\Database;

try {
    Database::init();

    // Test query to confirm DB layer works
    $found = Database::table('users')
        ->where('username', $user)
        ->where('status', 'active')
        ->first(['id', 'username', 'password_hash', 'role']);

    if ($found && password_verify($pass, $found->password_hash)) {
        $loginTime = date('Y-m-d H:i:s');
        
        Database::table('users')
            ->where('id', $found->id)
            ->update(['last_login_at' => $loginTime]);

        $_SESSION['admin'] = [
            'username' => $found->username,
            'role' => $found->role ?? 'admin'
        ];

        echo json_encode([
            'success' => true, 
            'message' => 'Login successful using new DB layer'
        ]);
    } else {
        echo json_encode([
            'success' => false, 
            'message' => 'Invalid username or password.'
        ]);
    }
} catch (\Throwable $e) {
    error_log("Login DB Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}