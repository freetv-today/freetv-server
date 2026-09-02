<?php

use FreeTV\Admin\Database;
use FreeTV\Admin\DatabaseCapabilityProbe;
use FreeTV\Admin\Bootstrapper;
use FreeTV\Admin\SchemaBootstrapper;

require_once __DIR__ . '/Session.php';

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

session_start();

$input = initializeReadJsonObject();
$allowedFields = ['mode', 'username', 'password', 'password_confirmation'];
$unexpectedFields = array_diff(array_keys($input), $allowedFields);
if ($unexpectedFields !== []) {
    initializeRespond(400, ['success' => false, 'message' => 'Unexpected request fields']);
}

$mode = $input['mode'] ?? 'fresh';
if (!is_string($mode) || !in_array($mode, ['fresh', 'sample', 'official'], true)) {
    initializeRespond(400, ['success' => false, 'message' => 'Invalid initialization mode']);
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
    require_once __DIR__ . '/DatabaseCapabilityProbe.php';
    require_once __DIR__ . '/MariaDbError.php';
    require_once __DIR__ . '/DatabaseIdentifier.php';
    require_once __DIR__ . '/SqlPackageExecutor.php';
    require_once __DIR__ . '/SchemaBootstrapper.php';
    require_once __DIR__ . '/FreshBootstrapData.php';
    require_once __DIR__ . '/FreshDatabaseInstaller.php';
    require_once __DIR__ . '/FreshArtifactInstaller.php';
    require_once __DIR__ . '/DatasetPackage.php';
    require_once __DIR__ . '/PackageBootstrapContracts.php';
    require_once __DIR__ . '/DatasetPackageValidator.php';
    require_once __DIR__ . '/DatasetPackageProvider.php';
    require_once __DIR__ . '/PackageDatabaseInstaller.php';
    require_once __DIR__ . '/PackageArtifactInstaller.php';
    require_once __DIR__ . '/publication/PublicationException.php';
    require_once __DIR__ . '/publication/PublicationTimestamp.php';
    require_once __DIR__ . '/publication/ConfigPublicationSerializer.php';
    require_once __DIR__ . '/publication/PlaylistPublicationSerializer.php';
    require_once __DIR__ . '/publication/PlaylistIndexSerializer.php';
    require_once __DIR__ . '/Bootstrapper.php';
} catch (\Throwable $e) {
    error_log('Initialization dependency loading error: ' . $e->getMessage());
    initializeRespond(503, ['success' => false, 'message' => 'PHP dependencies are unavailable']);
}

if (!class_exists('\Illuminate\Database\Capsule\Manager') || !Database::hasExplicitConfig()) {
    initializeRespond(503, ['success' => false, 'message' => 'Database setup is incomplete']);
}

$bootstrapConnection = null;
$lockAcquired = false;
$lockName = 'freetv_initialize_application';

try {
    $bootstrapConnection = Database::createBootstrapConnection();
    $bootstrapConnection->getPdo();

    $lockResult = $bootstrapConnection->selectOne('SELECT GET_LOCK(?, 10) AS acquired', [$lockName]);
    if (!$lockResult || (int) $lockResult->acquired !== 1) {
        initializeRespond(409, [
            'success' => false,
            'message' => 'Initialization is already in progress. Please try again.',
        ]);
    }
    $lockAcquired = true;

    $schemaBootstrapper = new SchemaBootstrapper(
        $bootstrapConnection,
        static fn() => Database::createConfiguredConnection(),
        static fn(): string => (new DatabaseCapabilityProbe(
            $bootstrapConnection,
            null,
            null,
            null,
            static fn() => Database::createConfiguredConnection()
        ))->detect(),
        Database::configuredDatabaseName(),
        dirname(__DIR__, 3) . '/sql/freetv_mariadb_schema-tables-only.sql'
    );
    $paths = new \FreeTV\Admin\ServerPaths();
    $sqlExecutor = new \FreeTV\Admin\SqlPackageExecutor();
    $bootstrapper = new Bootstrapper(
        $schemaBootstrapper,
        new \FreeTV\Admin\FreshBootstrapData(
            $paths->appRoot() . '/resources/bootstrap/fresh.json'
        ),
        new \FreeTV\Admin\FreshDatabaseInstaller(),
        new \FreeTV\Admin\FreshArtifactInstaller($paths->publicRoot()),
        null,
        new \FreeTV\Admin\DatasetPackageProvider(
            $paths->tempRoot(),
            new \FreeTV\Admin\DatasetPackageValidator()
        ),
        new \FreeTV\Admin\PackageDatabaseInstaller(
            $sqlExecutor,
            $paths->appRoot() . '/sql/freetv_mariadb_schema-tables-only.sql'
        ),
        new \FreeTV\Admin\PackageArtifactInstaller($paths->publicRoot())
    );
    if ($mode !== 'fresh') {
        @set_time_limit(600);
    }
    $result = match ($mode) {
        'sample' => $bootstrapper->sample($username, $password),
        'official' => $bootstrapper->official($username, $password),
        default => $bootstrapper->fresh($username, $password),
    };
} catch (\Throwable $e) {
    error_log('Initialization database error: ' . $e->getMessage());
    initializeRespond(500, ['success' => false, 'message' => 'FreeTV initialization failed']);
} finally {
    if ($lockAcquired && $bootstrapConnection !== null) {
        try {
            $bootstrapConnection->selectOne('SELECT RELEASE_LOCK(?) AS released', [$lockName]);
        } catch (\Throwable $releaseException) {
            error_log('Initialization lock release error: ' . $releaseException->getMessage());
        }
    }
}

if ($result === Bootstrapper::ALREADY_INITIALIZED) {
    initializeRespond(409, ['success' => false, 'message' => 'FreeTV has already been initialized']);
}

\FreeTV\Admin\destroyAdminSession();
initializeRespond(201, ['success' => true, 'message' => 'FreeTV library initialized']);
