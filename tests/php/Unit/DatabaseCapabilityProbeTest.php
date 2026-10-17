<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../public/api/admin/DatabaseCapabilityProbe.php';

use FreeTV\Admin\DatabaseCapabilityProbe;
use FreeTV\Admin\DatabasePermissionsInsufficientException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PermanentCapabilityConnection
{
    public array $operations = [];
    public function __construct(
        private array $failures = [],
        private string $selectedValue = 'freetv-readiness-write-read-ok',
        private array $falsePrefixes = []
    ) {}
    public function getPdo(): object
    {
        $this->operations[] = ['CONNECT', []];
        $this->fail('CONNECT');
        return new stdClass();
    }
    public function statement(string $sql, array $bindings = []): bool
    {
        $this->operations[] = [$sql, $bindings];
        $this->fail($sql);
        foreach ($this->falsePrefixes as $prefix) {
            if (str_starts_with($sql, $prefix)) { return false; }
        }
        return true;
    }
    public function selectOne(string $sql): object
    {
        $this->operations[] = [$sql, []];
        $this->fail($sql);
        return (object) ['probe_value' => $this->selectedValue];
    }
    private function fail(string $operation): void
    {
        foreach ($this->failures as $prefix => $exception) {
            if (str_starts_with($operation, $prefix)) { throw $exception; }
        }
    }
}

final class DatabaseCapabilityProbeTest extends TestCase
{
    private const DATABASE = 'freetv_readiness_aaaaaaaaaaaa';
    private const TABLE = 'freetv_readiness_bbbbbbbbbbbb';

    public function testCreateDatabaseCapabilityUsesOrderedQuotedWriteReadAndCleanupOperations(): void
    {
        $bootstrap = new PermanentCapabilityConnection();
        $isolated = new PermanentCapabilityConnection();
        $configuredCalls = 0;
        $factoryName = null;
        $probe = new DatabaseCapabilityProbe(
            $bootstrap,
            static function (string $name) use ($isolated, &$factoryName): PermanentCapabilityConnection {
                $factoryName = $name;
                return $isolated;
            },
            $this->names([self::DATABASE]),
            null,
            static function () use (&$configuredCalls): never {
                $configuredCalls++;
                throw new RuntimeException('Configured connection must not be used');
            }
        );

        self::assertSame(DatabaseCapabilityProbe::MODE_CREATE_DATABASE, $probe->detect());
        self::assertSame(self::DATABASE, $factoryName);
        self::assertSame(0, $configuredCalls);
        self::assertSame([
            ['CREATE DATABASE `' . self::DATABASE . '`', []],
            ['DROP DATABASE `' . self::DATABASE . '`', []],
        ], $bootstrap->operations);
        self::assertSame('CONNECT', $isolated->operations[0][0]);
        self::assertSame('CREATE TABLE `' . self::DATABASE . '` (`probe_value` VARCHAR(64) NOT NULL) ENGINE=InnoDB', $isolated->operations[1][0]);
        self::assertSame(['freetv-readiness-write-read-ok'], $isolated->operations[2][1]);
        self::assertStringStartsWith('SELECT `probe_value` FROM `' . self::DATABASE . '`', $isolated->operations[3][0]);
    }

    #[DataProvider('permissionCodeProvider')]
    public function testCreatePermissionErrorsUseConfiguredDatabaseFallback(int $code): void
    {
        $bootstrap = new PermanentCapabilityConnection(['CREATE DATABASE' => self::driverError($code)]);
        $configured = new PermanentCapabilityConnection();
        $configuredCalls = 0;
        $probe = new DatabaseCapabilityProbe(
            $bootstrap,
            static fn(string $name): never => throw new RuntimeException('Isolated factory used'),
            $this->names([self::DATABASE, self::TABLE]),
            null,
            static function () use ($configured, &$configuredCalls): PermanentCapabilityConnection {
                $configuredCalls++;
                return $configured;
            }
        );
        self::assertSame(DatabaseCapabilityProbe::MODE_EXISTING_DATABASE, $probe->detect());
        self::assertSame(1, $configuredCalls);
        self::assertSame([
            'CREATE TABLE `' . self::TABLE . '` (`probe_value` VARCHAR(64) NOT NULL) ENGINE=InnoDB',
            'INSERT INTO `' . self::TABLE . '` (`probe_value`) VALUES (?)',
            'SELECT `probe_value` FROM `' . self::TABLE . '` LIMIT 1',
            'DROP TABLE `' . self::TABLE . '`',
        ], array_column($configured->operations, 0));
    }

