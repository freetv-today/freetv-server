<?php

declare(strict_types=1);

require_once __DIR__ . '/../public/api/admin/DatabaseCapabilityProbe.php';
require_once __DIR__ . '/../public/api/admin/MariaDbError.php';
require_once __DIR__ . '/../public/api/admin/DatabaseIdentifier.php';
require_once __DIR__ . '/../public/api/admin/SqlPackageExecutor.php';
require_once __DIR__ . '/../public/api/admin/SchemaBootstrapper.php';
require_once __DIR__ . '/../public/api/admin/FreshBootstrapData.php';
require_once __DIR__ . '/../public/api/admin/FreshDatabaseInstaller.php';
require_once __DIR__ . '/../public/api/admin/FreshArtifactInstaller.php';
require_once __DIR__ . '/../public/api/admin/PackageBootstrapContracts.php';
require_once __DIR__ . '/../public/api/admin/Settings.php';
require_once __DIR__ . '/../public/api/admin/publication/PublicationException.php';
require_once __DIR__ . '/../public/api/admin/publication/PublicationTimestamp.php';
require_once __DIR__ . '/../public/api/admin/publication/PublicationSemanticHasher.php';
require_once __DIR__ . '/../public/api/admin/publication/PublicationSemanticDelta.php';
require_once __DIR__ . '/../public/api/admin/publication/ConfigPublicationSerializer.php';
require_once __DIR__ . '/../public/api/admin/publication/PlaylistPublicationSerializer.php';
require_once __DIR__ . '/../public/api/admin/publication/PlaylistIndexSerializer.php';
require_once __DIR__ . '/../public/api/admin/publication/PublicationStatusService.php';
require_once __DIR__ . '/../public/api/admin/Bootstrapper.php';

use FreeTV\Admin\Bootstrapper;
use FreeTV\Admin\FreshArtifactInstaller;
use FreeTV\Admin\FreshBootstrapData;
use FreeTV\Admin\FreshDatabaseInstaller;
use FreeTV\Admin\SchemaBootstrapper;
use FreeTV\Admin\Publication\PublicationStatusService;

final class BootstrapTestSchema
{
    public function hasTable(string $table): bool
    {
        return in_array($table, SchemaBootstrapper::REQUIRED_TABLES, true);
    }
}

final class BootstrapTestQuery
{
    private array $conditions = [];

    public function __construct(private BootstrapTestConnection $connection, private string $table)
    {
    }

    public function where(string $field, mixed $value): self
    {
        $this->conditions[$field] = $value;
        return $this;
    }

    public function orderBy(string $field): self
    {
        return $this;
    }

    public function lockForUpdate(): self
    {
        $this->connection->userLockChecks++;
        return $this;
    }

    public function exists(): bool
    {
        return $this->matchingRows() !== [];
    }

    public function first(array $fields = ['*']): ?object
    {
        $rows = $this->matchingRows();
        if ($rows === []) {
            return null;
        }
        $row = $rows[0];
        if ($fields !== ['*']) {
            $row = array_intersect_key($row, array_flip($fields));
        }
        return (object) $row;
    }

    public function count(): int
    {
        return count($this->matchingRows());
    }

    public function delete(): int
    {
        $before = count($this->connection->rows[$this->table]);
        if ($this->conditions === []) {
            $this->connection->rows[$this->table] = [];
        } else {
            $this->connection->rows[$this->table] = array_values(array_filter(
                $this->connection->rows[$this->table],
                fn(array $row): bool => !$this->matches($row)
            ));
        }
        return $before - count($this->connection->rows[$this->table]);
    }

    public function insert(array $row): bool
    {
        $this->connection->rows[$this->table][] = $this->withId($row);
        return true;
    }

    public function insertGetId(array $row): int
    {
        $row = $this->withId($row);
        $this->connection->rows[$this->table][] = $row;
        return $row['id'];
    }

    public function updateOrInsert(array $attributes, array $values): bool
    {
        foreach ($this->connection->rows[$this->table] as &$row) {
            $matches = true;
            foreach ($attributes as $field => $value) {
                if (($row[$field] ?? null) !== $value) {
                    $matches = false;
                }
            }
            if ($matches) {
                $row = array_merge($row, $values);
                return true;
            }
        }
        unset($row);
        $this->connection->rows[$this->table][] = $this->withId(array_merge($attributes, $values));
        return true;
    }

    private function matchingRows(): array
    {
        return array_values(array_filter(
            $this->connection->rows[$this->table],
            fn(array $row): bool => $this->matches($row)
        ));
    }

    private function matches(array $row): bool
    {
        foreach ($this->conditions as $field => $value) {
            if (($row[$field] ?? null) !== $value) {
                return false;
            }
        }
        return true;
    }

