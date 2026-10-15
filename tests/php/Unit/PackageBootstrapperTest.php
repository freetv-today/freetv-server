<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../public/api/admin/PackageBootstrapContracts.php';
require_once __DIR__ . '/../../../public/api/admin/DatasetPackage.php';
require_once __DIR__ . '/../../../public/api/admin/DatabaseCapabilityProbe.php';
require_once __DIR__ . '/../../../public/api/admin/MariaDbError.php';
require_once __DIR__ . '/../../../public/api/admin/DatabaseIdentifier.php';
require_once __DIR__ . '/../../../public/api/admin/SqlPackageExecutor.php';
require_once __DIR__ . '/../../../public/api/admin/SchemaBootstrapper.php';
require_once __DIR__ . '/../../../public/api/admin/FreshBootstrapData.php';
require_once __DIR__ . '/../../../public/api/admin/FreshDatabaseInstaller.php';
require_once __DIR__ . '/../../../public/api/admin/FreshArtifactInstaller.php';
require_once __DIR__ . '/../../../public/api/admin/Bootstrapper.php';

use FreeTV\Admin\Bootstrapper;
use FreeTV\Admin\DatasetPackage;
use FreeTV\Admin\DatasetPackageSource;
use FreeTV\Admin\FreshArtifactInstaller;
use FreeTV\Admin\FreshBootstrapData;
use FreeTV\Admin\FreshDatabaseInstaller;
use FreeTV\Admin\PackageArtifactStager;
use FreeTV\Admin\PackageDatabaseInstallation;
use FreeTV\Admin\SchemaBootstrapper;
use FreeTV\Admin\StagedPackageArtifacts;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PermanentPackageSchema
{
    public function hasTable(string $table): bool
    {
        return in_array($table, SchemaBootstrapper::REQUIRED_TABLES, true);
    }
}
final class PermanentPackageUsers
{
    public function __construct(private PermanentPackageConnection $connection) {}
    public function orderBy(string $field): self { return $this; }
    public function lockForUpdate(): self { $this->connection->lockChecks++; return $this; }
    public function first(array $fields): ?object { return $this->connection->hasUsers ? (object) ['id' => 1] : null; }
    public function exists(): bool { return $this->connection->hasUsers; }
}
final class PermanentPackageConnection
{
    public int $begins = 0;
    public int $commits = 0;
    public int $rollbacks = 0;
    public int $lockChecks = 0;
    public function __construct(public bool $hasUsers = false) {}
    public function getPdo(): object { return new stdClass(); }
    public function getSchemaBuilder(): PermanentPackageSchema { return new PermanentPackageSchema(); }
    public function table(string $table): PermanentPackageUsers { return new PermanentPackageUsers($this); }
    public function beginTransaction(): void { $this->begins++; }
    public function commit(): void { $this->commits++; }
    public function rollBack(): void { $this->rollbacks++; }
}
final class PermanentPackageServer
{
    public array $operations = [];
    public function statement(string $sql): bool { $this->operations[] = $sql; return true; }
}
final class PermanentDatasetSource implements DatasetPackageSource
{
    public array $requested = [];
    public array $workspaces = [];
    public bool $fail = false;
    public function acquire(string $dataset): DatasetPackage
    {
        $this->requested[] = $dataset;
        if ($this->fail) { throw new RuntimeException('injected package acquisition failure'); }
        $workspace = sys_get_temp_dir() . '/freetv-package-phpunit-' . bin2hex(random_bytes(8));
        mkdir($workspace . '/root', 0700, true);
        $this->workspaces[] = $workspace;
        return new DatasetPackage($workspace, $workspace . '/root', $dataset, []);
    }
}
final class PermanentPackageDatabase implements PackageDatabaseInstallation
{
    public array $events = [];
    public ?string $failure = null;
    public function validatedDataStatements(DatasetPackage $package): array
    {
        $this->events[] = 'validate:' . $package->dataset();
        if ($this->failure === 'validate') { throw new RuntimeException('injected SQL validation failure'); }
        return ['validated-statement'];
    }
    public function install($connection, DatasetPackage $package, array $statements, string $user, string $pass): void
    {
        $this->events[] = "install:{$package->dataset()}:{$user}:{$pass}:" . implode(',', $statements);
        if ($this->failure === 'install') { throw new RuntimeException('injected package installation failure'); }
    }
    public function verify($connection, DatasetPackage $package, string $username): void
    {
        $this->events[] = 'verify:' . $package->dataset();
        if ($this->failure === 'verify') { throw new RuntimeException('injected package verification failure'); }
    }
}
final class PermanentStagedArtifacts implements StagedPackageArtifacts
{
    public array $events = [];
    public ?string $failure = null;
    public function promote(): void
    {
        $this->events[] = 'promote';
        if ($this->failure === 'promote') { throw new RuntimeException('injected artifact promotion failure'); }
    }
    public function commit(): void
    {
        $this->events[] = 'commit';
        if ($this->failure === 'commit') { throw new RuntimeException('injected artifact cleanup failure'); }
    }
    public function rollback(): void { $this->events[] = 'rollback'; }
}
final class PermanentPackageStager implements PackageArtifactStager
{
    public array $prepared = [];
    public function __construct(public PermanentStagedArtifacts $installation) {}
    public function prepare(DatasetPackage $package): StagedPackageArtifacts
    {
        $this->prepared[] = $package->dataset();
        return $this->installation;
    }
}