    public static function permissionCodeProvider(): array
    {
        return ['1044' => [1044], '1142' => [1142], '1227' => [1227]];
    }

    public function testUnrelatedCreateDatabaseFailurePropagatesWithoutFallback(): void
    {
        $failure = new RuntimeException('injected storage failure');
        $bootstrap = new PermanentCapabilityConnection(['CREATE DATABASE' => $failure]);
        $configuredCalls = 0;
        try {
            (new DatabaseCapabilityProbe(
                $bootstrap,
                null,
                $this->names([self::DATABASE]),
                null,
                static function () use (&$configuredCalls): never { $configuredCalls++; throw new RuntimeException(); }
            ))->detect();
            self::fail('Expected original failure');
        } catch (RuntimeException $exception) {
            self::assertSame($failure, $exception);
        }
        self::assertSame(0, $configuredCalls);
    }

    #[DataProvider('createdDatabaseFailureProvider')]
    public function testCreatedDatabaseWorkflowFailuresAttemptCleanup(string $prefix, Throwable $failure): void
    {
        $bootstrap = new PermanentCapabilityConnection();
        $isolated = new PermanentCapabilityConnection([$prefix => $failure]);
        try {
            (new DatabaseCapabilityProbe(
                $bootstrap,
                static fn(string $name) => $isolated,
                $this->names([self::DATABASE])
            ))->detect();
            self::fail('Expected workflow failure');
        } catch (Throwable $exception) {
            if ($failure instanceof PDOException) {
                self::assertInstanceOf(DatabasePermissionsInsufficientException::class, $exception);
                self::assertSame($failure, $exception->getPrevious());
            } else {
                self::assertSame($failure, $exception);
            }
        }
        self::assertContains('DROP DATABASE `' . self::DATABASE . '`', array_column($bootstrap->operations, 0));
    }

    public static function createdDatabaseFailureProvider(): array
    {
        return [
            'connection' => ['CONNECT', new RuntimeException('connection failure')],
            'table create permission' => ['CREATE TABLE', self::driverError(1142)],
            'insert' => ['INSERT INTO', new RuntimeException('insert failure')],
        ];
    }

    public function testIncorrectReadBackAttemptsDatabaseCleanup(): void
    {
        $bootstrap = new PermanentCapabilityConnection();
        $isolated = new PermanentCapabilityConnection([], 'wrong value');
        $exception = $this->capture(fn() => (new DatabaseCapabilityProbe(
            $bootstrap,
            static fn(string $name) => $isolated,
            $this->names([self::DATABASE])
        ))->detect());
        self::assertStringContainsString('exact inserted value', $exception->getMessage());
        self::assertContains('DROP DATABASE `' . self::DATABASE . '`', array_column($bootstrap->operations, 0));
    }

    public function testDatabaseCleanupFailureIsSurfaced(): void
    {
        $failure = self::driverError(1142);
        $bootstrap = new PermanentCapabilityConnection(['DROP DATABASE' => $failure]);
        $exception = $this->capture(fn() => (new DatabaseCapabilityProbe(
            $bootstrap,
            static fn(string $name) => new PermanentCapabilityConnection(),
            $this->names([self::DATABASE])
        ))->detect());
        self::assertInstanceOf(DatabasePermissionsInsufficientException::class, $exception);
        self::assertSame($failure, $exception->getPrevious());
    }

