<?php

use FreeTV\Admin\Database;

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
    $connection = Database::init()->getConnection();
    $connection->getPdo();
} catch (\Throwable $e) {
    error_log('Readiness database connection error: ' . $e->getMessage());
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
    respond('schema_missing', 503, ['missing_tables' => $missingTables]);
}

try {
    if (!Database::table('users')->exists()) {
        respond('initialization_required', 200);
    }
} catch (\Throwable $e) {
    error_log('Readiness application state inspection error: ' . $e->getMessage());
    respond('database_unavailable', 503);
}

respond('ready', 200);
