<?php

declare(strict_types=1);

require_once __DIR__ . '/../public/api/admin/DatabaseCapabilityProbe.php';
require_once __DIR__ . '/../public/api/admin/MariaDbError.php';
require_once __DIR__ . '/../public/api/admin/DatabaseIdentifier.php';
require_once __DIR__ . '/../public/api/admin/SqlPackageExecutor.php';
require_once __DIR__ . '/../public/api/admin/SchemaBootstrapper.php';

use FreeTV\Admin\DatabaseCapabilityProbe;
use FreeTV\Admin\DatabaseIdentifier;
use FreeTV\Admin\SchemaBootstrapper;

final class BootstrapSchema
{
    public function __construct(private BootstrapConnection $connection)
    {
    }

    public function hasTable(string $table): bool
    {
        return in_array($table, $this->connection->tables, true);
    }
}

final class BootstrapUsers
{
    public function __construct(private bool $hasUsers)
    {
    }

    public function exists(): bool
    {
        return $this->hasUsers;
    }
}

final class BootstrapConnection
{
    public array $tables;
    public array $operations = [];
    public bool $hasUsers;
    public ?int $failAtStatement = null;
    private int $statementCount = 0;

    public function __construct(array $tables = [], bool $hasUsers = false)
    {
        $this->tables = $tables;
        $this->hasUsers = $hasUsers;
    }

    public function getPdo(): object
    {
        return new stdClass();
    }

    public function getSchemaBuilder(): BootstrapSchema
    {
        return new BootstrapSchema($this);
    }

    public function table(string $table): BootstrapUsers
    {
        return new BootstrapUsers($table === 'users' && $this->hasUsers);
    }

    public function unprepared(string $sql): bool
    {
        $this->operations[] = $sql;
        $this->statementCount++;
        if ($this->failAtStatement === $this->statementCount) {
            throw new RuntimeException('injected partial bootstrap failure');
        }
        if (preg_match('/^CREATE TABLE IF NOT EXISTS\s+`?([a-z_]+)`?/i', $sql, $matches) === 1
            && !in_array($matches[1], $this->tables, true)) {
            $this->tables[] = $matches[1];
        }
        return true;
    }
}

final class BootstrapServerConnection
{
    public array $operations = [];

    public function statement(string $sql): bool
    {
        $this->operations[] = $sql;
        return true;
    }
}

function bootstrapUnknownDatabase(): PDOException
{
    $exception = new PDOException('Unknown database');
    $exception->errorInfo = ['HY000', 1049, 'Unknown database'];
    return $exception;
}

function bootstrapAssertSame(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message . '\nExpected: ' . var_export($expected, true)
            . '\nActual: ' . var_export($actual, true));
    }
}

$schemaPath = __DIR__ . '/../sql/freetv_mariadb_schema-tables-only.sql';
$server = new BootstrapServerConnection();
$target = new BootstrapConnection();
$factoryCalls = 0;
$capabilityCalls = 0;
[$createdConnection, $createdState] = (new SchemaBootstrapper(
    $server,
    static function () use (&$factoryCalls, $target) {
        if ($factoryCalls++ === 0) {
            throw bootstrapUnknownDatabase();
        }
        return $target;
    },
    static function () use (&$capabilityCalls): string {
        $capabilityCalls++;
        return DatabaseCapabilityProbe::MODE_CREATE_DATABASE;
    },
    'my_freetv-2',
    $schemaPath
))->prepare();
bootstrapAssertSame($target, $createdConnection, 'Created database path returned wrong connection');
bootstrapAssertSame(SchemaBootstrapper::SCHEMA_INSTALLED, $createdState, 'Created database must install schema');
bootstrapAssertSame(
    'CREATE DATABASE `my_freetv-2` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci',
    $server->operations[0] ?? null,
    'Bootstrap must use the quoted configured DB_NAME'
);
bootstrapAssertSame(1, $capabilityCalls, 'Missing database must verify create capability');
bootstrapAssertSame([], array_diff(SchemaBootstrapper::REQUIRED_TABLES, $target->tables),
    'Tables-only package did not install required schema');

$server = new BootstrapServerConnection();
$target = new BootstrapConnection();
$capabilityCalls = 0;
[, $existingState] = (new SchemaBootstrapper(
    $server,
    static fn() => $target,
    static function () use (&$capabilityCalls): string {
        $capabilityCalls++;
        return DatabaseCapabilityProbe::MODE_EXISTING_DATABASE;
    },
    'assigned_freetv',
    $schemaPath
))->prepare();
bootstrapAssertSame(SchemaBootstrapper::SCHEMA_INSTALLED, $existingState,
    'Existing empty database must install schema');
