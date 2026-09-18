<?php

header('Content-Type: application/json');

require_once __DIR__ . '/Authorization.php';

function respond(int $status, array $payload): void
{
    http_response_code($status);
    echo json_encode($payload);
    exit;
}

function readJsonObject(): array
{
    $requestObject = json_decode(file_get_contents('php://input'));
    if (json_last_error() !== JSON_ERROR_NONE || !is_object($requestObject)) {
        respond(400, ['success' => false, 'message' => 'Invalid JSON request body']);
    }

    return get_object_vars($requestObject);
}

function parsePositiveId($value)
{
    $validFormat = is_int($value)
        || (is_string($value) && preg_match('/^[1-9][0-9]*$/', $value));

    if (!$validFormat) {
        return false;
    }

    return filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
}

function validateUsername($value): string
{
    if (!is_string($value)) {
        respond(400, ['success' => false, 'message' => 'Invalid username']);
    }

    $username = trim($value);
    $length = function_exists('mb_strlen') ? mb_strlen($username, 'UTF-8') : strlen($username);
    if ($username === '' || $length > 100 || !preg_match('/^[A-Za-z0-9._-]+$/', $username)) {
        respond(400, [
            'success' => false,
            'message' => 'Username must be 1-100 characters using letters, numbers, dots, dashes, or underscores',
        ]);
    }

    return $username;
}

function validatePassword($value): string
{
    if (!is_string($value) || trim($value) === '' || strlen($value) < 6) {
        respond(400, ['success' => false, 'message' => 'Password must be at least 6 characters']);
    }

    return $value;
}

function validateRole($value): string
{
    $allowedRoles = ['viewer', 'editor', 'admin'];
    if (!is_string($value) || !in_array($value, $allowedRoles, true)) {
        respond(400, ['success' => false, 'message' => 'Invalid role']);
    }

    return $value;
}

function validateStatus($value): string
{
    $allowedStatuses = ['active', 'disabled'];
    if (!is_string($value) || !in_array($value, $allowedStatuses, true)) {
        respond(400, ['success' => false, 'message' => 'Invalid status']);
    }

    return $value;
}

function isDuplicateUsernameException(\Throwable $e): bool
{
    return $e instanceof \Illuminate\Database\QueryException
        && ((string) $e->getCode() === '23000' || ($e->errorInfo[1] ?? null) === 1062);
}

$sessionUser = \FreeTV\Admin\requireRole('admin');

$currentUserId = parsePositiveId($sessionUser['id']);
if ($currentUserId === false) {
    respond(401, ['success' => false, 'message' => 'Unauthorized']);
}

$action = $_GET['action'] ?? null;
$allowedMethods = [
    'list' => 'GET',
    'add' => 'POST',
    'edit' => 'POST',
    'changepass' => 'POST',
    'delete' => 'POST',
];

if (!is_string($action) || !array_key_exists($action, $allowedMethods)) {
    respond(400, ['success' => false, 'message' => 'Unknown or missing action']);
}

if ($_SERVER['REQUEST_METHOD'] !== $allowedMethods[$action]) {
    respond(405, ['success' => false, 'message' => 'Method not allowed']);
}

require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/Database.php';

use FreeTV\Admin\Database;

