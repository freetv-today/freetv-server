<?php

declare(strict_types=1);

require_once __DIR__ . '/../public/api/admin/PackageBootstrapContracts.php';
require_once __DIR__ . '/../public/api/admin/DatasetPackage.php';
require_once __DIR__ . '/../public/api/admin/DatabaseCapabilityProbe.php';
require_once __DIR__ . '/../public/api/admin/MariaDbError.php';
require_once __DIR__ . '/../public/api/admin/DatabaseIdentifier.php';
require_once __DIR__ . '/../public/api/admin/SqlPackageExecutor.php';
require_once __DIR__ . '/../public/api/admin/SchemaBootstrapper.php';
require_once __DIR__ . '/../public/api/admin/FreshBootstrapData.php';
require_once __DIR__ . '/../public/api/admin/FreshDatabaseInstaller.php';
require_once __DIR__ . '/../public/api/admin/FreshArtifactInstaller.php';
require_once __DIR__ . '/../public/api/admin/publication/PublicationException.php';
require_once __DIR__ . '/../public/api/admin/publication/PublicationTimestamp.php';
require_once __DIR__ . '/../public/api/admin/publication/ConfigPublicationSerializer.php';
require_once __DIR__ . '/../public/api/admin/publication/PlaylistPublicationSerializer.php';
require_once __DIR__ . '/../public/api/admin/publication/PlaylistIndexSerializer.php';
require_once __DIR__ . '/../public/api/admin/Bootstrapper.php';

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

final class PackageBootstrapSchema
{
    public function hasTable(string $table): bool
    {
        return in_array($table, SchemaBootstrapper::REQUIRED_TABLES, true);
    }
}

final class PackageBootstrapUsers
{
    public function __construct(private bool $hasUsers)
    {
    }

    public function orderBy(string $field): self
    {
        return $this;
    }

    public function lockForUpdate(): self
    {
        return $this;
    }

    public function first(array $fields): ?object
    {
        return $this->hasUsers ? (object) ['id' => 1] : null;
    }

    public function exists(): bool
    {
        return $this->hasUsers;
    }
}

final class PackageBootstrapConnection
{
    public int $begins = 0;
    public int $commits = 0;
    public int $rollbacks = 0;

    public function __construct(public bool $hasUsers = false)
    {
    }

    public function getPdo(): object
    {
        return new stdClass();
    }

    public function getSchemaBuilder(): PackageBootstrapSchema
    {
        return new PackageBootstrapSchema();
    }

    public function table(string $table): PackageBootstrapUsers
    {
        return new PackageBootstrapUsers($this->hasUsers);
    }

    public function beginTransaction(): void
    {
        $this->begins++;
    }

    public function commit(): void
    {
        $this->commits++;
    }

    public function rollBack(): void
    {
        $this->rollbacks++;
    }
}

final class PackageBootstrapServer
{
    public function statement(string $sql): bool
    {
        throw new RuntimeException('Schema is already present');
    }
}

final class FakeDatasetSource implements DatasetPackageSource
{
    public array $requested = [];
    public array $workspaces = [];

    public function acquire(string $dataset): DatasetPackage
    {
        $this->requested[] = $dataset;
        $workspace = sys_get_temp_dir() . '/freetv-package-route-' . bin2hex(random_bytes(6));
        mkdir($workspace . '/root', 0700, true);
        $this->workspaces[] = $workspace;
        return new DatasetPackage($workspace, $workspace . '/root', $dataset, []);
    }
}

final class FakePackageDatabase implements PackageDatabaseInstallation
{
    public array $events = [];
    public bool $failInstall = false;

    public function validatedDataStatements(DatasetPackage $package): array
    {
        $this->events[] = 'validate:' . $package->dataset();
        return ['validated'];
    }

    public function install(
        $connection,
        DatasetPackage $package,
        array $dataStatements,
        string $username,
        string $password
    ): void {
        $this->events[] = "install:{$package->dataset()}:{$username}:{$password}";
        if ($this->failInstall) {
            throw new RuntimeException('injected database installation failure');
        }
    }

    public function verify($connection, DatasetPackage $package, string $username): void
    {
        $this->events[] = 'verify:' . $package->dataset();
    }
}

final class FakeStagedArtifacts implements StagedPackageArtifacts
{
    public bool $promoted = false;
    public bool $committed = false;
    public bool $rolledBack = false;
    public bool $failPromotion = false;

    public function promote(): void
    {
        $this->promoted = true;
        if ($this->failPromotion) {
            throw new RuntimeException('injected artifact promotion failure');
        }
    }

    public function commit(): void
    {
        $this->committed = true;
    }

    public function rollback(): void
    {
        $this->rolledBack = true;
    }
}

final class FakePackageArtifactStager implements PackageArtifactStager
{
    public array $prepared = [];

    public function __construct(public FakeStagedArtifacts $installation)
    {
    }

