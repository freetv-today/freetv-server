<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../public/api/admin/MariaDbError.php';
require_once __DIR__ . '/../../../public/api/admin/SchemaBootstrapper.php';
require_once __DIR__ . '/../../../public/api/admin/DatabaseReadiness.php';

use FreeTV\Admin\DatabaseReadiness;
use FreeTV\Admin\SchemaBootstrapper;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PermanentReadinessSchema
{
    public function __construct(private array $tables) {}
    public function hasTable(string $table): bool { return in_array($table, $this->tables, true); }
}

final class PermanentReadinessUsers
{
    public function __construct(private bool $exists) {}
    public function exists(): bool { return $this->exists; }
}

final class PermanentReadinessConnection
{
    public function __construct(private array $tables, private bool $hasUsers) {}
    public function getPdo(): object { return new stdClass(); }
    public function getSchemaBuilder(): PermanentReadinessSchema
    {
        return new PermanentReadinessSchema($this->tables);
    }
    public function table(string $table): PermanentReadinessUsers
    {
        if ($table !== 'users') {
            throw new RuntimeException('Unexpected table query');
        }
        return new PermanentReadinessUsers($this->hasUsers);
    }
}

final class DatabaseReadinessTest extends TestCase
{
    public function testCompleteInitializedSchemaIsReadyWithoutCapabilityProbe(): void
    {
        $probeCalls = 0;
        $result = (new DatabaseReadiness(
            static fn() => new PermanentReadinessConnection(SchemaBootstrapper::REQUIRED_TABLES, true),
            static function () use (&$probeCalls): string {
                $probeCalls++;
                return 'create_database';
            }
        ))->check();

        self::assertSame(['status' => 'ready', 'http_status' => 200], $result);
        self::assertSame(0, $probeCalls);
    }

    public function testUnknownDatabaseReportsMissingWithCapabilityMode(): void
    {
        $probeCalls = 0;
        $result = (new DatabaseReadiness(
            static function (): never { throw self::driverError(1049); },
            static function () use (&$probeCalls): string {
                $probeCalls++;
                return 'create_database';
            }
        ))->check();

        self::assertSame([
            'status' => 'database_missing',
            'http_status' => 503,
            'database_mode' => 'create_database',
        ], $result);
        self::assertSame(1, $probeCalls);
    }

    #[DataProvider('unrelatedConnectionErrorProvider')]
    public function testUnrelatedConnectionErrorsPropagateWithoutCapabilityProbe(int $code): void
    {
        $probeCalls = 0;
        try {
            (new DatabaseReadiness(
                static function () use ($code): never { throw self::driverError($code); },
                static function () use (&$probeCalls): string {
                    $probeCalls++;
                    return 'create_database';
                }
            ))->check();
            self::fail('Expected PDOException');
        } catch (PDOException $exception) {
            self::assertSame($code, $exception->errorInfo[1]);
        }
        self::assertSame(0, $probeCalls);
    }

    public static function unrelatedConnectionErrorProvider(): array
    {
        return ['access database' => [1044], 'authentication' => [1045], 'server unavailable' => [2002]];
    }

    public function testIncompleteSchemaReportsExactMissingTablesAndCapabilityMode(): void
    {
        $probeCalls = 0;
        $result = (new DatabaseReadiness(
            static fn() => new PermanentReadinessConnection(['users', 'playlists'], false),
            static function () use (&$probeCalls): string {
                $probeCalls++;
                return 'existing_database';
            }
        ))->check();

        self::assertSame([
            'status' => 'schema_missing',
            'http_status' => 503,
            'missing_tables' => ['playlist_shows', 'app_settings'],
            'database_mode' => 'existing_database',
        ], $result);
        self::assertSame(1, $probeCalls);
    }

    public function testCompleteSchemaWithoutUsersRequiresInitializationAndProbesCapability(): void
    {
        $probeCalls = 0;
        $result = (new DatabaseReadiness(
            static fn() => new PermanentReadinessConnection(SchemaBootstrapper::REQUIRED_TABLES, false),
            static function () use (&$probeCalls): string {
                $probeCalls++;
                return 'existing_database';
            }
        ))->check();

        self::assertSame([
            'status' => 'initialization_required',
            'http_status' => 200,
            'database_mode' => 'existing_database',
        ], $result);
        self::assertSame(1, $probeCalls);
    }

    private static function driverError(int $code): PDOException
    {
        $exception = new PDOException('injected database failure');
        $exception->errorInfo = ['HY000', $code, 'injected database failure'];
        return $exception;
    }
}
