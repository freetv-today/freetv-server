<?php

use FreeTV\Admin\Database;
use FreeTV\Admin\DatabaseCapabilityProbe;
use FreeTV\Admin\DatabasePermissionsInsufficientException;

header('Content-Type: application/json');
header('Cache-Control: no-store');

function respond($status, $httpStatus, $extra = [])
{
    http_response_code($httpStatus);
    echo json_encode(array_merge(['status' => $status], $extra));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    header('Allow: GET');
    respond('method_not_allowed', 405);
}

$autoloadPath = __DIR__ . '/../../../vendor/autoload.php';

if (!file_exists($autoloadPath)) {
    respond('dependencies_missing', 503);
}

try {
    require_once $autoloadPath;
    require_once __DIR__ . '/Database.php';
    require_once __DIR__ . '/DatabaseCapabilityProbe.php';
} catch (\Throwable $e) {
    error_log('Readiness dependency loading error: ' . $e->getMessage());
    respond('dependencies_missing', 503);
}

if (!class_exists('\Illuminate\Database\Capsule\Manager')
    || !class_exists('\Dotenv\Dotenv')
) {
    respond('dependencies_missing', 503);
}

if (!Database::hasExplicitConfig()) {
    respond('database_config_missing', 503);
}

try {
    $bootstrapConnection = Database::createBootstrapConnection();
    $bootstrapConnection->getPdo();
} catch (\Throwable $e) {
    error_log('Readiness MariaDB bootstrap connection error: ' . $e->getMessage());
    respond('database_unavailable', 503);
}

try {
    $databaseMode = (new DatabaseCapabilityProbe(
        $bootstrapConnection,
        null,
        null,
        null,
        static fn() => Database::createConfiguredConnection()
    ))->detect();
} catch (DatabasePermissionsInsufficientException $e) {
    error_log('Readiness database permissions error: ' . $e->getMessage());
    respond('database_permissions_insufficient', 503);
} catch (\Throwable $e) {
    error_log('Readiness database capability probe error: ' . $e->getMessage());
    respond('database_unavailable', 503);
}

try {
    $connection = Database::createConfiguredConnection();
    $connection->getPdo();
} catch (\Throwable $e) {
    if ($databaseMode === DatabaseCapabilityProbe::MODE_CREATE_DATABASE) {
        respond('database_missing', 503, ['database_mode' => $databaseMode]);
    }
    error_log('Readiness configured database connection error: ' . $e->getMessage());
    respond('database_unavailable', 503);
}

$requiredTables = ['users', 'playlists', 'playlist_shows', 'app_settings'];
$missingTables = [];

try {
    $schema = $connection->getSchemaBuilder();
    foreach ($requiredTables as $table) {
        if (!$schema->hasTable($table)) {
            $missingTables[] = $table;
        }
    }
} catch (\Throwable $e) {
    error_log('Readiness schema inspection error: ' . $e->getMessage());
    respond('database_unavailable', 503);
}

if ($missingTables !== []) {
    respond('schema_missing', 503, ['missing_tables' => $missingTables, 'database_mode' => $databaseMode]);
}

try {
    if (!Database::table('users')->exists()) {
        respond('initialization_required', 200, ['database_mode' => $databaseMode]);
    }
} catch (\Throwable $e) {
    error_log('Readiness application state inspection error: ' . $e->getMessage());
    respond('database_unavailable', 503);
}

respond('ready', 200);
