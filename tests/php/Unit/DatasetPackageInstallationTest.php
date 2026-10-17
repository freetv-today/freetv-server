<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../public/api/admin/PackageBootstrapContracts.php';
require_once __DIR__ . '/../../../public/api/admin/DatasetPackage.php';
require_once __DIR__ . '/../../../public/api/admin/SqlPackageExecutor.php';
require_once __DIR__ . '/../../../public/api/admin/PackageDatabaseInstaller.php';
require_once __DIR__ . '/../../../public/api/admin/PackageArtifactInstaller.php';

use FreeTV\Admin\DatasetPackage;
use FreeTV\Admin\PackageArtifactInstaller;
use FreeTV\Admin\PackageDatabaseInstaller;
use FreeTV\Admin\SqlPackageExecutor;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PermanentInstallationSchema
{
    public function hasTable(string $table): bool { return in_array($table, ['problem_reports', 'problem_report_ips'], true); }
}
final class PermanentInstallationTable
{
    public function __construct(private string $name, private PermanentInstallationConnection $connection) {}
    public function delete(): void { $this->connection->events[] = 'delete:' . $this->name; }
    public function updateOrInsert(array $key, array $value): void { $this->connection->events[] = ['updateOrInsert:' . $this->name, $key, $value]; }
    public function insert(array $value): void { $this->connection->events[] = ['insert:' . $this->name, $value]; }
}
final class PermanentInstallationConnection
{
    public array $events = [];
    public function getSchemaBuilder(): PermanentInstallationSchema { return new PermanentInstallationSchema(); }
    public function table(string $table): PermanentInstallationTable { return new PermanentInstallationTable($table, $this); }
    public function unprepared(string $sql): bool { $this->events[] = 'sql:' . $sql; return true; }
}