bootstrapAssertSame([], $server->operations, 'Existing database path must not execute CREATE DATABASE');
bootstrapAssertSame(0, $capabilityCalls, 'Existing database bootstrap must not probe CREATE DATABASE');

$present = new BootstrapConnection(SchemaBootstrapper::REQUIRED_TABLES);
[, $presentState] = (new SchemaBootstrapper(
    new BootstrapServerConnection(),
    static fn() => $present,
    static fn(): string => DatabaseCapabilityProbe::MODE_EXISTING_DATABASE,
    'freetv',
    $schemaPath
))->prepare();
bootstrapAssertSame(SchemaBootstrapper::SCHEMA_PRESENT, $presentState,
    'Complete schema must not be reinstalled');
bootstrapAssertSame([], $present->operations, 'Complete schema unexpectedly executed SQL package');

$initialized = new BootstrapConnection(['users'], true);
[, $initializedState] = (new SchemaBootstrapper(
    new BootstrapServerConnection(),
    static fn() => $initialized,
    static fn(): string => DatabaseCapabilityProbe::MODE_EXISTING_DATABASE,
    'freetv',
    $schemaPath
))->prepare();
bootstrapAssertSame(SchemaBootstrapper::ALREADY_INITIALIZED, $initializedState,
    'Existing user must protect against reinitialization');
bootstrapAssertSame([], $initialized->operations, 'Reinitialization protection modified incomplete schema');

$missingPackageTarget = new BootstrapConnection();
try {
    (new SchemaBootstrapper(
        new BootstrapServerConnection(),
        static fn() => $missingPackageTarget,
        static fn(): string => DatabaseCapabilityProbe::MODE_EXISTING_DATABASE,
        'freetv',
        __DIR__ . '/does-not-exist.sql'
    ))->prepare();
    throw new RuntimeException('Expected missing schema package failure');
} catch (RuntimeException $exception) {
    bootstrapAssertSame('Canonical FreeTV schema package is missing or unreadable', $exception->getMessage(),
        'Missing package did not fail clearly');
    bootstrapAssertSame([], $missingPackageTarget->operations, 'Missing package caused partial schema changes');
}

$partial = new BootstrapConnection();
$partial->failAtStatement = 3;
$retryBootstrapper = new SchemaBootstrapper(
    new BootstrapServerConnection(),
    static fn() => $partial,
    static fn(): string => DatabaseCapabilityProbe::MODE_EXISTING_DATABASE,
    'freetv',
    $schemaPath
);
try {
    $retryBootstrapper->prepare();
    throw new RuntimeException('Expected partial bootstrap failure');
} catch (RuntimeException $exception) {
    bootstrapAssertSame('injected partial bootstrap failure', $exception->getMessage(),
        'Unexpected partial bootstrap error');
}
$partial->failAtStatement = null;
[, $retryState] = $retryBootstrapper->prepare();
bootstrapAssertSame(SchemaBootstrapper::SCHEMA_INSTALLED, $retryState,
    'Idempotent package retry did not complete');
bootstrapAssertSame([], array_diff(SchemaBootstrapper::REQUIRED_TABLES, $partial->tables),
    'Retry did not leave complete required schema');

foreach (['bad name', 'bad`name', 'db.name', '../freetv', '', str_repeat('a', 65)] as $unsafeName) {
    $unsafeServer = new BootstrapServerConnection();
    try {
        (new SchemaBootstrapper(
            $unsafeServer,
            static function () {
                throw bootstrapUnknownDatabase();
            },
            static fn(): string => DatabaseCapabilityProbe::MODE_CREATE_DATABASE,
            $unsafeName,
            $schemaPath
        ))->prepare();
        throw new RuntimeException('Expected unsafe DB_NAME failure');
    } catch (InvalidArgumentException $exception) {
        bootstrapAssertSame([], $unsafeServer->operations, 'Unsafe DB_NAME reached database DDL');
    }
}
bootstrapAssertSame('`123_freetv$-test`', DatabaseIdentifier::quote('123_freetv$-test'),
    'Reasonable MariaDB identifier was rejected');

fwrite(STDOUT, "SchemaBootstrapperTest passed\n");
