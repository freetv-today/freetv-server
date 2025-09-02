
<?php
session_start();
if (!isset($_SESSION['admin'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}
header('Content-Type: application/json');

// Path to apdata.key
$apdata_path = __DIR__ . '/../../assets/apdata.key';

// Helper: Load users
function load_users($path)
{
    $data = json_decode(file_get_contents($path), true);
    return $data['users'] ?? [];
}

// Helper: Save users
function save_users($path, $users)
{
    $data = ["users" => $users];
    return file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT));
}

// Helper: Find user by id
function find_user_index($users, $id)
{
    foreach ($users as $i => $u) {
        if ($u['id'] == $id) {
            return $i;
        }
    }
    return -1;
}

$action = $_GET['action'] ?? $_POST['action'] ?? null;
if (!$action) {
    echo json_encode(["success" => false, "message" => "No action specified."]);
    exit;
}

$users = load_users($apdata_path);

switch ($action) {
    case 'list':
        // List all users
        $safeUsers = array_map(function ($u) {
            $copy = $u;
            unset($copy['password']);
            return $copy;
        }, $users);
        echo json_encode(["success" => true, "users" => $safeUsers]);
        break;

    case 'add':
        // Add a new user
        $data = json_decode(file_get_contents('php://input'), true);
        $username = trim($data['username'] ?? '');
        $password = $data['password'] ?? '';
        $role = $data['role'] ?? '';
        $status = $data['status'] ?? 'active';
        if (!$username || !$password || !$role) {
            echo json_encode(["success" => false, "message" => "Missing required fields."]);
            exit;
        }
        // Only one admin allowed
        if ($role === 'admin' && array_filter($users, fn($u) => $u['role'] === 'admin')) {
            echo json_encode(["success" => false, "message" => "Only one admin user allowed."]);
            exit;
        }
        // Unique username
        if (array_filter($users, fn($u) => $u['username'] === $username)) {
            echo json_encode(["success" => false, "message" => "Username already exists."]);
            exit;
        }
        $id = max(array_column($users, 'id')) + 1;
        // ISO8601 with milliseconds and Z
        $dt = new DateTime('now', new DateTimeZone('UTC'));
        $millis = (int)($dt->format('u') / 1000);
        $iso8601 = $dt->format('Y-m-d\TH:i:s') . '.' . str_pad($millis, 3, '0', STR_PAD_LEFT) . 'Z';
        $users[] = [
            'id' => $id,
            'username' => $username,
            'password' => password_hash($password, PASSWORD_DEFAULT),
            'role' => $role,
            'status' => $status,
            'created' => $iso8601,
            'lastLogin' => ''
        ];
        save_users($apdata_path, $users);
        echo json_encode(["success" => true, "message" => "User added."]);
        break;

    case 'edit':
        // Edit user info (not password)
        $data = json_decode(file_get_contents('php://input'), true);
        $id = $data['id'] ?? null;
        $username = trim($data['username'] ?? '');
        $role = $data['role'] ?? '';
        $status = $data['status'] ?? '';
        $i = find_user_index($users, $id);
        if ($i === -1) {
            echo json_encode(["success" => false, "message" => "User not found."]);
            exit;
        }
        // Prevent editing admin role/username
        if ($users[$i]['role'] === 'admin') {
            if ($role !== 'admin' || $username !== $users[$i]['username']) {
                echo json_encode(["success" => false, "message" => "Cannot change admin username or role."]);
                exit;
            }
        }
        // Unique username
        if ($username !== $users[$i]['username'] && array_filter($users, fn($u) => $u['username'] === $username)) {
            echo json_encode(["success" => false, "message" => "Username already exists."]);
            exit;
        }
        $users[$i]['username'] = $username;
        $users[$i]['role'] = $role;
        $users[$i]['status'] = $status;
        save_users($apdata_path, $users);
        echo json_encode(["success" => true, "message" => "User updated."]);
        break;

    case 'changepass':
        // Change user password
        $data = json_decode(file_get_contents('php://input'), true);
        $id = $data['id'] ?? null;
        $password = $data['password'] ?? '';
        $i = find_user_index($users, $id);
        if ($i === -1) {
            echo json_encode(["success" => false, "message" => "User not found."]);
            exit;
        }
        $users[$i]['password'] = password_hash($password, PASSWORD_DEFAULT);
        save_users($apdata_path, $users);
        echo json_encode(["success" => true, "message" => "Password updated."]);
        break;

    case 'delete':
        // Delete user (not admin)
        $data = json_decode(file_get_contents('php://input'), true);
        $id = $data['id'] ?? null;
        $i = find_user_index($users, $id);
        if ($i === -1) {
            echo json_encode(["success" => false, "message" => "User not found."]);
            exit;
        }
        if ($users[$i]['role'] === 'admin') {
            echo json_encode(["success" => false, "message" => "Cannot delete admin user."]);
            exit;
        }
        array_splice($users, $i, 1);
        save_users($apdata_path, $users);
        echo json_encode(["success" => true, "message" => "User deleted."]);
        break;

    default:
        echo json_encode(["success" => false, "message" => "Unknown action."]);
        break;
}
