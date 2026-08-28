<?php

declare(strict_types=1);

require_once __DIR__ . '/../public/api/admin/ProductionContentSnapshotService.php';

use FreeTV\Admin\ProductionContentSnapshotException;
use FreeTV\Admin\ProductionContentSnapshotService;
use FreeTV\Admin\ServerPaths;

function snapshotAssertSame(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message . "\nExpected: " . var_export($expected, true)
            . "\nActual: " . var_export($actual, true));
    }
}

function snapshotRemoveTree(string $root): void
{
    if (!is_dir($root)) {
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

function snapshotCompletedDirectories(string $root): array
{
    $entries = glob($root . '/temp/data-snapshots/freetv-content-snapshot-*', GLOB_ONLYDIR);
    return $entries === false ? [] : $entries;
}

function snapshotExpectFailure(callable $operation, string $message): void
{
    try {
        $operation();
    } catch (ProductionContentSnapshotException) {
        return;
    }
    throw new RuntimeException($message);
}

$testRoot = sys_get_temp_dir() . '/freetv-production-snapshot-test-' . bin2hex(random_bytes(8));
if (!mkdir($testRoot . '/public/thumbs', 0700, true)) {
    throw new RuntimeException('Could not create snapshot test fixture');
}
$paths = new ServerPaths($testRoot);
$playlists = [
    ['id' => 8, 'sort_order' => 2, 'filename' => 'last.json', 'created_at' => null],
    ['id' => 3, 'sort_order' => 1, 'filename' => 'first.json', 'created_at' => '2026-01-01 00:00:00'],
    ['id' => 4, 'sort_order' => 1, 'filename' => 'second.json', 'created_at' => null],
];
$shows = [
    ['id' => 9, 'playlist_id' => 8, 'sort_order' => 0, 'title' => 'Last'],
    ['id' => 7, 'playlist_id' => 3, 'sort_order' => 2, 'title' => 'Second'],
    ['id' => 2, 'playlist_id' => 3, 'sort_order' => 0, 'title' => 'First'],
];
$loader = static fn(): array => ['playlists' => $playlists, 'playlist_shows' => $shows];

try {
    file_put_contents($testRoot . '/public/thumbs/tt7654321.jpg', 'second thumbnail');
    file_put_contents($testRoot . '/public/thumbs/tt0000002.jpg', 'first thumbnail');
    file_put_contents($testRoot . '/public/thumbs/readme.txt', 'ignored');
    file_put_contents($testRoot . '/public/thumbs/TT0000003.jpg', 'ignored');

    $times = [
        new DateTimeImmutable('2026-08-28T14:01:02.987-04:00'),
        new DateTimeImmutable('2026-08-28T18:01:04.321Z'),
    ];
    $clock = static function () use (&$times): DateTimeImmutable {
        return array_shift($times);
    };
    $result = (new ProductionContentSnapshotService($paths, $loader, $clock))->create();

    snapshotAssertSame('2026-08-28T18:01:02.000Z', $result['production_snapshot_at'],
        'Snapshot boundary is not canonical UTC');
    snapshotAssertSame('2026-08-28T18:01:04.000Z', $result['capture_completed_at'],
        'Snapshot completion is not canonical UTC');
    snapshotAssertSame(['playlists' => 3, 'shows' => 3, 'thumbnails' => 2], $result['counts'],
        'Snapshot counts are incorrect');
    snapshotAssertSame($testRoot . '/temp/data-snapshots/freetv-content-snapshot-20260828T180102Z',
        $result['path'], 'Snapshot path is not deterministic');
    snapshotAssertSame(false, str_starts_with($result['path'], $paths->publicRoot() . '/'),
        'Snapshot was written under the public root');
    snapshotAssertSame(0700, fileperms($result['path']) & 0777, 'Snapshot directory is not private');
    snapshotAssertSame(0600, fileperms($result['path'] . '/manifest.json') & 0777,
        'Snapshot manifest is not private');

    $storedPlaylists = json_decode((string) file_get_contents($result['path'] . '/playlists.json'), true, 512, JSON_THROW_ON_ERROR);
    $storedShows = json_decode((string) file_get_contents($result['path'] . '/playlist_shows.json'), true, 512, JSON_THROW_ON_ERROR);
    snapshotAssertSame([3, 4, 8], array_column($storedPlaylists, 'id'), 'Playlist ordering is not stable');
    snapshotAssertSame([2, 7, 9], array_column($storedShows, 'id'), 'Show ordering is not stable');
    snapshotAssertSame($playlists[1]['created_at'], $storedPlaylists[0]['created_at'],
        'Complete database row values were not preserved');

    $thumbnailManifest = json_decode((string) file_get_contents($result['path'] . '/thumbs-manifest.json'), true, 512, JSON_THROW_ON_ERROR);
    snapshotAssertSame(['thumbs/tt0000002.jpg', 'thumbs/tt7654321.jpg'],
        array_column($thumbnailManifest['files'], 'path'), 'Thumbnail manifest is not filtered and deterministic');
    foreach ($thumbnailManifest['files'] as $file) {
        $copiedPath = $result['path'] . '/' . $file['path'];
        snapshotAssertSame(hash_file('sha256', $copiedPath), $file['sha256'], 'Thumbnail SHA-256 is incorrect');
        snapshotAssertSame(filesize($copiedPath), $file['bytes'], 'Thumbnail byte count is incorrect');
    }
    $manifest = json_decode((string) file_get_contents($result['path'] . '/manifest.json'), true, 512, JSON_THROW_ON_ERROR);
    snapshotAssertSame(1, $manifest['format_version'], 'Snapshot manifest version is incorrect');
    snapshotAssertSame($result['counts'], $manifest['counts'], 'Snapshot manifest counts differ from result');
    snapshotAssertSame(['playlists.json', 'playlist_shows.json', 'thumbs-manifest.json'],
        array_column($manifest['files'], 'path'), 'Snapshot manifest artifact list is incorrect');
    foreach ($manifest['files'] as $file) {
        snapshotAssertSame(hash_file('sha256', $result['path'] . '/' . $file['path']), $file['sha256'],
            'Snapshot artifact SHA-256 is incorrect');
    }

    $lockRoot = $testRoot . '-lock';
    mkdir($lockRoot . '/public/thumbs', 0700, true);
    mkdir($lockRoot . '/temp/data-snapshots', 0700, true);
    $lock = fopen($lockRoot . '/temp/data-snapshots/.lock', 'c');
    if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) {
        throw new RuntimeException('Could not prepare snapshot lock fixture');
    }
    try {
        $lockService = new ProductionContentSnapshotService(
            new ServerPaths($lockRoot),
            $loader,
            static fn(): DateTimeImmutable => new DateTimeImmutable('2026-08-28T18:30:00Z')
        );
        snapshotExpectFailure(fn() => $lockService->create(), 'Concurrent snapshot lock was ignored');
        snapshotAssertSame([], snapshotCompletedDirectories($lockRoot),
            'Lock contention left a completed snapshot');
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
        snapshotRemoveTree($lockRoot);
    }

    $failureCases = [
        'copy' => [static function (): void { throw new RuntimeException('copy failed'); }, null, null],
        'hash' => [null, static fn(): false => false, null],
        'write' => [null, null, static function (): void { throw new RuntimeException('write failed'); }],
    ];
    foreach ($failureCases as $label => [$stager, $hasher, $writer]) {
        $failureRoot = $testRoot . '-' . $label;
        mkdir($failureRoot . '/public/thumbs', 0700, true);
        file_put_contents($failureRoot . '/public/thumbs/tt0000001.jpg', 'thumbnail');
        $failurePaths = new ServerPaths($failureRoot);
        $failureTimes = [
            new DateTimeImmutable('2026-08-28T19:00:0' . (count(snapshotCompletedDirectories($testRoot)) + 1) . 'Z'),
            new DateTimeImmutable('2026-08-28T19:00:10Z'),
        ];
        $failureClock = static fn(): DateTimeImmutable => array_shift($failureTimes);
        $service = new ProductionContentSnapshotService(
            $failurePaths,
            $loader,
            $failureClock,
            $stager,
            $hasher,
            $writer
        );
        snapshotExpectFailure(fn() => $service->create(), ucfirst($label) . ' failure was accepted');
        snapshotAssertSame([], snapshotCompletedDirectories($failureRoot),
            ucfirst($label) . ' failure left a completed snapshot');
        $staging = glob($failureRoot . '/temp/data-snapshots/.staging-*');
        snapshotAssertSame([], $staging === false ? [] : $staging,
            ucfirst($label) . ' failure left staged snapshot data');
        snapshotRemoveTree($failureRoot);
    }

    $cli = (string) file_get_contents(__DIR__ . '/../scripts/create-content-snapshot.php');
    snapshotAssertSame(true, str_contains($cli, "PHP_SAPI !== 'cli'"), 'Snapshot CLI does not reject web execution');
} finally {
    snapshotRemoveTree($testRoot);
}

echo "ProductionContentSnapshotService tests passed\n";
