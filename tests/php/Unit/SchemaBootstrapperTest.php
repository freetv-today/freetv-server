<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../public/api/admin/DatabaseCapabilityProbe.php';
require_once __DIR__ . '/../../../public/api/admin/MariaDbError.php';
require_once __DIR__ . '/../../../public/api/admin/DatabaseIdentifier.php';
require_once __DIR__ . '/../../../public/api/admin/SqlPackageExecutor.php';
require_once __DIR__ . '/../../../public/api/admin/SchemaBootstrapper.php';

use FreeTV\Admin\DatabaseCapabilityProbe;
use FreeTV\Admin\DatabasePermissionsInsufficientException;
use FreeTV\Admin\SchemaBootstrapper;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PermanentSchemaState
{
    public function __construct(private PermanentSchemaConnection $connection) {}
    public function hasTable(string $table): bool
    {
        return in_array($table, $this->connection->tables, true);
    }
}

final class PermanentSchemaUsers
{
    public function __construct(private bool $exists) {}
    public function exists(): bool { return $this->exists; }
}

final class PermanentSchemaConnection
{
    public array $operations = [];
    public ?int $failAt = null;
    private int $statementCount = 0;

    public function __construct(public array $tables = [], public bool $hasUsers = false) {}
    public function getPdo(): object { return new stdClass(); }
    public function getSchemaBuilder(): PermanentSchemaState { return new PermanentSchemaState($this); }
    public function table(string $table): PermanentSchemaUsers
    {
        return new PermanentSchemaUsers($table === 'users' && $this->hasUsers);
    }
    public function unprepared(string $sql): bool
    {
        $this->operations[] = $sql;
        $this->statementCount++;
        if ($this->statementCount === $this->failAt) {
            throw new RuntimeException('injected schema statement failure');
        }
        if (preg_match('/^CREATE TABLE IF NOT EXISTS\s+`?([a-z_]+)`?/i', $sql, $matches) === 1
            && !in_array($matches[1], $this->tables, true)) {
            $this->tables[] = $matches[1];
        }
        return true;
    }
}

final class PermanentSchemaServer
{
    public array $operations = [];
    public function statement(string $sql): bool
    {
        $this->operations[] = $sql;
        return true;
    }
}

final class SchemaBootstrapperTest extends TestCase
{
    private string $schemaPath;

    protected function setUp(): void
    {
        $this->schemaPath = dirname(__DIR__, 3) . '/sql/freetv_mariadb_schema-tables-only.sql';
    }

    public function testCompleteSchemaReturnsPresentWithoutDdlOrProbe(): void
    {
        $connection = new PermanentSchemaConnection(SchemaBootstrapper::REQUIRED_TABLES);
        $server = new PermanentSchemaServer();
        $probes = 0;
        [$actual, $state] = $this->bootstrapper($server, fn() => $connection, function () use (&$probes) {
            $probes++;
            return DatabaseCapabilityProbe::MODE_EXISTING_DATABASE;
        })->prepare();

        self::assertSame($connection, $actual);
        self::assertSame(SchemaBootstrapper::SCHEMA_PRESENT, $state);
        self::assertSame([], $connection->operations);
        self::assertSame([], $server->operations);
        self::assertSame(0, $probes);
    }

    public function testExistingUserReturnsAlreadyInitializedWithoutDdl(): void
    {
        $connection = new PermanentSchemaConnection(['users'], true);
        $server = new PermanentSchemaServer();
        [, $state] = $this->bootstrapper($server, fn() => $connection)->prepare();
        self::assertSame(SchemaBootstrapper::ALREADY_INITIALIZED, $state);
        self::assertSame([], $connection->operations);
        self::assertSame([], $server->operations);
    }

    public function testMissingDatabaseIsQuotedCreatedAndCanonicalSchemaInstalled(): void
    {
        $server = new PermanentSchemaServer();
        $connection = new PermanentSchemaConnection();
        $calls = 0;
        [$actual, $state] = $this->bootstrapper(
            $server,
            function () use (&$calls, $connection) {
                if ($calls++ === 0) {
                    throw self::unknownDatabase();
                }
                return $connection;
            },
            static fn(): string => DatabaseCapabilityProbe::MODE_CREATE_DATABASE,
            'my_freetv-2'
        )->prepare();

        self::assertSame($connection, $actual);
        self::assertSame(SchemaBootstrapper::SCHEMA_INSTALLED, $state);
        self::assertSame(
            'CREATE DATABASE `my_freetv-2` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci',
            $server->operations[0]
        );
        self::assertSame([], array_diff(SchemaBootstrapper::REQUIRED_TABLES, $connection->tables));
    }

