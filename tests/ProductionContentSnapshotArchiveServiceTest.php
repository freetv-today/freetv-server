<?php

declare(strict_types=1);

require_once __DIR__ . '/../public/api/admin/ProductionContentSnapshotArchiveService.php';

use FreeTV\Admin\ProductionContentSnapshotArchiveException;
use FreeTV\Admin\ProductionContentSnapshotArchiveService;
use FreeTV\Admin\ServerPaths;

function archiveAssertSame(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message . "\nExpected: " . var_export($expected, true)
            . "\nActual: " . var_export($actual, true));
    }
}

function archiveRemoveTree(string $root): void
{
    if (!is_dir($root) && !is_link($root)) {
        return;
    }
    if (is_link($root)) {
        unlink($root);
        return;
    }
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iterator as $item) {
        $item->isDir() && !$item->isLink() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }
    rmdir($root);
}

function archiveWriteJson(string $path, array $value): void
{
    $json = json_encode(
        $value,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
    ) . "\n";
    if (file_put_contents($path, $json) !== strlen($json)) {
        throw new RuntimeException('Could not write archive test fixture');
    }
}

function archiveMetadata(string $root, string $relativePath): array
{
    return [
        'path' => $relativePath,
        'sha256' => hash_file('sha256', $root . '/' . $relativePath),
        'bytes' => filesize($root . '/' . $relativePath),
    ];
}

function createCompletedSnapshotFixture(string $appRoot, string $name): string
{
    $root = $appRoot . '/temp/data-snapshots/' . $name;
    if (!mkdir($root . '/thumbs', 0700, true)) {
        throw new RuntimeException('Could not create archive test snapshot');
    }

    $playlists = [
        ['id' => 1, 'sort_order' => 0, 'filename' => 'one.json'],
        ['id' => 2, 'sort_order' => 1, 'filename' => 'two.json'],
    ];
    $shows = [['id' => 1, 'playlist_id' => 1, 'sort_order' => 0, 'title' => 'One']];
    archiveWriteJson($root . '/playlists.json', $playlists);
    archiveWriteJson($root . '/playlist_shows.json', $shows);
    file_put_contents($root . '/thumbs/tt0000001.jpg', 'first thumbnail');
    file_put_contents($root . '/thumbs/tt0000002.jpg', 'second thumbnail');

    $thumbnailManifest = [
        'format_version' => 1,
        'files' => [
            archiveMetadata($root, 'thumbs/tt0000001.jpg'),
            archiveMetadata($root, 'thumbs/tt0000002.jpg'),
        ],
    ];
    archiveWriteJson($root . '/thumbs-manifest.json', $thumbnailManifest);

    $timestamp = substr($name, strlen('freetv-content-snapshot-'));
    $canonical = substr($timestamp, 0, 4) . '-' . substr($timestamp, 4, 2) . '-'
        . substr($timestamp, 6, 2) . 'T' . substr($timestamp, 9, 2) . ':'
        . substr($timestamp, 11, 2) . ':' . substr($timestamp, 13, 2) . '.000Z';
    archiveWriteJson($root . '/manifest.json', [
        'format_version' => 1,
        'production_snapshot_at' => $canonical,
        'capture_completed_at' => $canonical,
        'counts' => ['playlists' => 2, 'shows' => 1, 'thumbnails' => 2],
        'files' => [
            archiveMetadata($root, 'playlists.json'),
            archiveMetadata($root, 'playlist_shows.json'),
            archiveMetadata($root, 'thumbs-manifest.json'),
        ],
    ]);
    return $root;
}

function expectArchiveFailure(callable $operation, int $status, string $message): void
{
    try {
        $operation();
    } catch (ProductionContentSnapshotArchiveException $exception) {
        archiveAssertSame($status, $exception->getHttpStatus(), $message . ' returned the wrong status');
        return;
    }
    throw new RuntimeException($message);
}

final class FailingArchiveFixture
{
    private string $path = '';

    public function open(string $path, int $flags): bool
    {
        $this->path = $path;
        return file_put_contents($path, 'incomplete archive') !== false;
    }

    public function addEmptyDir(string $name): bool
    {
        return true;
    }

    public function addFile(string $path, string $name): bool
    {
        return true;
    }

    public function setMtimeName(string $name, int $timestamp): bool
    {
        return true;
    }

    public function close(): bool
    {
        return false;
    }
}

$testRoot = sys_get_temp_dir() . '/freetv-snapshot-archive-test-' . bin2hex(random_bytes(8));
mkdir($testRoot . '/public', 0700, true);
$paths = new ServerPaths($testRoot);
$service = new ProductionContentSnapshotArchiveService($paths);
$snapshotName = 'freetv-content-snapshot-20260828T183426Z';