    private function withId(array $row): array
    {
        if (!isset($row['id'])) {
            $ids = array_column($this->connection->rows[$this->table], 'id');
            $row['id'] = $ids === [] ? 1 : max($ids) + 1;
        }
        return $row;
    }
}

final class BootstrapTestConnection
{
    public array $rows;
    public int $userLockChecks = 0;
    public int $beginCount = 0;
    public int $commitCount = 0;
    public int $rollbackCount = 0;
    private ?array $snapshot = null;

    public function __construct(array $rows = [])
    {
        $this->rows = array_merge([
            'users' => [],
            'playlists' => [],
            'playlist_shows' => [],
            'app_settings' => [],
        ], $rows);
    }

    public function getPdo(): object
    {
        return new stdClass();
    }

    public function getSchemaBuilder(): BootstrapTestSchema
    {
        return new BootstrapTestSchema();
    }

    public function table(string $table): BootstrapTestQuery
    {
        return new BootstrapTestQuery($this, $table);
    }

    public function beginTransaction(): void
    {
        $this->beginCount++;
        $this->snapshot = $this->rows;
    }

    public function commit(): void
    {
        $this->commitCount++;
        $this->snapshot = null;
    }

    public function rollBack(): void
    {
        $this->rollbackCount++;
        if ($this->snapshot !== null) {
            $this->rows = $this->snapshot;
            $this->snapshot = null;
        }
    }
}

final class BootstrapTestServer
{
    public function statement(string $sql): bool
    {
        throw new RuntimeException('Complete schema must not execute database DDL');
    }
}

function bootstrapTestAssertSame(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message . "\nExpected: " . var_export($expected, true)
            . "\nActual: " . var_export($actual, true));
    }
}

function bootstrapTestRoot(): string
{
    $root = sys_get_temp_dir() . '/freetv-bootstrap-test-' . bin2hex(random_bytes(8));
    if (!mkdir($root, 0700)) {
        throw new RuntimeException('Could not create bootstrap test directory');
    }
    return $root;
}

function bootstrapTestRemove(string $root): void
{
    if (!is_dir($root)) {
        return;
    }
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iterator as $item) {
        $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }
    rmdir($root);
}

function bootstrapTestService(
    BootstrapTestConnection $connection,
    string $publicRoot,
    ?callable $rename = null,
    ?int &$schemaCalls = null
): Bootstrapper {
    $schemaCalls = 0;
    $schema = new SchemaBootstrapper(
        new BootstrapTestServer(),
        static function () use ($connection, &$schemaCalls): BootstrapTestConnection {
            $schemaCalls++;
            return $connection;
        },
        static fn(): string => 'existing_database',
        'freetv',
        __DIR__ . '/../sql/freetv_mariadb_schema-tables-only.sql'
    );
    return new Bootstrapper(
        $schema,
        new FreshBootstrapData(__DIR__ . '/../resources/bootstrap/fresh.json'),
        new FreshDatabaseInstaller(),
        new FreshArtifactInstaller($publicRoot, $rename),
        static fn() => new DateTimeImmutable('2026-09-02T12:34:56Z')
    );
}

