<?php

use FreeTV\Admin\Database;
use FreeTV\Admin\DatabaseCapabilityProbe;
use FreeTV\Admin\DatabasePermissionsInsufficientException;
use FreeTV\Admin\DatabaseReadiness;

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
    require_once __DIR__ . '/MariaDbError.php';
    require_once __DIR__ . '/SqlPackageExecutor.php';
    require_once __DIR__ . '/SchemaBootstrapper.php';
    require_once __DIR__ . '/DatabaseReadiness.php';
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
    $result = (new DatabaseReadiness(
        static fn() => Database::createConfiguredConnection(),
        static fn(): string => (new DatabaseCapabilityProbe(
            $bootstrapConnection,
            null,
            null,
            null,
            static fn() => Database::createConfiguredConnection()
        ))->detect()
    ))->check();
} catch (DatabasePermissionsInsufficientException $e) {
    error_log('Readiness database permissions error: ' . $e->getMessage());
    respond('database_permissions_insufficient', 503);
} catch (\Throwable $e) {
    error_log('Readiness database inspection error: ' . $e->getMessage());
    respond('database_unavailable', 503);
}

$status = $result['status'];
$httpStatus = $result['http_status'];
unset($result['status'], $result['http_status']);
respond($status, $httpStatus, $result);