final class PackageBootstrapperTest extends TestCase
{
    private array $cleanupRoots = [];
    protected function tearDown(): void
    {
        foreach ($this->cleanupRoots as $root) { DatasetPackage::removeTree($root); }
    }

    #[DataProvider('modeProvider')]
    public function testPackageModesUseCorrectSourceAndCompleteSharedPipeline(
        string $mode,
        string $dataset,
        bool $usesBaseline
    ): void {
        $connection = new PermanentPackageConnection();
        $current = new PermanentDatasetSource();
        $baseline = new PermanentDatasetSource();
        $database = new PermanentPackageDatabase();
        $artifacts = new PermanentStagedArtifacts();
        $stager = new PermanentPackageStager($artifacts);
        $bootstrapper = $this->service($connection, $current, $database, $stager, $baseline);

        $result = $bootstrapper->{$mode}('route_admin', 'route_password');

        self::assertSame(Bootstrapper::INITIALIZED, $result);
        self::assertSame($usesBaseline ? [] : [$dataset], $current->requested);
        self::assertSame($usesBaseline ? [$dataset] : [], $baseline->requested);
        self::assertSame([
            "validate:{$dataset}",
            "install:{$dataset}:route_admin:route_password:validated-statement",
            "verify:{$dataset}",
        ], $database->events);
        self::assertSame([$dataset], $stager->prepared);
        self::assertSame(['promote', 'commit'], $artifacts->events);
        self::assertSame([1, 1, 0], [$connection->begins, $connection->commits, $connection->rollbacks]);
        $workspace = ($usesBaseline ? $baseline : $current)->workspaces[0];
        self::assertDirectoryDoesNotExist($workspace);
    }

    public static function modeProvider(): array
    {
        return [
            'baseline bundled sample' => ['baseline', 'sample', true],
            'current sample' => ['sample', 'sample', false],
            'current official' => ['official', 'official', false],
        ];
    }

    public function testInvalidSqlFailsBeforeSchemaPreparationOrTransaction(): void
    {
        $connection = new PermanentPackageConnection();
        $source = new PermanentDatasetSource();
        $database = new PermanentPackageDatabase();
        $database->failure = 'validate';
        $server = new PermanentPackageServer();
        $artifacts = new PermanentStagedArtifacts();
        $bootstrapper = $this->service(
            $connection,
            $source,
            $database,
            new PermanentPackageStager($artifacts),
            null,
            $server
        );
        $exception = $this->capture(fn() => $bootstrapper->sample('admin', 'password'));
        self::assertSame('injected SQL validation failure', $exception->getMessage());
        self::assertSame([], $server->operations);
        self::assertSame([0, 0, 0], [$connection->begins, $connection->commits, $connection->rollbacks]);
        self::assertSame([], $artifacts->events);
        self::assertDirectoryDoesNotExist($source->workspaces[0]);
    }

    public function testAlreadyInitializedDoesNotAcquireOrModifyPackage(): void
    {
        $connection = new PermanentPackageConnection(true);
        $source = new PermanentDatasetSource();
        $database = new PermanentPackageDatabase();
        $artifacts = new PermanentStagedArtifacts();
        $result = $this->service(
            $connection,
            $source,
            $database,
            new PermanentPackageStager($artifacts)
        )->official('replacement', 'password');
        self::assertSame(Bootstrapper::ALREADY_INITIALIZED, $result);
        self::assertSame([], $source->requested);
        self::assertSame([], $database->events);
        self::assertSame([0, 0, 0], [$connection->begins, $connection->commits, $connection->rollbacks]);
    }

