<?php

declare(strict_types=1);

require_once __DIR__ . '/../public/api/admin/DatabaseCapabilityProbe.php';

use FreeTV\Admin\DatabaseCapabilityProbe;
use FreeTV\Admin\DatabasePermissionsInsufficientException;

final class ReadinessFakeConnection
{
    public array $operations = [];
    private array $failures;
    private string $selectedValue;

    public function __construct(array $failures = [], string $selectedValue = 'freetv-readiness-write-read-ok')
    {
        $this->failures = $failures;
        $this->selectedValue = $selectedValue;
    }

    public function getPdo(): object
    {
        $this->operations[] = 'CONNECT';
        $this->failIfConfigured('CONNECT');
        return new stdClass();
    }

    public function statement(string $sql, array $bindings = []): bool
    {
        $this->operations[] = $sql;
        $this->failIfConfigured($sql);
        return true;
    }

    public function selectOne(string $sql): object
    {
        $this->operations[] = $sql;
        $this->failIfConfigured($sql);
        return (object) ['probe_value' => $this->selectedValue];
    }

    private function failIfConfigured(string $operation): void
    {
        foreach ($this->failures as $prefix => $exception) {
            if (str_starts_with($operation, $prefix)) {
                throw $exception;
            }
        }
    }
}

function readinessAssertSame(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message . "\nExpected: " . var_export($expected, true)
            . "\nActual: " . var_export($actual, true));
    }
}

function readinessAssertTrue(bool $actual, string $message): void
{
    readinessAssertSame(true, $actual, $message);
}

function readinessPermissionError(int $driverCode = 1044): PDOException
{
    $exception = new PDOException('permission denied');
    $exception->errorInfo = ['42000', $driverCode, 'permission denied'];
    return $exception;
}

function readinessNames(array $names): callable
{
    return static function () use (&$names): string {
        if ($names === []) {
            throw new RuntimeException('Test name sequence exhausted');
        }
        return array_shift($names);
    };
}

function readinessHasOperation(ReadinessFakeConnection $connection, string $prefix): bool
{
    return count(array_filter(
        $connection->operations,
        static fn(string $operation): bool => str_starts_with($operation, $prefix)
    )) > 0;
}

$databaseName = 'freetv_readiness_aaaaaaaaaaaa';
$tableName = 'freetv_readiness_bbbbbbbbbbbb';

// Full provisioning capability.
$configured = new ReadinessFakeConnection();
$isolated = new ReadinessFakeConnection();
$factoryDatabase = null;
$probe = new DatabaseCapabilityProbe(
    $configured,
    static function (string $database) use ($isolated, &$factoryDatabase): ReadinessFakeConnection {
        $factoryDatabase = $database;
        return $isolated;
    },
    readinessNames([$databaseName])
);
readinessAssertSame(DatabaseCapabilityProbe::MODE_CREATE_DATABASE, $probe->detect(),
    'Full probe should classify create_database mode');
readinessAssertSame($databaseName, $factoryDatabase, 'Probe did not connect to the disposable database');
readinessAssertTrue(readinessHasOperation($configured, "CREATE DATABASE `{$databaseName}`"),
    'Disposable database was not created');
readinessAssertTrue(readinessHasOperation($isolated, "CREATE TABLE `{$databaseName}`"),
    'Disposable database table was not created');
readinessAssertTrue(readinessHasOperation($isolated, 'INSERT INTO'), 'Known row was not inserted');
readinessAssertTrue(readinessHasOperation($isolated, 'SELECT `probe_value`'), 'Known row was not read');
readinessAssertTrue(readinessHasOperation($configured, "DROP DATABASE `{$databaseName}`"),
    'Disposable database was not removed');

// Hosted/pre-created database fallback.
$configured = new ReadinessFakeConnection(['CREATE DATABASE' => readinessPermissionError()]);
$probe = new DatabaseCapabilityProbe(
    $configured,
    static fn(string $_database): never => throw new RuntimeException(
        'Isolated connection must not be used for fallback'
    ),
    readinessNames([$databaseName, $tableName])
);
readinessAssertSame(DatabaseCapabilityProbe::MODE_EXISTING_DATABASE, $probe->detect(),
    'Permission-denied CREATE DATABASE should use existing_database fallback');
readinessAssertTrue(readinessHasOperation($configured, "CREATE TABLE `{$tableName}`"),
    'Fallback table was not created');
readinessAssertTrue(readinessHasOperation($configured, "DROP TABLE `{$tableName}`"),
    'Fallback table was not removed');