final class DatasetPackageInstallationTest extends TestCase
{
    private string $root;
    private string $public;
    private string $schema;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/freetv-permanent-package-install-' . bin2hex(random_bytes(8));
        $this->public = $this->root . '/public';
        mkdir($this->public, 0700, true);
        $this->schema = dirname(__DIR__, 3) . '/sql/freetv_mariadb_schema-tables-only.sql';
    }
    protected function tearDown(): void { DatasetPackage::removeTree($this->root); }

    public function testCanonicalSqlReturnsTwoOrderedDataStatements(): void
    {
        $package = $this->package(['database.sql' => $this->validSql()]);
        $statements = $this->databaseInstaller()->validatedDataStatements($package);
        self::assertCount(2, $statements);
        self::assertStringStartsWith('INSERT INTO playlists', $statements[0]);
        self::assertStringStartsWith('INSERT INTO playlist_shows', $statements[1]);
    }

    #[DataProvider('invalidSqlProvider')]
    public function testUnsafeOrNoncanonicalSqlIsRejected(string $case, string $message): void
    {
        $sql = $this->validSql();
        $sql = match ($case) {
            'expression' => str_replace('CONVERT(0x46697874757265 USING utf8mb4)', 'LOAD_FILE(CONVERT(0x2f657463 USING utf8mb4))', $sql),
            'schema' => preg_replace('/CREATE TABLE IF NOT EXISTS app_settings/', 'CREATE TABLE IF NOT EXISTS changed_settings', $sql, 1),
            'missing start' => str_replace("START TRANSACTION;\n", '', $sql),
            'missing commit' => str_replace("COMMIT;\n", '', $sql),
            'target' => str_replace('INSERT INTO playlists', 'INSERT INTO users', $sql),
            'columns' => str_replace('(`id`, `filename`', '(`id`, `wrong_column`', $sql),
            'extra' => $sql . "DELETE FROM users;\n",
        };
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage($message);
        $this->databaseInstaller()->validatedDataStatements($this->package(['database.sql' => $sql]));
    }
    public static function invalidSqlProvider(): array
    {
        return [
            'unsafe expression' => ['expression', 'unsupported expressions'],
            'altered schema' => ['schema', 'canonical FreeTV schema prefix'],
            'missing start' => ['missing start', 'transaction structure'],
            'missing commit' => ['missing commit', 'transaction structure'],
            'unsupported target' => ['target', 'unsupported playlists insert'],
            'unsupported columns' => ['columns', 'unsupported playlists insert'],
            'arbitrary statement' => ['extra', 'transaction structure'],
        ];
    }

    public function testDatabaseInstallClearsTablesExecutesSqlSetsViewerSettingAndCreatesHashedAdmin(): void
    {
        $package = $this->package();
        $connection = new PermanentInstallationConnection();
        $statements = $this->databaseInstaller()->validatedDataStatements($package);
        $this->databaseInstaller()->install($connection, $package, $statements, 'fixture_admin', 'secret-pass');
        self::assertSame(['delete:problem_reports', 'delete:problem_report_ips', 'delete:playlist_shows', 'delete:playlists'], array_slice($connection->events, 0, 4));
        self::assertSame('sql:' . $statements[0], $connection->events[4]);
        self::assertSame('sql:' . $statements[1], $connection->events[5]);
        self::assertSame(['updateOrInsert:app_settings', ['setting_key' => 'show_ads'], ['setting_value' => 'false', 'scope' => 'viewer']], $connection->events[6]);
        self::assertSame('insert:users', $connection->events[7][0]);
        self::assertSame(['username' => 'fixture_admin', 'role' => 'admin', 'status' => 'active'], array_diff_key($connection->events[7][1], ['password_hash' => true]));
        self::assertTrue(password_verify('secret-pass', $connection->events[7][1]['password_hash']));
    }

    public function testSuccessfulArtifactCommitReplacesManagedTreesAndPreservesUnrelatedBytes(): void
    {
        $this->seedPublished();
        $package = $this->package();
        $installation = (new PackageArtifactInstaller($this->public))->prepare($package);
        self::assertNotSame([], $this->stages());
        $installation->promote(); $installation->commit();
        self::assertSame($package->files()['config.json'], hash_file('sha256', $this->public . '/config.json'));
        self::assertSame($package->files()['playlists/index.json'], hash_file('sha256', $this->public . '/playlists/index.json'));
        self::assertSame($package->files()['thumbs/tt0000001.jpg'], hash_file('sha256', $this->public . '/thumbs/tt0000001.jpg'));
        self::assertFileDoesNotExist($this->public . '/playlists/old.json');
        self::assertFileDoesNotExist($this->public . '/thumbs/old.jpg');
        self::assertFileDoesNotExist($this->public . '/database.sql');
        self::assertSame("unrelated\0bytes", file_get_contents($this->public . '/unrelated.bin'));
        self::assertSame(0775, fileperms($this->public . '/playlists') & 0777);
        self::assertSame(0775, fileperms($this->public . '/thumbs') & 0777);
        self::assertSame([], $this->stages());
    }

    #[DataProvider('promotionFailureProvider')]
    public function testPartialPromotionFailuresRestoreEntirePriorManagedTree(string $destination): void
    {
        $this->seedPublished();
        $before = $this->publishedSnapshot();
        $failed = false;
        $rename = function (string $from, string $to) use (&$failed, $destination): bool {
            if (!$failed && $to === $this->public . '/' . $destination) { $failed = true; return false; }
            return rename($from, $to);
        };
        $installation = (new PackageArtifactInstaller($this->public, $rename))->prepare($this->package());
        try { $installation->promote(); self::fail('Expected failure'); } catch (RuntimeException $exception) { self::assertStringContainsString('promote', $exception->getMessage()); }
        self::assertSame($before, $this->publishedSnapshot());
        self::assertSame([], $this->stages());
    }
    public static function promotionFailureProvider(): array { return ['config' => ['config.json'], 'after config' => ['playlists'], 'after directory' => ['thumbs']]; }

    #[DataProvider('corruptionProvider')]
    public function testVerificationCorruptionRestoresEntirePriorManagedTree(string $destination, string $corruptPath): void
    {
        $this->seedPublished();
        $before = $this->publishedSnapshot();
        $corrupted = false;
        $rename = function (string $from, string $to) use (&$corrupted, $destination, $corruptPath): bool {
            $result = rename($from, $to);
            if ($result && !$corrupted && $to === $this->public . '/' . $destination) { file_put_contents($this->public . '/' . $corruptPath, 'corrupt'); $corrupted = true; }
            return $result;
        };
        try { (new PackageArtifactInstaller($this->public, $rename))->prepare($this->package())->promote(); self::fail('Expected verification failure'); }
        catch (RuntimeException $exception) { self::assertStringContainsString('verification failed', $exception->getMessage()); }
        self::assertSame($before, $this->publishedSnapshot());
        self::assertSame([], $this->stages());
    }
    public static function corruptionProvider(): array { return ['config file' => ['config.json', 'config.json'], 'playlist file' => ['playlists', 'playlists/index.json'], 'thumbnail file' => ['thumbs', 'thumbs/tt0000001.jpg']]; }

    #[DataProvider('unsafeManagedProvider')]
    public function testUnsafeManagedTypesAreRejectedWithoutDamage(string $path, string $type): void
    {
        $this->seedPublished();
        DatasetPackage::removeTree($this->public . '/' . $path);
        if ($type === 'directory') { mkdir($this->public . '/' . $path); }
        elseif ($type === 'file') { file_put_contents($this->public . '/' . $path, 'unsafe'); }
        else { $target = $this->root . '/outside-' . md5($path); file_put_contents($target, 'outside'); symlink($target, $this->public . '/' . $path); }
        $before = $this->publishedSnapshot();
        try { (new PackageArtifactInstaller($this->public))->prepare($this->package())->promote(); self::fail('Unsafe path accepted'); }
        catch (RuntimeException $exception) { self::assertStringContainsString('unsafe type', $exception->getMessage()); }
        self::assertSame($before, $this->publishedSnapshot()); self::assertSame([], $this->stages());
    }
    public static function unsafeManagedProvider(): array { return ['config directory' => ['config.json', 'directory'], 'playlist file' => ['playlists', 'file'], 'thumb file' => ['thumbs', 'file'], 'config symlink' => ['config.json', 'symlink']]; }

    public function testMissingPublicRootIsRejected(): void
    {
        $this->expectException(RuntimeException::class);
        (new PackageArtifactInstaller($this->root . '/missing'))->prepare($this->package());
    }

    #[DataProvider('invalidInventoryProvider')]
    public function testUnsupportedOrMissingPackageArtifactsCleanStage(string $case): void
    {
        $files = $this->packageContents();
        if ($case === 'unsupported') { $files['other.txt'] = 'bad'; }
        else { unset($files['config.json']); }
        $package = $this->package($files);
        try {
            $rename = static fn(string $from, string $to): bool => file_exists($from) && rename($from, $to);
            $installation = (new PackageArtifactInstaller($this->public, $rename))->prepare($package);
            $installation->promote();
            self::fail('Invalid inventory accepted');
        } catch (RuntimeException) {}
        self::assertSame([], $this->stages());
    }
    public static function invalidInventoryProvider(): array { return ['unsupported path' => ['unsupported'], 'missing config' => ['missing']]; }

    public function testPromoteThenRollbackRestoresOldStateAndCommitMakesRollbackNoOp(): void
    {
        $this->seedPublished(); $before = $this->publishedSnapshot();
        $installation = (new PackageArtifactInstaller($this->public))->prepare($this->package());
        $installation->promote(); $installation->rollback();
        self::assertSame($before, $this->publishedSnapshot());
        $committed = (new PackageArtifactInstaller($this->public))->prepare($this->package());
        $committed->promote(); $committed->commit(); $after = $this->publishedSnapshot(); $committed->rollback();
        self::assertSame($after, $this->publishedSnapshot()); self::assertSame([], $this->stages());
    }

    public function testRollbackRemovesManagedArtifactsWithoutPredecessors(): void
    {
        $installation = (new PackageArtifactInstaller($this->public))->prepare($this->package());
        $installation->promote(); $installation->rollback();
        self::assertFileDoesNotExist($this->public . '/config.json');
        self::assertDirectoryDoesNotExist($this->public . '/playlists');
        self::assertDirectoryDoesNotExist($this->public . '/thumbs');
        self::assertSame([], $this->stages());
    }

    private function databaseInstaller(): PackageDatabaseInstaller { return new PackageDatabaseInstaller(new SqlPackageExecutor(), $this->schema); }
    private function validSql(): string
    {
        return (string) file_get_contents($this->schema) . "\nSTART TRANSACTION;\n"
            . 'INSERT INTO playlists (`id`, `filename`, `dbtitle`, `dbversion`, `author`, `email`, `link`, `lastupdated`, `is_default`, `sort_order`) VALUES (1, CONVERT(0x666978747572652e6a736f6e USING utf8mb4), CONVERT(0x46697874757265 USING utf8mb4), NULL, NULL, NULL, NULL, NULL, 1, 0);' . "\n"
            . 'INSERT INTO playlist_shows (`playlist_id`, `category`, `status`, `identifier`, `title`, `description`, `start_year`, `end_year`, `imdb`, `group_name`, `sort_order`) VALUES (1, NULL, CONVERT(0x616374697665 USING utf8mb4), CONVERT(0x66697874757265 USING utf8mb4), CONVERT(0x46697874757265 USING utf8mb4), NULL, NULL, NULL, NULL, NULL, 0);' . "\nCOMMIT;\n";
    }
    private function packageContents(): array { return ['database.sql' => $this->validSql(), 'config.json' => '{"lastupdated":"2026-09-02T12:00:00.000Z","show_ads":false}', 'playlists/index.json' => '{"default":"fixture.json","playlists":[{"filename":"fixture.json"}]}', 'playlists/fixture.json' => '{"shows":[]}', 'thumbs/tt0000001.jpg' => 'new thumbnail']; }
    private function package(?array $contents = null): DatasetPackage
    {
        $contents ??= $this->packageContents(); $workspace = $this->root . '/package-' . bin2hex(random_bytes(5)); $root = $workspace . '/root'; mkdir($root . '/playlists', 0700, true); mkdir($root . '/thumbs', 0700, true); $hashes = [];
        foreach ($contents as $path => $bytes) { $target = $root . '/' . $path; if (!is_dir(dirname($target))) { mkdir(dirname($target), 0700, true); } file_put_contents($target, $bytes); $hashes[$path] = hash('sha256', $bytes); }
        return new DatasetPackage($workspace, $root, 'sample', $hashes);
    }
    private function seedPublished(): void { mkdir($this->public . '/playlists', 0700); mkdir($this->public . '/thumbs', 0700); file_put_contents($this->public . '/config.json', 'old config'); file_put_contents($this->public . '/playlists/old.json', 'old playlist'); file_put_contents($this->public . '/thumbs/old.jpg', 'old thumb'); file_put_contents($this->public . '/unrelated.bin', "unrelated\0bytes"); }
    private function stages(): array { return glob($this->public . '/.freetv-package-*') ?: []; }
    private function publishedSnapshot(): array
    {
        $result = []; $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->public, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
        foreach ($iterator as $item) { if (str_starts_with($item->getFilename(), '.freetv-package-')) { continue; } $relative = substr($item->getPathname(), strlen($this->public) + 1); $result[$relative] = $item->isFile() ? hash_file('sha256', $item->getPathname()) : 'directory'; }
        ksort($result); return $result;
    }
}
