<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../public/api/admin/DatabaseCapabilityProbe.php';
require_once __DIR__ . '/../../../public/api/admin/MariaDbError.php';
require_once __DIR__ . '/../../../public/api/admin/DatabaseIdentifier.php';
require_once __DIR__ . '/../../../public/api/admin/SqlPackageExecutor.php';
require_once __DIR__ . '/../../../public/api/admin/SchemaBootstrapper.php';
require_once __DIR__ . '/../../../public/api/admin/FreshBootstrapData.php';
require_once __DIR__ . '/../../../public/api/admin/FreshDatabaseInstaller.php';
require_once __DIR__ . '/../../../public/api/admin/FreshArtifactInstaller.php';
require_once __DIR__ . '/../../../public/api/admin/publication/PublicationException.php';
require_once __DIR__ . '/../../../public/api/admin/publication/PublicationTimestamp.php';
require_once __DIR__ . '/../../../public/api/admin/publication/PublicationSemanticHasher.php';
require_once __DIR__ . '/../../../public/api/admin/publication/PublicationSemanticDelta.php';
require_once __DIR__ . '/../../../public/api/admin/publication/ConfigPublicationSerializer.php';
require_once __DIR__ . '/../../../public/api/admin/publication/PlaylistPublicationSerializer.php';
require_once __DIR__ . '/../../../public/api/admin/publication/PlaylistIndexSerializer.php';
require_once __DIR__ . '/../../../public/api/admin/publication/PublicationStatusService.php';
require_once __DIR__ . '/../../../public/api/admin/Bootstrapper.php';

use FreeTV\Admin\Bootstrapper;
use FreeTV\Admin\FreshArtifactInstaller;
use FreeTV\Admin\FreshBootstrapData;
use FreeTV\Admin\FreshDatabaseInstaller;
use FreeTV\Admin\Publication\PublicationStatusService;
use FreeTV\Admin\SchemaBootstrapper;
use PHPUnit\Framework\TestCase;

final class PermanentFreshSchema
{
    public function hasTable(string $table): bool
    {
        return in_array($table, SchemaBootstrapper::REQUIRED_TABLES, true);
    }
}

