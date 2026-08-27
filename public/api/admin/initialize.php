<?php

use FreeTV\Admin\Database;
use FreeTV\Admin\InitializationPlan;

header('Content-Type: application/json');
header('Cache-Control: no-store');

function initializeRespond(int $httpStatus, array $payload): void
{
    http_response_code($httpStatus);
    echo json_encode($payload);
    exit;
}

function initializeReadJsonObject(): array
{
    $requestObject = json_decode(file_get_contents('php://input'));
    if (json_last_error() !== JSON_ERROR_NONE || !is_object($requestObject)) {
        initializeRespond(400, ['success' => false, 'message' => 'Invalid JSON request body']);
    }

    return get_object_vars($requestObject);
}

function initializeValidateUsername($value): string
{
    if (!is_string($value)) {
        initializeRespond(400, ['success' => false, 'message' => 'Invalid username']);
    }

    $username = trim($value);
    $length = function_exists('mb_strlen') ? mb_strlen($username, 'UTF-8') : strlen($username);
    if ($username === '' || $length > 100 || !preg_match('/^[A-Za-z0-9._-]+$/', $username)) {
        initializeRespond(400, [
            'success' => false,
            'message' => 'Username must be 1-100 characters using letters, numbers, dots, dashes, or underscores',
        ]);
    }

    return $username;
}

function initializeValidatePassword($value): string
{
    if (!is_string($value) || trim($value) === '' || strlen($value) < 6) {
        initializeRespond(400, ['success' => false, 'message' => 'Password must be at least 6 characters']);
    }

    return $value;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    initializeRespond(405, ['success' => false, 'message' => 'Method not allowed']);
}

$input = initializeReadJsonObject();
$allowedFields = ['username', 'password', 'password_confirmation'];
$unexpectedFields = array_diff(array_keys($input), $allowedFields);
if ($unexpectedFields !== []) {
    initializeRespond(400, ['success' => false, 'message' => 'Unexpected request fields']);
}

$username = initializeValidateUsername($input['username'] ?? null);
$password = initializeValidatePassword($input['password'] ?? null);
$passwordConfirmation = $input['password_confirmation'] ?? null;
if (!is_string($passwordConfirmation) || !hash_equals($password, $passwordConfirmation)) {
    initializeRespond(400, ['success' => false, 'message' => 'Password confirmation does not match']);
}

$autoloadPath = __DIR__ . '/../../../vendor/autoload.php';
if (!file_exists($autoloadPath)) {
    initializeRespond(503, ['success' => false, 'message' => 'PHP dependencies are unavailable']);
}

try {
    require_once $autoloadPath;
    require_once __DIR__ . '/Database.php';
    require_once __DIR__ . '/InitializationPlan.php';
} catch (\Throwable $e) {
    error_log('Initialization dependency loading error: ' . $e->getMessage());
    initializeRespond(503, ['success' => false, 'message' => 'PHP dependencies are unavailable']);
}

if (!class_exists('\Illuminate\Database\Capsule\Manager') || !Database::hasExplicitConfig()) {
    initializeRespond(503, ['success' => false, 'message' => 'Database setup is incomplete']);
}

$connection = null;
$lockAcquired = false;
$lockName = 'freetv_initialize_application';

try {
    $connection = Database::init()->getConnection();
    $connection->getPdo();

    $lockResult = $connection->selectOne('SELECT GET_LOCK(?, 10) AS acquired', [$lockName]);
    if (!$lockResult || (int) $lockResult->acquired !== 1) {
        initializeRespond(409, [
            'success' => false,
            'message' => 'Initialization is already in progress. Please try again.',
        ]);
    }
    $lockAcquired = true;

    $result = $connection->transaction(function () use ($connection, $username, $password): string {
        $hasUsers = Database::table('users')
            ->orderBy('id')
            ->lockForUpdate()
            ->first(['id']) !== null;
        $hasPlaylists = Database::table('playlists')
            ->orderBy('id')
            ->lockForUpdate()
            ->first(['id']) !== null;
        $hasPlaylistShows = Database::table('playlist_shows')
            ->orderBy('id')
            ->lockForUpdate()
            ->first(['id']) !== null;

        $plan = InitializationPlan::forState($hasUsers, $hasPlaylists, $hasPlaylistShows);
        if ($plan === InitializationPlan::ALREADY_INITIALIZED) {
            return 'already_initialized';
        }

        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        if ($passwordHash === false) {
            throw new \RuntimeException('Password hashing failed');
        }

        Database::table('users')->insert([
            'username' => $username,
            'password_hash' => $passwordHash,
            'role' => 'admin',
            'status' => 'active',
        ]);

        if ($plan === InitializationPlan::CREATE_ADMIN_AND_STARTER) {
            Database::table('playlists')->insert([
                'filename' => 'playlist-one.json',
                'dbtitle' => 'Playlist One',
                'dbversion' => null,
                'author' => null,
                'email' => null,
                'link' => null,
                'lastupdated' => $connection->raw('CURRENT_TIMESTAMP'),
                'is_default' => 1,
                'sort_order' => 0,
            ]);
        }

        Database::table('app_settings')->insertOrIgnore([
            'setting_key' => 'show_ads',
            'setting_value' => 'false',
            'scope' => 'viewer',
        ]);

        return 'initialized';
    });
} catch (\Throwable $e) {
    error_log('Initialization database error: ' . $e->getMessage());
    initializeRespond(500, ['success' => false, 'message' => 'FreeTV initialization failed']);
} finally {
    if ($lockAcquired && $connection !== null) {
        try {
            $connection->selectOne('SELECT RELEASE_LOCK(?) AS released', [$lockName]);
        } catch (\Throwable $releaseException) {
            error_log('Initialization lock release error: ' . $releaseException->getMessage());
        }
    }
}

if ($result === 'already_initialized') {
    initializeRespond(409, ['success' => false, 'message' => 'FreeTV has already been initialized']);
}

initializeRespond(201, ['success' => true, 'message' => 'FreeTV library initialized']);
