<?php

declare(strict_types=1);

require_once __DIR__ . '/../public/api/admin/ThumbnailOrphanCleanupService.php';

use FreeTV\Admin\ServerPaths;
use FreeTV\Admin\ThumbnailOrphanCleanupException;
use FreeTV\Admin\ThumbnailOrphanCleanupService;

function orphanCleanupAssertSame(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message . "\nExpected: " . var_export($expected, true)
            . "\nActual: " . var_export($actual, true));
    }
}

function orphanCleanupTreeState(string $root): array
{
    if (!is_dir($root)) {
        return [];
    }
    $state = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($iterator as $item) {
        if ($item->isFile() && !$item->isLink()) {
            $relative = substr($item->getPathname(), strlen($root) + 1);
            $state[$relative] = hash_file('sha256', $item->getPathname());
        } elseif ($item->isLink()) {
            $relative = substr($item->getPathname(), strlen($root) + 1);
            $state[$relative] = 'symlink:' . readlink($item->getPathname());
        }
    }
    ksort($state, SORT_STRING);
    return $state;
}

function orphanCleanupRemoveTree(string $root): void
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

function orphanCleanupExpectFailure(callable $operation, string $expectedMessage): void
{
    try {
        $operation();
    } catch (ThumbnailOrphanCleanupException $exception) {
        if (!str_contains($exception->getMessage(), $expectedMessage)) {
            throw new RuntimeException('Unexpected cleanup failure: ' . $exception->getMessage());
        }
        return;
    }
    throw new RuntimeException('Expected thumbnail orphan cleanup failure');
}

$root = sys_get_temp_dir() . '/freetv-thumbnail-orphan-cleanup-test-' . bin2hex(random_bytes(8));
$thumbs = $root . '/public/thumbs';
if (!mkdir($thumbs, 0700, true)) {
    throw new RuntimeException('Could not create thumbnail cleanup test fixture');
}
$paths = new ServerPaths($root);
$audit = [
    'summary' => [
        'total_distinct_valid_imdb_references' => 3,
        'total_thumbnail_jpg_files_considered' => 6,
        'present' => 2,
        'missing' => 1,
        'orphaned' => 5,
        'invalid_database_imdb_values' => 0,
        'invalid_database_imdb_rows' => 0,
    ],
    'present' => [
        [
            'imdb' => 'tt0000300',
            'filename' => 'tt0000300.jpg',
            'usage' => ['show_count' => 2, 'playlist_count' => 2],
        ],
    ],
    'missing' => [
        [
            'imdb' => 'tt0000700',
            'filename' => 'tt0000700.jpg',
            'usage' => ['show_count' => 1, 'playlist_count' => 1],
        ],
    ],
    'orphaned' => [
        ['imdb' => 'tt0000600', 'filename' => 'tt0000600.jpg'],
        ['imdb' => 'tt0000500', 'filename' => 'tt0000500.jpg'],
        ['imdb' => 'tt0000400', 'filename' => 'tt0000400.jpg'],
        ['imdb' => 'tt0000200', 'filename' => 'tt0000200.jpg'],
        ['imdb' => 'tt0000100', 'filename' => 'tt0000100.jpg'],
    ],
    'invalid_database_imdb' => [],
];