// Fallback failure has a dedicated permissions result and still cleans its table.
$configured = new ReadinessFakeConnection([
    'CREATE DATABASE' => readinessPermissionError(),
    'INSERT INTO' => new RuntimeException('injected fallback insert failure'),
]);
$probe = new DatabaseCapabilityProbe(
    $configured,
    null,
    readinessNames([$databaseName, $tableName]),
    static function (): void {}
);
try {
    $probe->detect();
    throw new RuntimeException('Expected fallback permissions failure');
} catch (DatabasePermissionsInsufficientException) {
    readinessAssertTrue(readinessHasOperation($configured, "DROP TABLE `{$tableName}`"),
        'Fallback table was not cleaned after an intermediate failure');
}

// A non-permission CREATE DATABASE error must never masquerade as hosted fallback.
$configured = new ReadinessFakeConnection([
    'CREATE DATABASE' => new RuntimeException('injected storage failure'),
]);
$probe = new DatabaseCapabilityProbe($configured, null, readinessNames([$databaseName, $tableName]));
try {
    $probe->detect();
    throw new RuntimeException('Expected non-permission database failure');
} catch (RuntimeException $exception) {
    readinessAssertSame('injected storage failure', $exception->getMessage(),
        'Non-permission CREATE DATABASE failure was changed');
    readinessAssertSame(false, readinessHasOperation($configured, 'CREATE TABLE'),
        'Non-permission failure incorrectly used hosted fallback');
}

// An intermediate failure in the disposable database must still drop that database.
$configured = new ReadinessFakeConnection();
$isolated = new ReadinessFakeConnection([
    'INSERT INTO' => new RuntimeException('injected disposable insert failure'),
]);
$probe = new DatabaseCapabilityProbe(
    $configured,
    static fn(string $_database): ReadinessFakeConnection => $isolated,
    readinessNames([$databaseName])
);
try {
    $probe->detect();
    throw new RuntimeException('Expected disposable database probe failure');
} catch (RuntimeException $exception) {
    readinessAssertSame('injected disposable insert failure', $exception->getMessage(),
        'Unexpected disposable database failure');
    readinessAssertTrue(readinessHasOperation($configured, "DROP DATABASE `{$databaseName}`"),
        'Disposable database was not cleaned after an intermediate failure');
}

// Exact read-back mismatch also fails and cleans the disposable database.
$configured = new ReadinessFakeConnection();
$isolated = new ReadinessFakeConnection([], 'wrong value');
$probe = new DatabaseCapabilityProbe(
    $configured,
    static fn(string $_database): ReadinessFakeConnection => $isolated,
    readinessNames([$databaseName])
);
try {
    $probe->detect();
    throw new RuntimeException('Expected read-back verification failure');
} catch (RuntimeException $exception) {
    readinessAssertTrue(str_contains($exception->getMessage(), 'exact inserted value'),
        'Unexpected read-back verification error');
    readinessAssertTrue(readinessHasOperation($configured, "DROP DATABASE `{$databaseName}`"),
        'Read-back mismatch did not clean the disposable database');
}

// Unsafe generated names are rejected before any statement, especially DROP.
$configured = new ReadinessFakeConnection();
$probe = new DatabaseCapabilityProbe($configured, null, readinessNames(['freetv_readiness_bad;DROP DATABASE prod']));
try {
    $probe->detect();
    throw new RuntimeException('Expected unsafe generated-name failure');
} catch (RuntimeException $exception) {
    readinessAssertTrue(str_contains($exception->getMessage(), 'unsafe'), 'Unexpected unsafe-name error');
    readinessAssertSame([], $configured->operations, 'Unsafe name reached the database connection');
}

// Even the separately generated fallback table name must satisfy the exact contract.
$configured = new ReadinessFakeConnection(['CREATE DATABASE' => readinessPermissionError()]);
$probe = new DatabaseCapabilityProbe(
    $configured,
    null,
    readinessNames([$databaseName, 'users'])
);
try {
    $probe->detect();
    throw new RuntimeException('Expected unsafe fallback-name failure');
} catch (RuntimeException $exception) {
    readinessAssertTrue(str_contains($exception->getMessage(), 'unsafe'), 'Unexpected fallback unsafe-name error');
    readinessAssertSame(false, readinessHasOperation($configured, 'DROP'),
        'Unsafe fallback name was used in a DROP operation');
}

fwrite(STDOUT, "DatabaseCapabilityProbeTest passed\n");