try {
    $capsule = Database::init();
    $connection = $capsule->getConnection();

    if ($action === 'list') {
        $users = Database::table('users')
            ->orderBy('username')
            ->orderBy('id')
            ->get([
                'id',
                'username',
                'role',
                'status',
                'created_at',
                'last_login_at',
                'updated_at',
            ]);

        respond(200, ['success' => true, 'users' => $users]);
    }

    $input = readJsonObject();

    if ($action === 'add') {
        $username = validateUsername($input['username'] ?? null);
        $password = validatePassword($input['password'] ?? null);
        $role = validateRole($input['role'] ?? null);
        $status = validateStatus($input['status'] ?? null);

        $result = $connection->transaction(function () use ($username, $password, $role, $status) {
            $duplicate = Database::table('users')
                ->where('username', $username)
                ->exists();

            if ($duplicate) {
                return 'duplicate';
            }

            Database::table('users')->insert([
                'username' => $username,
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                'role' => $role,
                'status' => $status,
            ]);

            return 'created';
        });

        if ($result === 'duplicate') {
            respond(409, ['success' => false, 'message' => 'Username already exists']);
        }

        respond(201, ['success' => true, 'message' => 'User added']);
    }

    $targetId = parsePositiveId($input['id'] ?? null);
    if ($targetId === false) {
        respond(400, ['success' => false, 'message' => 'Invalid user ID']);
    }

    if ($action === 'changepass') {
        $password = validatePassword($input['password'] ?? null);
        $targetExists = Database::table('users')->where('id', $targetId)->exists();
        if (!$targetExists) {
            respond(404, ['success' => false, 'message' => 'User not found']);
        }

        Database::table('users')
            ->where('id', $targetId)
            ->update(['password_hash' => password_hash($password, PASSWORD_DEFAULT)]);

        respond(200, ['success' => true, 'message' => 'Password updated']);
    }

    if ($action === 'edit') {
        $username = validateUsername($input['username'] ?? null);
        $role = validateRole($input['role'] ?? null);
        $status = validateStatus($input['status'] ?? null);

        $result = $connection->transaction(function () use (
            $targetId,
            $currentUserId,
            $username,
            $role,
            $status
        ) {
            $activeAdmins = Database::table('users')
                ->where('role', 'admin')
                ->where('status', 'active')
                ->orderBy('id')
                ->lockForUpdate()
                ->get(['id']);

            $target = Database::table('users')
                ->where('id', $targetId)
                ->lockForUpdate()
                ->first(['id', 'username', 'role', 'status']);

            if (!$target) {
                return 'not_found';
            }

            if ((int) $target->id === $currentUserId && $role !== 'admin') {
                return 'self_demote';
            }

            if ((int) $target->id === $currentUserId && $status !== 'active') {
                return 'self_disable';
            }

            $duplicate = Database::table('users')
                ->where('username', $username)
                ->where('id', '!=', $targetId)
                ->exists();
            if ($duplicate) {
                return 'duplicate';
            }

            $wasActiveAdmin = $target->role === 'admin' && $target->status === 'active';
            $willBeActiveAdmin = $role === 'admin' && $status === 'active';
            $activeAdminCount = count($activeAdmins) - ($wasActiveAdmin ? 1 : 0) + ($willBeActiveAdmin ? 1 : 0);
            if ($activeAdminCount < 1) {
                return 'final_active_admin';
            }

            Database::table('users')
                ->where('id', $targetId)
                ->update([
                    'username' => $username,
                    'role' => $role,
                    'status' => $status,
                ]);

            return 'updated';
        });

        if ($result === 'not_found') {
            respond(404, ['success' => false, 'message' => 'User not found']);
        }
        if ($result === 'duplicate') {
            respond(409, ['success' => false, 'message' => 'Username already exists']);
        }
        if ($result === 'self_demote') {
            respond(409, ['success' => false, 'message' => 'You cannot demote your own account']);
        }
        if ($result === 'self_disable') {
            respond(409, ['success' => false, 'message' => 'You cannot disable your own account']);
        }
        if ($result === 'final_active_admin') {
            respond(409, ['success' => false, 'message' => 'At least one active admin is required']);
        }

        if ($targetId === $currentUserId) {
            $_SESSION['admin']['username'] = $username;
        }

        respond(200, ['success' => true, 'message' => 'User updated']);
    }

    $result = $connection->transaction(function () use ($targetId, $currentUserId) {
        $activeAdmins = Database::table('users')
            ->where('role', 'admin')
            ->where('status', 'active')
            ->orderBy('id')
            ->lockForUpdate()
            ->get(['id']);

        $target = Database::table('users')
            ->where('id', $targetId)
            ->lockForUpdate()
            ->first(['id', 'role', 'status']);

        if (!$target) {
            return 'not_found';
        }

        if ((int) $target->id === $currentUserId) {
            return 'self_delete';
        }

        $wasActiveAdmin = $target->role === 'admin' && $target->status === 'active';
        $activeAdminCount = count($activeAdmins) - ($wasActiveAdmin ? 1 : 0);
        if ($activeAdminCount < 1) {
            return 'final_active_admin';
        }

        Database::table('users')->where('id', $targetId)->delete();
        return 'deleted';
    });

    if ($result === 'not_found') {
        respond(404, ['success' => false, 'message' => 'User not found']);
    }
    if ($result === 'self_delete') {
        respond(409, ['success' => false, 'message' => 'You cannot delete your own account']);
    }
    if ($result === 'final_active_admin') {
        respond(409, ['success' => false, 'message' => 'At least one active admin is required']);
    }

    respond(200, ['success' => true, 'message' => 'User deleted']);
} catch (\Throwable $e) {
    error_log('User Manager API Error: ' . $e->getMessage());

    if (isDuplicateUsernameException($e)) {
        respond(409, ['success' => false, 'message' => 'Username already exists']);
    }

    respond(500, ['success' => false, 'message' => 'Database error']);
}
