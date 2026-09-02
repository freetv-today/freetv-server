<?php

declare(strict_types=1);

require_once __DIR__ . '/../public/api/admin/PackageBootstrapContracts.php';
require_once __DIR__ . '/../public/api/admin/DatasetPackage.php';
require_once __DIR__ . '/../public/api/admin/SqlPackageExecutor.php';
require_once __DIR__ . '/../public/api/admin/PackageDatabaseInstaller.php';
require_once __DIR__ . '/../public/api/admin/PackageArtifactInstaller.php';

use FreeTV\Admin\DatasetPackage;
use FreeTV\Admin\PackageArtifactInstaller;
use FreeTV\Admin\PackageDatabaseInstaller;
use FreeTV\Admin\SqlPackageExecutor;

function installationTestPackage(string $workspace, string $databaseSql): DatasetPackage
{
    $root = $workspace . '/package';
    mkdir($root . '/playlists', 0700, true);
    mkdir($root . '/thumbs', 0700, true);
    $contents = [
        'database.sql' => $databaseSql,
        'config.json' => '{"lastupdated":"2026-09-02T12:00:00.000Z","show_ads":false}',
        'playlists/index.json' => '{"default":"fixture.json","playlists":[]}',
        'playlists/fixture.json' => '{"shows":[]}',
        'thumbs/tt0000001.jpg' => 'new thumbnail',
    ];
    $hashes = [];
    foreach ($contents as $relativePath => $content) {
        file_put_contents($root . '/' . $relativePath, $content);
        $hashes[$relativePath] = hash('sha256', $content);
    }
    return new DatasetPackage($workspace, $root, 'sample', $hashes);
}

function installationSql(string $extra = ''): string
{
    $canonical = (string) file_get_contents(__DIR__ . '/../sql/freetv_mariadb_schema-tables-only.sql');
    return $canonical . <<<'SQL'

START TRANSACTION;
INSERT INTO playlists (`id`, `filename`, `dbtitle`, `dbversion`, `author`, `email`, `link`, `lastupdated`, `is_default`, `sort_order`) VALUES
  (1, CONVERT(0x666978747572652e6a736f6e USING utf8mb4), CONVERT(0x46697874757265 USING utf8mb4), NULL, NULL, NULL, NULL, CONVERT(0x323032362d30392d30322031323a30303a3030 USING utf8mb4), 1, 0);
INSERT INTO playlist_shows (`playlist_id`, `category`, `status`, `identifier`, `title`, `description`, `start_year`, `end_year`, `imdb`, `group_name`, `sort_order`) VALUES
  (1, NULL, CONVERT(0x616374697665 USING utf8mb4), CONVERT(0x66697874757265 USING utf8mb4), CONVERT(0x46697874757265 USING utf8mb4), NULL, NULL, NULL, NULL, NULL, 0);
COMMIT;
SQL
        . $extra;
}

function installationAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$workspace = sys_get_temp_dir() . '/freetv-package-sql-' . bin2hex(random_bytes(6));
mkdir($workspace, 0700);
$package = installationTestPackage($workspace, installationSql());
$installer = new PackageDatabaseInstaller(
    new SqlPackageExecutor(),
    __DIR__ . '/../sql/freetv_mariadb_schema-tables-only.sql'
);
installationAssert(count($installer->validatedDataStatements($package)) === 2,
    'Valid generated dataset SQL was rejected');
$package->cleanup();

$workspace = sys_get_temp_dir() . '/freetv-package-sql-bad-' . bin2hex(random_bytes(6));
mkdir($workspace, 0700);
$badSql = str_replace(
    'CONVERT(0x46697874757265 USING utf8mb4)',
    'LOAD_FILE(CONVERT(0x2f6574632f706173737764 USING utf8mb4))',
    installationSql()
);
$package = installationTestPackage($workspace, $badSql);
try {
    $installer->validatedDataStatements($package);
    throw new RuntimeException('Unsafe dataset SQL expression was accepted');
} catch (RuntimeException $exception) {
    installationAssert(str_contains($exception->getMessage(), 'unsupported expressions'),
        'Unsafe SQL produced an unexpected validation error');
}
$package->cleanup();

