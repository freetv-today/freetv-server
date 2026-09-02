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
        && str_contains($endpoint, '))->fresh($username, $password)'),
    'Start Fresh must delegate application orchestration to Bootstrapper'
);
$failureResponse = strpos($endpoint, "initializeRespond(500, ['success' => false");
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

fwrite(STDOUT, "InitializationBootstrapContractTest passed\n");