$root = bootstrapTestRoot();
try {
    file_put_contents($root . '/unrelated.txt', 'preserve me');
    file_put_contents($root . '/config.json', '{"stale":true}');
    $connection = new BootstrapTestConnection([
        'playlists' => [[
            'id' => 99, 'filename' => 'stale.json', 'dbtitle' => 'Stale',
            'is_default' => 1, 'sort_order' => 9,
        ]],
        'playlist_shows' => [['id' => 8, 'playlist_id' => 99]],
        'app_settings' => [[
            'id' => 1, 'setting_key' => 'show_ads', 'setting_value' => 'true', 'scope' => 'viewer',
        ]],
    ]);
    $schemaCalls = 0;
    $result = bootstrapTestService($connection, $root, null, $schemaCalls)->fresh('first_admin', 'secret1');

    bootstrapTestAssertSame(Bootstrapper::INITIALIZED, $result, 'Fresh bootstrap did not succeed');
    bootstrapTestAssertSame(1, $schemaCalls, 'Bootstrapper did not delegate schema preparation exactly once');
    bootstrapTestAssertSame(1, $connection->commitCount, 'Fresh MariaDB transaction was not committed');
    bootstrapTestAssertSame(1, count($connection->rows['users']), 'Fresh must create exactly one user');
    bootstrapTestAssertSame('first_admin', $connection->rows['users'][0]['username'],
        'Fresh administrator did not use submitted username');
    bootstrapTestAssertSame(true, password_verify('secret1', $connection->rows['users'][0]['password_hash']),
        'Fresh administrator did not use the submitted password');
    bootstrapTestAssertSame('playlist-one.json', $connection->rows['playlists'][0]['filename'],
        'Fresh playlist baseline was not installed');
    bootstrapTestAssertSame([], $connection->rows['playlist_shows'], 'Fresh must contain zero shows');
    bootstrapTestAssertSame('false', $connection->rows['app_settings'][0]['setting_value'],
        'Fresh show_ads setting was not reset');

    foreach (['config.json', 'playlists/index.json', 'playlists/playlist-one.json'] as $relative) {
        bootstrapTestAssertSame(true, is_file($root . '/' . $relative), "Missing Fresh artifact {$relative}");
    }
    $playlistArtifact = json_decode(
        (string) file_get_contents($root . '/playlists/playlist-one.json'),
        true,
        512,
        JSON_THROW_ON_ERROR
    );
    bootstrapTestAssertSame([], $playlistArtifact['shows'], 'Empty Fresh playlist did not serialize as shows=[]');
    bootstrapTestAssertSame('preserve me', file_get_contents($root . '/unrelated.txt'),
        'Fresh bootstrap changed an unrelated public file');
    bootstrapTestAssertSame(false, str_contains((string) file_get_contents($root . '/config.json'), 'stale'),
        'Fresh bootstrap did not replace stale managed config');

    $status = (new PublicationStatusService(
        $root,
        static fn() => $connection->rows['playlists'],
        static fn(int $playlistId) => $connection->rows['playlist_shows'],
        static fn() => ['show_ads' => false]
    ))->status();
    bootstrapTestAssertSame(false, $status['playlists'][0]['changed'],
        'Fresh playlist is immediately reported as unpublished');
    bootstrapTestAssertSame(null, $status['playlists'][0]['error'],
        'Fresh playlist has an immediate publication error');
    bootstrapTestAssertSame(false, $status['config']['changed'],
        'Fresh config is immediately reported as unpublished');
    bootstrapTestAssertSame(false, $status['default_playlist']['changed'],
        'Fresh default playlist is immediately reported as changed');
} finally {
    bootstrapTestRemove($root);
}

$root = bootstrapTestRoot();
try {
    mkdir($root . '/playlists', 0700);
    $oldArtifacts = [
        'config.json' => '{"old":"config"}',
        'playlists/index.json' => '{"old":"index"}',
        'playlists/playlist-one.json' => '{"old":"playlist"}',
    ];
    foreach ($oldArtifacts as $relative => $contents) {
        file_put_contents($root . '/' . $relative, $contents);
    }
    file_put_contents($root . '/keep.json', '{"keep":true}');
    $renameFailureInjected = false;
    $rename = static function (string $from, string $to) use (&$renameFailureInjected): bool {
        if (!$renameFailureInjected
            && str_ends_with(str_replace('\\', '/', $to), '/playlists/index.json')) {
            $renameFailureInjected = true;
            return false;
        }
        return rename($from, $to);
    };
    $connection = new BootstrapTestConnection();
    try {
        bootstrapTestService($connection, $root, $rename)->fresh('failed_admin', 'secret1');
        throw new RuntimeException('Expected artifact promotion failure');
    } catch (RuntimeException $exception) {
        bootstrapTestAssertSame('Could not promote a Fresh Viewer artifact', $exception->getMessage(),
            'Unexpected artifact installation failure');
    }
    bootstrapTestAssertSame(1, $connection->rollbackCount,
        'Artifact failure did not roll back MariaDB initialization');
    bootstrapTestAssertSame([], $connection->rows['users'],
        'Artifact failure left an initialized administrator');
    foreach ($oldArtifacts as $relative => $contents) {
        bootstrapTestAssertSame($contents, file_get_contents($root . '/' . $relative),
            "Artifact failure did not restore {$relative}");
    }
    bootstrapTestAssertSame('{"keep":true}', file_get_contents($root . '/keep.json'),
        'Artifact failure changed an unrelated public file');
} finally {
    bootstrapTestRemove($root);
}

$root = bootstrapTestRoot();
try {
    $connection = new BootstrapTestConnection(['users' => [[
        'id' => 1, 'username' => 'existing', 'role' => 'admin', 'status' => 'active',
    ]]]);
    $result = bootstrapTestService($connection, $root)->fresh('replacement', 'secret1');
    bootstrapTestAssertSame(Bootstrapper::ALREADY_INITIALIZED, $result,
        'Existing user must preserve already-initialized behavior');
    bootstrapTestAssertSame(0, $connection->beginCount,
        'Already-initialized bootstrap unexpectedly began data changes');
    bootstrapTestAssertSame(false, file_exists($root . '/config.json'),
        'Already-initialized bootstrap unexpectedly wrote artifacts');
} finally {
    bootstrapTestRemove($root);
}

fwrite(STDOUT, "BootstrapperTest passed\n");