$workspace = sys_get_temp_dir() . '/freetv-package-artifacts-' . bin2hex(random_bytes(6));
$public = $workspace . '/public';
mkdir($public . '/playlists', 0700, true);
mkdir($public . '/thumbs', 0700, true);
$package = installationTestPackage($workspace . '/download', installationSql());
file_put_contents($public . '/config.json', 'old config');
file_put_contents($public . '/playlists/old.json', 'old playlist');
file_put_contents($public . '/thumbs/old.jpg', 'old thumbnail');
$artifacts = (new PackageArtifactInstaller($public))->prepare($package);
$artifacts->promote();
$artifacts->commit();
installationAssert(!file_exists($public . '/playlists/old.json')
    && !file_exists($public . '/thumbs/old.jpg')
    && file_get_contents($public . '/thumbs/tt0000001.jpg') === 'new thumbnail',
    'Package artifact promotion did not atomically replace managed directories');
installationAssert((fileperms($public . '/playlists') & 0777) === 0775
    && (fileperms($public . '/thumbs') & 0777) === 0775,
    'Promoted Viewer directories are not readable and writable for publication');
$package->cleanup();
DatasetPackage::removeTree($workspace);

$workspace = sys_get_temp_dir() . '/freetv-package-artifacts-fail-' . bin2hex(random_bytes(6));
$public = $workspace . '/public';
mkdir($public . '/playlists', 0700, true);
mkdir($public . '/thumbs', 0700, true);
$package = installationTestPackage($workspace . '/download', installationSql());
file_put_contents($public . '/config.json', 'old config');
file_put_contents($public . '/playlists/old.json', 'old playlist');
file_put_contents($public . '/thumbs/old.jpg', 'old thumbnail');
$failed = false;
$rename = static function (string $from, string $to) use (&$failed, $public): bool {
    if (!$failed && $to === $public . '/playlists') {
        $failed = true;
        return false;
    }
    return rename($from, $to);
};
try {
    $artifacts = (new PackageArtifactInstaller($public, $rename))->prepare($package);
    $artifacts->promote();
    throw new RuntimeException('Expected package artifact promotion failure');
} catch (RuntimeException $exception) {
    installationAssert(str_contains($exception->getMessage(), 'promote Viewer package'),
        'Artifact promotion failure produced unexpected error');
}
installationAssert(file_get_contents($public . '/config.json') === 'old config'
    && file_get_contents($public . '/playlists/old.json') === 'old playlist'
    && file_get_contents($public . '/thumbs/old.jpg') === 'old thumbnail',
    'Failed package artifact promotion did not restore prior managed content');
$package->cleanup();
DatasetPackage::removeTree($workspace);

$workspace = sys_get_temp_dir() . '/freetv-package-artifacts-verify-' . bin2hex(random_bytes(6));
$public = $workspace . '/public';
mkdir($public . '/playlists', 0700, true);
mkdir($public . '/thumbs', 0700, true);
$package = installationTestPackage($workspace . '/download', installationSql());
file_put_contents($public . '/config.json', 'old config');
$corrupted = false;
$rename = static function (string $from, string $to) use (&$corrupted, $public): bool {
    $result = rename($from, $to);
    if ($result && !$corrupted && $to === $public . '/config.json') {
        file_put_contents($to, 'corrupted after promotion');
        $corrupted = true;
    }
    return $result;
};
try {
    $artifacts = (new PackageArtifactInstaller($public, $rename))->prepare($package);
    $artifacts->promote();
    throw new RuntimeException('Expected package artifact verification failure');
} catch (RuntimeException $exception) {
    installationAssert(str_contains($exception->getMessage(), 'verification failed'),
        'Artifact verification failure produced unexpected error');
}
installationAssert(file_get_contents($public . '/config.json') === 'old config',
    'Artifact verification failure did not restore previous config');
$package->cleanup();
DatasetPackage::removeTree($workspace);

fwrite(STDOUT, "DatasetPackageInstallationTest passed\n");
