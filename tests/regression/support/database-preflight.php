<?php

declare(strict_types=1);

use FreeTV\Admin\Database;
use FreeTV\Admin\SqlPackageExecutor;

const FREETV_TEST_DATABASE = 'freetv_test';

$appRoot = dirname(__DIR__, 3);

require_once $appRoot . '/vendor/autoload.php';
require_once $appRoot . '/public/api/admin/Database.php';
require_once $appRoot . '/public/api/admin/SqlPackageExecutor.php';

/** @return list<string> */
function canonicalTableNames(string $schemaPath): array
{
    if (!is_file($schemaPath) || !is_readable($schemaPath)) {
        throw new RuntimeException('Canonical FreeTV schema package is missing or unreadable');
    }

    $sql = file_get_contents($schemaPath);
    if ($sql === false) {
        throw new RuntimeException('Canonical FreeTV schema package could not be read');
    }

    $tables = [];
    foreach ((new SqlPackageExecutor())->statements($sql) as $statement) {
        if (preg_match(
            '/^CREATE\s+TABLE\s+IF\s+NOT\s+EXISTS\s+`?([A-Za-z0-9_]+)`?\s*\(/i',
            $statement,
            $matches
        ) === 1) {
            $tables[] = $matches[1];
        }
    }

    if ($tables === []) {
        throw new RuntimeException('Canonical FreeTV schema package does not define any tables');
    }

    return array_values(array_unique($tables));
}

function pass(string $message): void
{
    fwrite(STDOUT, "PASS  {$message}\n");
}

function info(string $message): void
{
    fwrite(STDOUT, "INFO  {$message}\n");
}

function fail(string $message): never
{
    fwrite(STDOUT, "FAIL  {$message}\n\nPreflight failed.\n");
    exit(1);
}

fwrite(STDOUT, "FreeTV Admin — Database Preflight\n\n");

try {
    if (!Database::hasExplicitConfig()) {
        fail('Database configuration is incomplete; DB_HOST, DB_NAME, and DB_USER are required');
    }
    pass('Database configuration found');

    $sourceDatabase = Database::configuredDatabaseName();
    if ($sourceDatabase === FREETV_TEST_DATABASE) {
        fail('Configured source database conflicts with the proposed test database: ' . FREETV_TEST_DATABASE);
    }

    $connection = Database::createConfiguredConnection();
    $connection->getPdo();
    pass('MariaDB connection established');

    $selected = $connection->selectOne('SELECT DATABASE() AS database_name');
    $selectedDatabase = is_object($selected) ? ($selected->database_name ?? null) : null;
    if (!is_string($selectedDatabase) || $selectedDatabase !== $sourceDatabase) {
        fail('The active MariaDB database does not match the configured source database');
    }
    pass('Source database accessible: ' . $sourceDatabase);

    $requiredTables = canonicalTableNames($appRoot . '/sql/freetv_mariadb_schema-tables-only.sql');
    $rows = $connection->select(
        'SELECT TABLE_NAME AS table_name FROM information_schema.TABLES'
            . ' WHERE TABLE_SCHEMA = ? AND TABLE_TYPE = ?',
        [$sourceDatabase, 'BASE TABLE']
    );
    $presentTables = array_map(
        static fn(object $row): string => (string) $row->table_name,
        $rows
    );
    $missingTables = array_values(array_diff($requiredTables, $presentTables));
    if ($missingTables !== []) {
        fail('Required tables missing: ' . implode(', ', $missingTables));
    }
    pass('Required tables present: ' . implode(', ', $requiredTables));

    $testDatabase = $connection->selectOne(
        'SELECT SCHEMA_NAME AS schema_name FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = ? LIMIT 1',
        [FREETV_TEST_DATABASE]
    );
    if ($testDatabase === null) {
        info('Test database: ' . FREETV_TEST_DATABASE . ' (not visible to the configured account)');
    } else {
        info('Test database: ' . FREETV_TEST_DATABASE . ' (exists and is visible to the configured account)');
    }

    fwrite(STDOUT, "\nPreflight successful.\n");
} catch (Throwable $exception) {
    fail($exception->getMessage());
}