try {
    $snapshotRoot = createCompletedSnapshotFixture($testRoot, $snapshotName);
    file_put_contents($testRoot . '/temp/data-snapshots/outside.txt', 'must not be archived');

    $result = $service->create($snapshotName);
    archiveAssertSame($snapshotName, $result['name'], 'Archive result has the wrong snapshot name');
    archiveAssertSame($testRoot . '/temp/data-snapshots/' . $snapshotName . '.zip', $result['path'],
        'Archive was not stored under the private snapshot root');
    archiveAssertSame(true, $result['bytes'] > 0, 'Archive byte count is invalid');
    archiveAssertSame(0600, fileperms($result['path']) & 0777, 'Snapshot archive is not private');

    $zip = new ZipArchive();
    archiveAssertSame(true, $zip->open($result['path'], ZipArchive::RDONLY), 'Completed archive does not open');
    $entries = [];
    for ($index = 0; $index < $zip->numFiles; $index++) {
        $entries[] = $zip->getNameIndex($index);
    }
    $expectedEntries = [
        $snapshotName . '/',
        $snapshotName . '/manifest.json',
        $snapshotName . '/playlists.json',
        $snapshotName . '/playlist_shows.json',
        $snapshotName . '/thumbs-manifest.json',
        $snapshotName . '/thumbs/',
        $snapshotName . '/thumbs/tt0000001.jpg',
        $snapshotName . '/thumbs/tt0000002.jpg',
    ];
    archiveAssertSame($expectedEntries, $entries, 'Archive entries are incomplete or nondeterministic');
    archiveAssertSame(false, in_array('outside.txt', $entries, true), 'File outside snapshot was archived');
    archiveAssertSame('first thumbnail', $zip->getFromName($snapshotName . '/thumbs/tt0000001.jpg'),
        'Nested thumbnail bytes were not preserved');
    $zip->close();

    archiveAssertSame($result, $service->resolveArchive($snapshotName), 'Completed archive did not resolve safely');
    $originalHash = hash_file('sha256', $result['path']);
    expectArchiveFailure(fn() => $service->create($snapshotName), 409,
        'Existing archive was silently overwritten');
    archiveAssertSame($originalHash, hash_file('sha256', $result['path']),
        'Archive collision modified the existing ZIP');

    foreach (['../' . $snapshotName, $snapshotName . '/thumbs', '/tmp/' . $snapshotName, '.staging-test'] as $invalid) {
        expectArchiveFailure(fn() => $service->create($invalid), 400, 'Invalid snapshot name was accepted');
        expectArchiveFailure(fn() => $service->resolveArchive($invalid), 400,
            'Invalid archive download name was accepted');
    }

    $invalidZipName = 'freetv-content-snapshot-20260828T184500Z';
    file_put_contents($testRoot . '/temp/data-snapshots/' . $invalidZipName . '.zip', 'not a ZIP archive');
    expectArchiveFailure(fn() => $service->resolveArchive($invalidZipName), 404,
        'Non-ZIP file with a snapshot archive name was accepted');

    file_put_contents($snapshotRoot . '/unexpected.txt', 'unexpected');
    unlink($result['path']);
    expectArchiveFailure(fn() => $service->create($snapshotName), 409,
        'Unexpected snapshot file was archived');
    unlink($snapshotRoot . '/unexpected.txt');

    $symlinkName = 'freetv-content-snapshot-20260828T190000Z';
    $symlinkRoot = createCompletedSnapshotFixture($testRoot, $symlinkName);
    unlink($symlinkRoot . '/thumbs/tt0000002.jpg');
    symlink($testRoot . '/temp/data-snapshots/outside.txt', $symlinkRoot . '/thumbs/tt0000002.jpg');
    expectArchiveFailure(fn() => $service->create($symlinkName), 409,
        'Symlinked snapshot content was archived');

    $failingName = 'freetv-content-snapshot-20260828T200000Z';
    createCompletedSnapshotFixture($testRoot, $failingName);
    $failingService = new ProductionContentSnapshotArchiveService(
        $paths,
        static fn(): FailingArchiveFixture => new FailingArchiveFixture()
    );
    expectArchiveFailure(fn() => $failingService->create($failingName), 500,
        'Incomplete archive finalization was accepted');
    $temporaryArchives = glob($testRoot . '/temp/data-snapshots/.archive-*.tmp');
    archiveAssertSame([], $temporaryArchives === false ? [] : $temporaryArchives,
        'Failed archive creation left an incomplete ZIP');
    archiveAssertSame(false, file_exists($testRoot . '/temp/data-snapshots/' . $failingName . '.zip'),
        'Failed archive creation left a completed-looking ZIP');
} finally {
    archiveRemoveTree($testRoot);
}

echo "ProductionContentSnapshotArchiveService tests passed\n";