final class PermanentFreshQuery
{
    private array $conditions = [];
    public function __construct(private PermanentFreshConnection $connection, private string $table) {}
    public function where(string $field, mixed $value): self { $this->conditions[$field] = $value; return $this; }
    public function orderBy(string $field): self { return $this; }
    public function lockForUpdate(): self
    {
        $this->connection->lockChecks++;
        if ($this->connection->initializeOnLock && $this->connection->rows['users'] === []) {
            $this->connection->rows['users'][] = [
                'id' => 99, 'username' => 'racing-admin', 'role' => 'admin', 'status' => 'active',
            ];
        }
        return $this;
    }
    public function exists(): bool { return $this->matching() !== []; }
    public function first(array $fields = ['*']): ?object
    {
        $rows = $this->matching();
        if ($rows === []) { return null; }
        $row = $fields === ['*'] ? $rows[0] : array_intersect_key($rows[0], array_flip($fields));
        return (object) $row;
    }
    public function count(): int { return count($this->matching()); }
    public function delete(): int
    {
        $before = count($this->connection->rows[$this->table]);
        $this->connection->rows[$this->table] = $this->conditions === [] ? [] : array_values(array_filter(
            $this->connection->rows[$this->table], fn(array $row): bool => !$this->matches($row)
        ));
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
            if ($this->rowMatches($row, $attributes)) { $row = array_merge($row, $values); return true; }
        }
        unset($row);
        $this->connection->rows[$this->table][] = $this->withId(array_merge($attributes, $values));
        return true;
    }
    private function matching(): array
    {
        return array_values(array_filter($this->connection->rows[$this->table], fn($row) => $this->matches($row)));
    }
    private function matches(array $row): bool { return $this->rowMatches($row, $this->conditions); }
    private function rowMatches(array $row, array $conditions): bool
    {
        foreach ($conditions as $field => $value) { if (($row[$field] ?? null) !== $value) { return false; } }
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

final class PermanentFreshConnection
{
    public array $rows;
    public int $begins = 0;
    public int $commits = 0;
    public int $rollbacks = 0;
    public int $lockChecks = 0;
    public bool $initializeOnLock = false;
    private ?array $snapshot = null;
    public function __construct(array $rows = [])
    {
        $this->rows = array_merge([
            'users' => [], 'playlists' => [], 'playlist_shows' => [], 'app_settings' => [],
        ], $rows);
    }
    public function getPdo(): object { return new stdClass(); }
    public function getSchemaBuilder(): PermanentFreshSchema { return new PermanentFreshSchema(); }
    public function table(string $table): PermanentFreshQuery { return new PermanentFreshQuery($this, $table); }
    public function beginTransaction(): void { $this->begins++; $this->snapshot = $this->rows; }
    public function commit(): void { $this->commits++; $this->snapshot = null; }
    public function rollBack(): void
    {
        $this->rollbacks++;
        if ($this->snapshot !== null) { $this->rows = $this->snapshot; $this->snapshot = null; }
    }
}

final class PermanentFreshServer
{
    public function statement(string $sql): bool { throw new RuntimeException('Unexpected schema DDL'); }
}

final class BootstrapperTest extends TestCase
{
    private string $root;
    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/freetv-fresh-phpunit-' . bin2hex(random_bytes(8));
        if (!mkdir($this->root, 0700)) { self::fail('Could not create Fresh fixture root'); }
    }
    protected function tearDown(): void { $this->removeTree($this->root); }

    public function testFreshInitializationInstallsDatabaseAndMatchingViewerArtifacts(): void
    {
        mkdir($this->root . '/playlists', 0700);
        $this->write('config.json', "{\"stale\":true}\n");
        $this->write('playlists/index.json', "{\"stale\":true}\n");
        $this->write('playlists/playlist-one.json', "{\"stale\":true}\n");
        $this->write('unrelated.txt', 'preserve me');
        $connection = new PermanentFreshConnection([
            'playlists' => [['id' => 50, 'filename' => 'old.json']],
            'playlist_shows' => [['id' => 1, 'playlist_id' => 50]],
            'app_settings' => [['id' => 1, 'setting_key' => 'show_ads', 'setting_value' => 'true', 'scope' => 'viewer']],
        ]);

        $result = $this->service($connection)->fresh('first_admin', 'secret1');

        self::assertSame(Bootstrapper::INITIALIZED, $result);
        self::assertSame([1, 1, 0], [$connection->begins, $connection->commits, $connection->rollbacks]);
        self::assertSame(1, $connection->lockChecks);
        self::assertCount(1, $connection->rows['users']);
        self::assertSame('first_admin', $connection->rows['users'][0]['username']);
        self::assertTrue(password_verify('secret1', $connection->rows['users'][0]['password_hash']));
        self::assertCount(1, $connection->rows['playlists']);
        self::assertSame('playlist-one.json', $connection->rows['playlists'][0]['filename']);
        self::assertSame('Playlist One', $connection->rows['playlists'][0]['dbtitle']);
        self::assertSame(1, $connection->rows['playlists'][0]['is_default']);
        self::assertSame([], $connection->rows['playlist_shows']);
        self::assertSame('false', $connection->rows['app_settings'][0]['setting_value']);
        foreach (['config.json', 'playlists/index.json', 'playlists/playlist-one.json'] as $path) {
            self::assertFileExists($this->root . '/' . $path);
        }
        $config = $this->decode('config.json');
        $index = $this->decode('playlists/index.json');
        $playlist = $this->decode('playlists/playlist-one.json');
        self::assertSame([], $playlist['shows']);
        self::assertSame($playlist['lastupdated'], $index['playlists'][0]['lastupdated']);
        self::assertSame($playlist['lastupdated'], $config['lastupdated']);
        self::assertSame('2026-10-15 12:00:00', $connection->rows['playlists'][0]['lastupdated']);
        self::assertSame('preserve me', $this->read('unrelated.txt'));
        $status = (new PublicationStatusService(
            $this->root,
            static fn() => $connection->rows['playlists'],
            static fn(int $playlistId) => $connection->rows['playlist_shows'],
            static fn() => ['show_ads' => false]
        ))->status();
        self::assertFalse($status['playlists'][0]['changed']);
        self::assertNull($status['playlists'][0]['error']);
        self::assertFalse($status['config']['changed']);
        self::assertFalse($status['default_playlist']['changed']);
        self::assertSame([], glob($this->root . '/.freetv-fresh-*') ?: []);
    }

    public function testExistingUserPreventsFreshInitializationBeforeTransaction(): void
    {
        $connection = new PermanentFreshConnection(['users' => [[
            'id' => 1, 'username' => 'existing', 'role' => 'admin', 'status' => 'active',
        ]]]);
        $before = $connection->rows;
        self::assertSame(Bootstrapper::ALREADY_INITIALIZED, $this->service($connection)->fresh('new', 'secret1'));
        self::assertSame($before, $connection->rows);
        self::assertSame([0, 0, 0], [$connection->begins, $connection->commits, $connection->rollbacks]);
        self::assertFileDoesNotExist($this->root . '/config.json');
    }

    public function testUserAppearingAtLockedSecondCheckPreventsInitialization(): void
    {
        $connection = new PermanentFreshConnection();
        $connection->initializeOnLock = true;
        self::assertSame(Bootstrapper::ALREADY_INITIALIZED, $this->service($connection)->fresh('new', 'secret1'));
        self::assertSame([1, 0, 1], [$connection->begins, $connection->commits, $connection->rollbacks]);
        self::assertSame(1, $connection->lockChecks);
        self::assertSame([], $connection->rows['users']);
        self::assertFileDoesNotExist($this->root . '/config.json');
    }

    public function testArtifactPromotionFailureRollsBackDatabaseAndRestoresManagedFiles(): void
    {
        mkdir($this->root . '/playlists', 0700);
        $old = [
            'config.json' => '{"old":"config"}',
            'playlists/index.json' => '{"old":"index"}',
            'playlists/playlist-one.json' => '{"old":"playlist"}',
        ];
        foreach ($old as $path => $contents) { $this->write($path, $contents); }
        $this->write('unrelated.txt', 'preserve me');
        $failed = false;
        $rename = static function (string $from, string $to) use (&$failed): bool {
            if (!$failed && str_ends_with(str_replace('\\', '/', $to), '/playlists/index.json')) {
                $failed = true;
                return false;
            }
            return rename($from, $to);
        };
        $connection = new PermanentFreshConnection();
        try {
            $this->service($connection, $rename)->fresh('failed', 'secret1');
            self::fail('Expected artifact promotion failure');
        } catch (RuntimeException $exception) {
            self::assertSame('Could not promote a Fresh Viewer artifact', $exception->getMessage());
        }
        self::assertSame([1, 0, 1], [$connection->begins, $connection->commits, $connection->rollbacks]);
        self::assertSame([], $connection->rows['users']);
        foreach ($old as $path => $contents) { self::assertSame($contents, $this->read($path)); }
        self::assertSame('preserve me', $this->read('unrelated.txt'));
        self::assertSame([], glob($this->root . '/.freetv-fresh-*') ?: []);
    }

    private function service(PermanentFreshConnection $connection, ?callable $rename = null): Bootstrapper
    {
        $schema = new SchemaBootstrapper(
            new PermanentFreshServer(),
            static fn() => $connection,
            static fn(): string => 'existing_database',
            'freetv',
            dirname(__DIR__, 3) . '/sql/freetv_mariadb_schema-tables-only.sql'
        );
        return new Bootstrapper(
            $schema,
            new FreshBootstrapData(dirname(__DIR__, 3) . '/resources/bootstrap/fresh.json'),
            new FreshDatabaseInstaller(),
            new FreshArtifactInstaller($this->root, $rename),
            static fn(): DateTimeImmutable => new DateTimeImmutable('2026-10-15T12:00:00.987Z')
        );
    }
    private function write(string $path, string $contents): void
    {
        if (file_put_contents($this->root . '/' . $path, $contents) !== strlen($contents)) {
            self::fail('Could not write fixture');
        }
    }
    private function read(string $path): string { return (string) file_get_contents($this->root . '/' . $path); }
    private function decode(string $path): array
    {
        return json_decode($this->read($path), true, 512, JSON_THROW_ON_ERROR);
    }
    private function removeTree(string $root): void
    {
        if (!is_dir($root)) { return; }
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $item) { $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname()); }
        rmdir($root);
    }
}