    public function prepare(DatasetPackage $package): StagedPackageArtifacts
    {
        $this->prepared[] = $package->dataset();
        return $this->installation;
    }
}

function packageBootstrapper(
    PackageBootstrapConnection $connection,
    DatasetPackageSource $source,
    PackageDatabaseInstallation $database,
    PackageArtifactStager $artifacts
): Bootstrapper {
    $schema = new SchemaBootstrapper(
        new PackageBootstrapServer(),
        static fn() => $connection,
        static fn(): string => 'existing_database',
        'freetv',
        __DIR__ . '/../sql/freetv_mariadb_schema-tables-only.sql'
    );
    $public = sys_get_temp_dir() . '/unused-fresh-' . bin2hex(random_bytes(4));
    return new Bootstrapper(
        $schema,
        new FreshBootstrapData(__DIR__ . '/../resources/bootstrap/fresh.json'),
        new FreshDatabaseInstaller(),
        new FreshArtifactInstaller($public),
        null,
        $source,
        $database,
        $artifacts
    );
}

function packageBootstrapAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

foreach (['sample', 'official'] as $dataset) {
    $connection = new PackageBootstrapConnection();
    $source = new FakeDatasetSource();
    $database = new FakePackageDatabase();
    $installation = new FakeStagedArtifacts();
    $artifacts = new FakePackageArtifactStager($installation);
    $bootstrapper = packageBootstrapper($connection, $source, $database, $artifacts);
    $result = $dataset === 'sample'
        ? $bootstrapper->sample('route_admin', 'route_password')
        : $bootstrapper->official('route_admin', 'route_password');

    packageBootstrapAssert($result === Bootstrapper::INITIALIZED, "{$dataset} route did not initialize");
    packageBootstrapAssert($source->requested === [$dataset], "{$dataset} route requested wrong package");
    packageBootstrapAssert($database->events === [
        "validate:{$dataset}",
        "install:{$dataset}:route_admin:route_password",
        "verify:{$dataset}",
    ], "{$dataset} route did not run the shared database pipeline");
    packageBootstrapAssert($installation->promoted && $installation->committed,
        "{$dataset} route did not commit artifacts");
    packageBootstrapAssert($connection->commits === 1 && $connection->rollbacks === 0,
        "{$dataset} route did not commit MariaDB exactly once");
    packageBootstrapAssert(!file_exists($source->workspaces[0]),
        "{$dataset} route did not clean its package workspace");
}

$connection = new PackageBootstrapConnection();
$source = new FakeDatasetSource();
$database = new FakePackageDatabase();
$database->failInstall = true;
$installation = new FakeStagedArtifacts();
try {
    packageBootstrapper($connection, $source, $database, new FakePackageArtifactStager($installation))
        ->sample('admin', 'password');
    throw new RuntimeException('Expected database installation failure');
} catch (RuntimeException $exception) {
    packageBootstrapAssert($exception->getMessage() === 'injected database installation failure',
        'Unexpected database failure');
}
packageBootstrapAssert($connection->rollbacks === 1, 'Database installation failure was not rolled back');
packageBootstrapAssert(!$installation->promoted, 'Artifacts were touched after database installation failure');
packageBootstrapAssert(!file_exists($source->workspaces[0]), 'Database failure did not clean package workspace');

$connection = new PackageBootstrapConnection();
$source = new FakeDatasetSource();
$database = new FakePackageDatabase();
$installation = new FakeStagedArtifacts();
$installation->failPromotion = true;
try {
    packageBootstrapper($connection, $source, $database, new FakePackageArtifactStager($installation))
        ->official('admin', 'password');
    throw new RuntimeException('Expected artifact promotion failure');
} catch (RuntimeException $exception) {
    packageBootstrapAssert($exception->getMessage() === 'injected artifact promotion failure',
        'Unexpected artifact failure');
}
packageBootstrapAssert($connection->rollbacks === 1, 'Artifact failure did not roll back MariaDB');
packageBootstrapAssert($installation->rolledBack, 'Artifact failure did not invoke artifact rollback');
packageBootstrapAssert(!file_exists($source->workspaces[0]), 'Artifact failure did not clean package workspace');

$connection = new PackageBootstrapConnection(true);
$source = new FakeDatasetSource();
$database = new FakePackageDatabase();
$installation = new FakeStagedArtifacts();
$result = packageBootstrapper($connection, $source, $database, new FakePackageArtifactStager($installation))
    ->official('replacement', 'password');
packageBootstrapAssert($result === Bootstrapper::ALREADY_INITIALIZED,
    'Package route did not preserve already-initialized protection');
packageBootstrapAssert($source->requested === [], 'Already-initialized package route downloaded an asset');
packageBootstrapAssert($connection->begins === 0, 'Already-initialized package route began a transaction');

fwrite(STDOUT, "PackageBootstrapperTest passed\n");
