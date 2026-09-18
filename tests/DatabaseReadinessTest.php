<?php

declare(strict_types=1);

require_once __DIR__ . '/../public/api/admin/MariaDbError.php';
require_once __DIR__ . '/../public/api/admin/SchemaBootstrapper.php';
require_once __DIR__ . '/../public/api/admin/DatabaseReadiness.php';

use FreeTV\Admin\DatabaseReadiness;
use FreeTV\Admin\SchemaBootstrapper;

final class ReadinessSchema
{
    public function __construct(private array $tables)
    {
    }

    public function hasTable(string $table): bool
    {
        return in_array($table, $this->tables, true);
    }
}

final class ReadinessUsers
{
    public function __construct(private bool $exists)
    {
    }

    public function exists(): bool
    {
        return $this->exists;
    }
}

final class ReadinessConnection
{
    public function __construct(private array $tables, private bool $hasUsers)
    {
    }

    public function getPdo(): object
    {
        return new stdClass();
    }

    public function getSchemaBuilder(): ReadinessSchema
    {
        return new ReadinessSchema($this->tables);
    }

    public function table(string $table): ReadinessUsers
    {
        if ($table !== 'users') {
            throw new RuntimeException('Unexpected table query');
        }
        return new ReadinessUsers($this->hasUsers);
    }
}

function readinessDriverError(int $code): PDOException
{
    $exception = new PDOException('injected database failure');
    $exception->errorInfo = ['HY000', $code, 'injected database failure'];
    return $exception;
}

function readinessTestAssertSame(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message . '\nExpected: ' . var_export($expected, true)
            . '\nActual: ' . var_export($actual, true));
    }
}

$probeCalls = 0;
$ready = (new DatabaseReadiness(
    static fn() => new ReadinessConnection(SchemaBootstrapper::REQUIRED_TABLES, true),
    static function () use (&$probeCalls): string {
        $probeCalls++;
        return 'create_database';
    }
))->check();
readinessTestAssertSame('ready', $ready['status'], 'Initialized installation must be ready');
readinessTestAssertSame(0, $probeCalls, 'Ready fast path must not run capability probing');

$probeCalls = 0;
$missing = (new DatabaseReadiness(
    static function () {
        throw readinessDriverError(1049);
    },
    static function () use (&$probeCalls): string {
        $probeCalls++;
        return 'create_database';
    }
))->check();
readinessTestAssertSame('database_missing', $missing['status'], 'Error 1049 must mean configured database missing');
readinessTestAssertSame(1, $probeCalls, 'Missing database must run capability probing');

foreach ([1044, 1045, 2002] as $code) {
    $probeCalls = 0;
    try {
        (new DatabaseReadiness(
            static function () use ($code) {
                throw readinessDriverError($code);
            },
            static function () use (&$probeCalls): string {
                $probeCalls++;
                return 'create_database';
            }
        ))->check();
        throw new RuntimeException("Expected driver error {$code}");
    } catch (PDOException $exception) {
        readinessTestAssertSame($code, $exception->errorInfo[1], 'Unrelated connection error was changed');
        readinessTestAssertSame(0, $probeCalls, 'Unrelated connection error must not run probing');
    }
}

$incomplete = (new DatabaseReadiness(
    static fn() => new ReadinessConnection(['users'], false),
    static fn(): string => 'existing_database'
))->check();
readinessTestAssertSame('schema_missing', $incomplete['status'], 'Incomplete target must report schema missing');
readinessTestAssertSame('existing_database', $incomplete['database_mode'], 'Capability mode must be preserved');

fwrite(STDOUT, "DatabaseReadinessTest passed\n");