try {
    file_put_contents($thumbs . '/tt0000100.jpg', 'move this orphan');
    file_put_contents($thumbs . '/tt0000200.jpg', 'becomes referenced');
    file_put_contents($thumbs . '/tt0000300.jpg', 'shared referenced thumbnail');
    file_put_contents($thumbs . '/tt0000400.jpg', 'becomes unsafe symlink');
    file_put_contents($thumbs . '/tt0000500.jpg', 'destination collision');
    file_put_contents($thumbs . '/tt0000600.jpg', 'move failure source');
    file_put_contents($thumbs . '/index.html', 'support file');
    file_put_contents($root . '/outside.jpg', 'outside symlink target');

    $fixedClock = static fn(): DateTimeImmutable =>
        new DateTimeImmutable('2026-08-29T13:00:00.123Z');
    $auditLoader = static fn(): array => $audit;
    $referenceChecks = [];
    $databaseRows = [
        ['imdb' => 'tt0000200'],
        ['imdb' => 'tt0000300'],
        ['imdb' => 'tt0000300'],
    ];
    $databaseRowsBefore = $databaseRows;
    $referenceChecker = static function (string $imdb) use (
        &$referenceChecks,
        $root,
        $databaseRows
    ): bool {
        $referenceChecks[] = $imdb;
        if ($imdb === 'tt0000500') {
            $batch = $root . '/temp/thumbnail-quarantine/20260829T130000Z';
            file_put_contents($batch . '/tt0000500.jpg', 'existing quarantine evidence');
        }
        if ($imdb === 'tt0000400') {
            $source = $root . '/public/thumbs/tt0000400.jpg';
            unlink($source);
            symlink($root . '/outside.jpg', $source);
        }
        return in_array($imdb, array_column($databaseRows, 'imdb'), true);
    };

    $dryRunService = new ThumbnailOrphanCleanupService(
        $paths,
        $auditLoader,
        $referenceChecker,
        $fixedClock
    );
    $treeBeforeDryRun = orphanCleanupTreeState($root);
    $dryRun = $dryRunService->run();
    orphanCleanupAssertSame('dry-run', $dryRun['mode'], 'Default cleanup mode is not dry-run');
    orphanCleanupAssertSame(5, $dryRun['orphan_count'], 'Dry run orphan count is incorrect');
    orphanCleanupAssertSame(
        ['tt0000100.jpg', 'tt0000200.jpg', 'tt0000400.jpg', 'tt0000500.jpg', 'tt0000600.jpg'],
        array_column($dryRun['candidates'], 'filename'),
        'Dry run candidates are not deterministic'
    );
    orphanCleanupAssertSame([], $referenceChecks, 'Dry run performed database reference rechecks');
    orphanCleanupAssertSame($treeBeforeDryRun, orphanCleanupTreeState($root),
        'Dry run mutated the filesystem');
    orphanCleanupAssertSame(false, is_dir($root . '/temp'), 'Dry run created private state');

    $fileMover = static function (string $source, string $destination): bool {
        if (basename($source) === 'tt0000600.jpg') {
            return false;
        }
        return rename($source, $destination);
    };
    $applyService = new ThumbnailOrphanCleanupService(
        $paths,
        $auditLoader,
        $referenceChecker,
        $fixedClock,
        $fileMover
    );
    $apply = $applyService->run(true);
    $batch = $root . '/temp/thumbnail-quarantine/20260829T130000Z';
    orphanCleanupAssertSame(['tt0000100.jpg'], array_column($apply['moved'], 'filename'),
        'Apply did not move exactly the safe orphan');
    orphanCleanupAssertSame(['tt0000200.jpg'], array_column($apply['skipped'], 'filename'),
        'Newly referenced orphan was not skipped');
    orphanCleanupAssertSame(
        ['unsafe_or_missing_source', 'destination_exists', 'move_failed'],
        array_column($apply['failed'], 'reason'),
        'Partial failures were not reported deterministically'
    );
    orphanCleanupAssertSame(false, file_exists($thumbs . '/tt0000100.jpg'),
        'Successfully moved orphan remains in public thumbnails');
    orphanCleanupAssertSame('move this orphan', file_get_contents($batch . '/tt0000100.jpg'),
        'Quarantined thumbnail bytes changed');
    foreach (['tt0000200.jpg', 'tt0000300.jpg', 'tt0000500.jpg', 'tt0000600.jpg', 'index.html'] as $filename) {
        orphanCleanupAssertSame(true, file_exists($thumbs . '/' . $filename),
            'Cleanup removed an unapproved source file: ' . $filename);
    }
    orphanCleanupAssertSame(true, is_link($thumbs . '/tt0000400.jpg'),
        'Cleanup moved or followed an unrelated thumbnail symlink');
    orphanCleanupAssertSame(
        ['tt0000100', 'tt0000200', 'tt0000400', 'tt0000500', 'tt0000600'],
        $referenceChecks,
        'Apply reference checks are missing or not deterministic'
    );
    orphanCleanupAssertSame($databaseRowsBefore, $databaseRows,
        'Cleanup mutated the database fixture');

    $manifestPath = $batch . '/manifest.json';
    $manifest = json_decode(
        (string) file_get_contents($manifestPath),
        true,
        512,
        JSON_THROW_ON_ERROR
    );
    orphanCleanupAssertSame(1, $manifest['format_version'], 'Manifest format version is incorrect');
    orphanCleanupAssertSame('2026-08-29T13:00:00.123Z', $manifest['created_at'],
        'Manifest created_at is incorrect');
    orphanCleanupAssertSame(1, $manifest['moved_count'], 'Manifest moved count is incorrect');
    orphanCleanupAssertSame(1, $manifest['skipped_count'], 'Manifest skipped count is incorrect');
    orphanCleanupAssertSame(3, $manifest['failed_count'], 'Manifest failed count is incorrect');
    orphanCleanupAssertSame(hash('sha256', 'move this orphan'), $manifest['files'][0]['sha256'],
        'Manifest SHA-256 does not identify moved bytes');
    orphanCleanupAssertSame($manifestPath, $apply['manifest'], 'Apply manifest path is incorrect');
    orphanCleanupAssertSame(0700, fileperms($batch) & 0777, 'Quarantine batch is not private');
    orphanCleanupAssertSame(0600, fileperms($manifestPath) & 0777, 'Quarantine manifest is not private');

    $sourcesBeforeCollision = orphanCleanupTreeState($root . '/public');
    orphanCleanupExpectFailure(
        fn() => $applyService->run(true),
        'batch directory already exists'
    );
    orphanCleanupAssertSame($sourcesBeforeCollision, orphanCleanupTreeState($root . '/public'),
        'Existing batch collision modified public thumbnails');
} finally {
    orphanCleanupRemoveTree($root);
}

echo "ThumbnailOrphanCleanupService tests passed\n";
