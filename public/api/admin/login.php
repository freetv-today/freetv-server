<?php

session_start();
header('Content-Type: application/json');
$data = json_decode(file_get_contents('php://input'), true);
$user = $data['user'] ?? '';
$pass = $data['pass'] ?? '';

$apdata_path = __DIR__ . '/../../assets/apdata.key';
$apdata = json_decode(file_get_contents($apdata_path), true);
$users = $apdata['users'];
$found = null;
$found_index = null;
foreach ($users as $i => $u) {
    if ($u['username'] === $user && $u['status'] === 'active') {
        $found = $u;
        $found_index = $i;
        break;
    }
}
if ($found && password_verify($pass, $found['password'])) {
    // Update lastLogin in ISO8601 with milliseconds and Z
    $dt = new DateTime('now', new DateTimeZone('UTC'));
    $millis = (int)($dt->format('u') / 1000);
    $iso8601 = $dt->format('Y-m-d\TH:i:s') . '.' . str_pad($millis, 3, '0', STR_PAD_LEFT) . 'Z';
    $users[$found_index]['lastLogin'] = $iso8601;
    $apdata['users'] = $users;
    file_put_contents($apdata_path, json_encode($apdata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    $_SESSION['admin'] = [
        'username' => $found['username'],
        'role' => $found['role']
    ];
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid username or password.']);
}