    public function testAcquisitionFailureDoesNotBeginDatabaseInitialization(): void
    {
        $connection = new PermanentPackageConnection();
        $source = new PermanentDatasetSource();
        $source->fail = true;
        $database = new PermanentPackageDatabase();
        $exception = $this->capture(fn() => $this->service(
            $connection,
            $source,
            $database,
            new PermanentPackageStager(new PermanentStagedArtifacts())
        )->sample('admin', 'password'));
        self::assertSame('injected package acquisition failure', $exception->getMessage());
        self::assertSame(['sample'], $source->requested);
        self::assertSame([], $database->events);
        self::assertSame([0, 0, 0], [$connection->begins, $connection->commits, $connection->rollbacks]);
    }

    #[DataProvider('transactionFailureProvider')]
    public function testPackageFailuresRollbackSupportedDatabaseAndArtifactState(string $failure): void
    {
        $connection = new PermanentPackageConnection();
        $source = new PermanentDatasetSource();
        $database = new PermanentPackageDatabase();
        $artifacts = new PermanentStagedArtifacts();
        if (in_array($failure, ['install', 'verify'], true)) { $database->failure = $failure; }
        if ($failure === 'promote') { $artifacts->failure = 'promote'; }
        $stager = new PermanentPackageStager($artifacts);
        $exception = $this->capture(fn() => $this->service(
            $connection, $source, $database, $stager
        )->official('admin', 'password'));

        self::assertStringContainsString('injected', $exception->getMessage());
        self::assertSame([1, 0, 1], [$connection->begins, $connection->commits, $connection->rollbacks]);
        if ($failure === 'promote') {
            self::assertSame(['promote', 'rollback'], $artifacts->events);
        } else {
            self::assertSame([], $artifacts->events);
        }
        self::assertDirectoryDoesNotExist($source->workspaces[0]);
    }

    public static function transactionFailureProvider(): array
    {
        return ['install' => ['install'], 'verification' => ['verify'], 'promotion' => ['promote']];
    }

    public function testArtifactCleanupFailureAfterCommitDoesNotChangeSuccessResult(): void
    {
        $connection = new PermanentPackageConnection();
        $source = new PermanentDatasetSource();
        $database = new PermanentPackageDatabase();
        $artifacts = new PermanentStagedArtifacts();
        $artifacts->failure = 'commit';
        $result = $this->service(
            $connection,
            $source,
            $database,
            new PermanentPackageStager($artifacts)
        )->sample('admin', 'password');
        self::assertSame(Bootstrapper::INITIALIZED, $result);
        self::assertSame([1, 1, 0], [$connection->begins, $connection->commits, $connection->rollbacks]);
        self::assertSame(['promote', 'commit'], $artifacts->events);
        self::assertDirectoryDoesNotExist($source->workspaces[0]);
    }

    private function service(
        PermanentPackageConnection $connection,
        DatasetPackageSource $source,
        PermanentPackageDatabase $database,
        PermanentPackageStager $stager,
        ?DatasetPackageSource $baseline = null,
        ?PermanentPackageServer $server = null
    ): Bootstrapper {
        $publicRoot = sys_get_temp_dir() . '/unused-fresh-phpunit-' . bin2hex(random_bytes(8));
        mkdir($publicRoot, 0700);
        $this->cleanupRoots[] = $publicRoot;
        $schema = new SchemaBootstrapper(
            $server ?? new PermanentPackageServer(),
            static fn() => $connection,
            static fn(): string => 'existing_database',
            'freetv',
            dirname(__DIR__, 3) . '/sql/freetv_mariadb_schema-tables-only.sql'
        );
        return new Bootstrapper(
            $schema,
            new FreshBootstrapData(dirname(__DIR__, 3) . '/resources/bootstrap/fresh.json'),
            new FreshDatabaseInstaller(),
            new FreshArtifactInstaller($publicRoot),
            null,
            $source,
            $database,
            $stager,
            $baseline
        );
    }
    private function capture(callable $operation): RuntimeException
    {
        try { $operation(); } catch (RuntimeException $exception) { return $exception; }
        self::fail('Expected RuntimeException');
    }
}