    public function testMissingDatabaseWithoutCreatePermissionFailsBeforeDdl(): void
    {
        $server = new PermanentSchemaServer();
        $bootstrapper = $this->bootstrapper(
            $server,
            static function (): never { throw self::unknownDatabase(); },
            static fn(): string => DatabaseCapabilityProbe::MODE_EXISTING_DATABASE
        );
        $this->expectException(DatabasePermissionsInsufficientException::class);
        try {
            $bootstrapper->prepare();
        } finally {
            self::assertSame([], $server->operations);
        }
    }

    public function testExistingDatabaseMissingTablesInstallsSchemaWithoutCreateDatabase(): void
    {
        $server = new PermanentSchemaServer();
        $connection = new PermanentSchemaConnection(['users']);
        [, $state] = $this->bootstrapper($server, fn() => $connection)->prepare();
        self::assertSame(SchemaBootstrapper::SCHEMA_INSTALLED, $state);
        self::assertSame([], $server->operations);
        self::assertSame([], array_diff(SchemaBootstrapper::REQUIRED_TABLES, $connection->tables));
    }

    public function testMissingCanonicalSchemaFailsBeforeChanges(): void
    {
        $server = new PermanentSchemaServer();
        $connection = new PermanentSchemaConnection();
        $bootstrapper = new SchemaBootstrapper(
            $server,
            fn() => $connection,
            static fn(): string => DatabaseCapabilityProbe::MODE_EXISTING_DATABASE,
            'freetv',
            $this->schemaPath . '.missing'
        );
        $this->expectExceptionMessage('Canonical FreeTV schema package is missing or unreadable');
        try {
            $bootstrapper->prepare();
        } finally {
            self::assertSame([], $connection->operations);
            self::assertSame([], $server->operations);
        }
    }

    #[DataProvider('unsafeDatabaseNameProvider')]
    public function testUnsafeDatabaseIdentifierIsRejectedBeforeDdl(string $name): void
    {
        $server = new PermanentSchemaServer();
        $bootstrapper = $this->bootstrapper(
            $server,
            static function (): never { throw self::unknownDatabase(); },
            static fn(): string => DatabaseCapabilityProbe::MODE_CREATE_DATABASE,
            $name
        );
        $this->expectException(InvalidArgumentException::class);
        try {
            $bootstrapper->prepare();
        } finally {
            self::assertSame([], $server->operations);
        }
    }

    public static function unsafeDatabaseNameProvider(): array
    {
        return [
            'space' => ['bad name'], 'backtick' => ['bad`name'], 'dot' => ['db.name'],
            'path' => ['../freetv'], 'empty' => [''], 'too long' => [str_repeat('a', 65)],
        ];
    }

    public function testSchemaStatementFailureDoesNotReportSuccessAndCanBeRetried(): void
    {
        $connection = new PermanentSchemaConnection();
        $connection->failAt = 3;
        $bootstrapper = $this->bootstrapper(new PermanentSchemaServer(), fn() => $connection);
        try {
            $bootstrapper->prepare();
            self::fail('Expected schema execution failure');
        } catch (RuntimeException $exception) {
            self::assertSame('injected schema statement failure', $exception->getMessage());
        }
        self::assertNotSame([], $connection->operations);
        self::assertNotSame([], array_diff(SchemaBootstrapper::REQUIRED_TABLES, $connection->tables));
        $connection->failAt = null;
        [, $state] = $bootstrapper->prepare();
        self::assertSame(SchemaBootstrapper::SCHEMA_INSTALLED, $state);
        self::assertSame([], array_diff(SchemaBootstrapper::REQUIRED_TABLES, $connection->tables));
    }

    #[DataProvider('initializedStateProvider')]
    public function testIsAlreadyInitializedDistinguishesStates(string $state, bool $expected): void
    {
        $factory = match ($state) {
            'initialized' => static fn() => new PermanentSchemaConnection(['users'], true),
            'uninitialized' => static fn() => new PermanentSchemaConnection(['users'], false),
            default => static function (): never { throw self::unknownDatabase(); },
        };
        self::assertSame(
            $expected,
            $this->bootstrapper(new PermanentSchemaServer(), $factory)->isAlreadyInitialized()
        );
    }

    public static function initializedStateProvider(): array
    {
        return [
            'initialized' => ['initialized', true],
            'uninitialized' => ['uninitialized', false],
            'unknown database' => ['missing', false],
        ];
    }

    private function bootstrapper(
        PermanentSchemaServer $server,
        callable $factory,
        ?callable $probe = null,
        string $database = 'freetv'
    ): SchemaBootstrapper {
        return new SchemaBootstrapper(
            $server,
            $factory,
            $probe ?? static fn(): string => DatabaseCapabilityProbe::MODE_EXISTING_DATABASE,
            $database,
            $this->schemaPath
        );
    }

    private static function unknownDatabase(): PDOException
    {
        $exception = new PDOException('Unknown database');
        $exception->errorInfo = ['HY000', 1049, 'Unknown database'];
        return $exception;
    }
}
