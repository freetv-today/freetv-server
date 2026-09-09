<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$endpoint = file_get_contents($root . '/public/api/admin/initialize.php');
if ($endpoint === false) {
    throw new RuntimeException('Could not read initialization endpoint');
}

function initializationContractAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

initializationContractAssert(
    str_contains($endpoint, "dirname(__DIR__, 3) . '/sql/freetv_mariadb_schema-tables-only.sql'"),
    'Initialization must use the canonical tables-only schema package'
);
initializationContractAssert(
    !str_contains($endpoint, 'freetv_mariadb_schema-create-db.sql'),
    'Initialization must not execute the hard-coded create-db package'
);
initializationContractAssert(
    strpos($endpoint, 'GET_LOCK') < strpos($endpoint, 'new SchemaBootstrapper'),
    'Initialization lock must be acquired before schema bootstrap'
);
initializationContractAssert(
    str_contains($endpoint, 'new Bootstrapper(')
        && str_contains($endpoint, "'fresh' => \$bootstrapper->fresh(\$username, \$password)")
        && str_contains($endpoint, "'baseline' => \$bootstrapper->baseline(\$username, \$password)")
        && str_contains($endpoint, "'sample' => \$bootstrapper->sample(\$username, \$password)")
        && str_contains($endpoint, "'official' => \$bootstrapper->official(\$username, \$password)")
        && !str_contains($endpoint, 'default => $bootstrapper->fresh($username, $password)'),
    'Initialization modes must explicitly delegate application orchestration to Bootstrapper'
);
$failureResponse = strpos($endpoint, 'InitializationErrorResponse::forException($e)');
$sessionDestroy = strpos($endpoint, 'destroyAdminSession()');
$alreadyInitialized = strpos($endpoint, 'if ($result === Bootstrapper::ALREADY_INITIALIZED)');
initializationContractAssert(
    $failureResponse !== false && $sessionDestroy !== false && $failureResponse < $sessionDestroy,
    'Failure handling must occur without destroying the Admin session'
);
initializationContractAssert(
    $alreadyInitialized !== false && $alreadyInitialized < $sessionDestroy,
    'Already-initialized response must occur without destroying the Admin session'
);
initializationContractAssert(
    strpos($endpoint, 'initializeRespond(201', $sessionDestroy) > $sessionDestroy,
    'Admin session must be destroyed immediately before successful response'
);
initializationContractAssert(
    str_contains($endpoint, 'InitializationErrorResponse::forException($e)')
        && str_contains($endpoint, "initializeRespond(\$failure['http_status'], \$failure['payload'])"),
    'Bootstrap failures must use the safe initialization error response mapping'
);
initializationContractAssert(
    str_contains($endpoint, "initializeRespond(409, ['success' => false, 'message' => 'FreeTV has already been initialized'])")
        && str_contains($endpoint, "initializeRespond(201, ['success' => true, 'message' => 'FreeTV library initialized'])"),
    'Already-initialized and successful initialization response contracts changed'
);

fwrite(STDOUT, "InitializationBootstrapContractTest passed\n");