    public function testWorkflowFailureRemainsPrimaryWhenDatabaseCleanupAlsoFails(): void
    {
        $workflowFailure = new RuntimeException('insert failure');
        $cleanupFailure = new RuntimeException('drop failure');
        $messages = [];
        $probe = new DatabaseCapabilityProbe(
            new PermanentCapabilityConnection(['DROP DATABASE' => $cleanupFailure]),
            static fn(string $name) => new PermanentCapabilityConnection(['INSERT INTO' => $workflowFailure]),
            $this->names([self::DATABASE]),
            static function (string $message) use (&$messages): void { $messages[] = $message; }
        );
        $exception = $this->capture(static fn() => $probe->detect());

        self::assertSame($workflowFailure, $exception);
        self::assertSame(['Readiness temporary database cleanup error: drop failure'], $messages);
    }

    #[DataProvider('fallbackFailureProvider')]
    public function testFallbackFailuresBecomePermissionsErrorsAndCleanupWhenCreated(
        string $prefix,
        bool $tableCreated,
        bool $returnsFalse
    ): void {
        $permission = self::driverError(1044);
        $failures = ['CREATE DATABASE' => $permission];
        if (!$returnsFalse) { $failures[$prefix] = new RuntimeException('fallback failure'); }
        $configured = new PermanentCapabilityConnection($failures, 'freetv-readiness-write-read-ok', $returnsFalse ? [$prefix] : []);
        $exception = $this->capture(fn() => (new DatabaseCapabilityProbe(
            new PermanentCapabilityConnection(['CREATE DATABASE' => $permission]),
            null,
            $this->names([self::DATABASE, self::TABLE]),
            static function (): void {},
            static fn() => $configured
        ))->detect());
        self::assertInstanceOf(DatabasePermissionsInsufficientException::class, $exception);
        self::assertSame($tableCreated, in_array('DROP TABLE `' . self::TABLE . '`', array_column($configured->operations, 0), true));
    }

    public static function fallbackFailureProvider(): array
    {
        return [
            'table creation' => ['CREATE TABLE', false, false],
            'insert' => ['INSERT INTO', true, false],
            'read' => ['SELECT', true, false],
            'false statement' => ['INSERT INTO', true, true],
            'drop table' => ['DROP TABLE', true, false],
        ];
    }

    #[DataProvider('unsafeNameProvider')]
    public function testUnsafeDisposableDatabaseNamesNeverReachSql(string $name): void
    {
        $connection = new PermanentCapabilityConnection();
        $exception = $this->capture(fn() => (new DatabaseCapabilityProbe(
            $connection,
            null,
            $this->names([$name])
        ))->detect());
        self::assertStringContainsString('unsafe', $exception->getMessage());
        self::assertSame([], $connection->operations);
    }

    #[DataProvider('unsafeNameProvider')]
    public function testUnsafeFallbackTableNamesNeverReachTableSql(string $name): void
    {
        $permission = self::driverError(1044);
        $bootstrap = new PermanentCapabilityConnection(['CREATE DATABASE' => $permission]);
        $configured = new PermanentCapabilityConnection();
        $this->capture(fn() => (new DatabaseCapabilityProbe(
            $bootstrap,
            null,
            $this->names([self::DATABASE, $name]),
            null,
            static fn() => $configured
        ))->detect());
        self::assertSame([], $configured->operations);
    }

    public static function unsafeNameProvider(): array
    {
        return [
            'SQL injection' => ['freetv_readiness_bad;DROP TABLE users'],
            'ordinary table' => ['users'],
            'missing prefix' => ['readiness_aaaaaaaaaaaa'],
            'short suffix' => ['freetv_readiness_aaaaaaaaaaa'],
            'non-hex' => ['freetv_readiness_gggggggggggg'],
            'path traversal' => ['../freetv_readiness_aaaaaaaaaaaa'],
            'backtick' => ['freetv_readiness_aaaaaaaaaaa`'],
        ];
    }

    private function names(array $names): callable
    {
        return static function () use (&$names): string { return array_shift($names); };
    }
    private function capture(callable $operation): Throwable
    {
        try { $operation(); } catch (Throwable $exception) { return $exception; }
        self::fail('Expected failure');
    }
    private static function driverError(int $code): PDOException
    {
        $exception = new PDOException('permission denied');
        $exception->errorInfo = ['42000', $code, 'permission denied'];
        return $exception;
    }
}
