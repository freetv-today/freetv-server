<?php

require_once __DIR__ . '/../public/api/admin/publication/PublicationException.php';
require_once __DIR__ . '/../public/api/admin/publication/PublicationTimestamp.php';
require_once __DIR__ . '/../public/api/admin/publication/PlaylistPublicationSerializer.php';
require_once __DIR__ . '/../public/api/admin/publication/PlaylistIndexSerializer.php';
require_once __DIR__ . '/../public/api/admin/publication/PlaylistPublicationService.php';
require_once __DIR__ . '/../public/api/admin/publication/PublicationUndoService.php';

use FreeTV\Admin\Publication\PlaylistPublicationService;
use FreeTV\Admin\Publication\PublicationException;
use FreeTV\Admin\Publication\PublicationUndoService;

function assertPublicationServiceSame($expected, $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException(
            $message . "\nExpected: " . var_export($expected, true)
            . "\nActual: " . var_export($actual, true)
        );
    }
}

function expectPublicationFailure(callable $callback, int $status, string $message): void
{
    try {
        $callback();
    } catch (PublicationException $exception) {
        assertPublicationServiceSame($status, $exception->getHttpStatus(), $message);
        return;
    }

    throw new RuntimeException($message . ': expected PublicationException');
}

function writePublishedIndex(string $path, array $index): void
{
    $json = json_encode($index, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    if (file_put_contents($path, $json) === false) {
        throw new RuntimeException('Could not write publication index fixture');
    }
}

$testRoot = sys_get_temp_dir() . '/freetv-publication-test-' . bin2hex(random_bytes(8));
$playlistDirectory = $testRoot . '/playlists';
if (!mkdir($playlistDirectory, 0700, true)) {
    throw new RuntimeException('Could not create publication test directory');
}

$playlists = [
    [
        'id' => 1,
        'filename' => 'british.json',
        'dbtitle' => 'British',
        'dbversion' => '1.0',
        'author' => 'Free TV',
        'email' => 'support@example.test',
        'link' => 'https://example.test',
        'lastupdated' => '2026-08-12 11:00:00',
        'is_default' => 1,
        'sort_order' => 0,
    ],
    [
        'id' => 2,
        'filename' => 'freetv.json',
        'dbtitle' => 'FreeTV',
        'dbversion' => '1.0',
        'author' => 'Free TV',
        'email' => 'support@example.test',
        'link' => 'https://example.test',
        'lastupdated' => '2026-08-12 10:00:00',
        'is_default' => 0,
        'sort_order' => 1,
    ],
];
$shows = [[
    'id' => 1,
    'sort_order' => 0,
    'category' => 'comedy',
    'status' => 'active',
    'identifier' => 'fixture-show',
    'title' => 'Fixture Show',
    'description' => 'A fixture',
    'start_year' => '1960',
    'end_year' => '1961',
    'imdb' => 'tt0000001',
    'group_name' => null,
]];
$publishedIndex = [
    'default' => 'freetv.json',
    'playlists' => [
        [
            'filename' => 'british.json',
            'dbtitle' => 'British',
            'lastupdated' => '2026-08-12T09:00:00.000Z',
            'author' => 'Free TV',
        ],
        [
            'filename' => 'freetv.json',
            'dbtitle' => 'FreeTV',
            'lastupdated' => '2026-08-12T08:00:00.000Z',
            'author' => 'Free TV',
        ],
    ],
];
$indexPath = $playlistDirectory . '/index.json';
$update = null;

try {
    writePublishedIndex($indexPath, $publishedIndex);
    file_put_contents(
        $playlistDirectory . '/freetv.json',
        json_encode([
            'lastupdated' => '2026-08-12T08:00:00.000Z',
            'dbtitle' => 'FreeTV',
            'dbversion' => '1.0',
            'author' => 'Free TV',
            'email' => 'support@example.test',
            'link' => 'https://example.test',
            'shows' => [],
        ], JSON_THROW_ON_ERROR)
    );
    $undoService = new PublicationUndoService(
        $testRoot,
        $testRoot . '/undo',
        static function (): void {}
    );
    $service = new PlaylistPublicationService(
        $testRoot,
        static fn() => $playlists,
        static fn(int $playlistId) => $playlistId === 2 ? $shows : [],
        static function (int $playlistId, string $timestamp) use (&$update, $testRoot): void {
            if (!is_file($testRoot . '/playlists/freetv.json')
                || !is_file($testRoot . '/playlists/index.json')) {
                throw new RuntimeException('Database timestamp updated before both artifacts existed');
            }
            $update = [$playlistId, $timestamp];
        },
        static fn() => new DateTimeImmutable('2026-08-12T16:30:00.987Z'),
        $undoService
    );

    $result = $service->publish('freetv.json');
    $playlistPath = $playlistDirectory . '/freetv.json';
    $playlistArtifact = json_decode(file_get_contents($playlistPath), true, 512, JSON_THROW_ON_ERROR);
    $indexArtifact = json_decode(file_get_contents($indexPath), true, 512, JSON_THROW_ON_ERROR);
    $entries = array_column($indexArtifact['playlists'], null, 'filename');

    assertPublicationServiceSame(true, is_file($playlistPath), 'Selected playlist file was not created');
    assertPublicationServiceSame(true, is_file($indexPath), 'Playlist index file was not created');
    assertPublicationServiceSame(
        0644,
        fileperms($playlistPath) & 0777,
        'Published playlist permissions do not allow static serving'
    );
    assertPublicationServiceSame(
        '2026-08-12T16:30:00.000Z',
        $result['lastupdated'],
        'Operation timestamp was not canonicalized to database precision'
    );
    assertPublicationServiceSame(
        $playlistArtifact['lastupdated'],
        $entries['freetv.json']['lastupdated'],
        'Playlist and matching index timestamps are not identical'
    );
    assertPublicationServiceSame(
        '2026-08-12T09:00:00.000Z',
        $entries['british.json']['lastupdated'],
        'Unselected timestamp came from changed MariaDB data instead of the published index'
    );
    assertPublicationServiceSame(
        'british.json',
        $indexArtifact['default'],
        'Regenerated default did not reflect the MariaDB default'
    );
    assertPublicationServiceSame(
        [2, '2026-08-12 16:30:00'],
        $update,
        'Selected playlist database timestamp was not updated after publication'
    );
    $update = null;

    $publishedPlaylistBeforeFailure = file_get_contents($playlistPath);
    $publishedIndexBeforeFailure = file_get_contents($indexPath);
    $undoStatusBeforeFailure = $undoService->status();
    $failingService = new PlaylistPublicationService(
        $testRoot,
        static fn() => $playlists,
        static fn(int $playlistId) => $playlistId === 2 ? $shows : [],
        static function (): void {
            throw new RuntimeException('Simulated timestamp update failure');
        },
        static fn() => new DateTimeImmutable('2026-08-12T17:00:00Z'),
        $undoService
    );
    expectPublicationFailure(
        fn() => $failingService->publish('freetv.json'),
        500,
        'Failed playlist publication did not report failure'
    );
    assertPublicationServiceSame(
        $publishedPlaylistBeforeFailure,
        file_get_contents($playlistPath),
        'Failed playlist publication did not restore the prior playlist'
    );
    assertPublicationServiceSame(
        $publishedIndexBeforeFailure,
        file_get_contents($indexPath),
        'Failed playlist publication did not restore the prior index'
    );
    assertPublicationServiceSame(
        $undoStatusBeforeFailure,
        $undoService->status(),
        'Failed playlist publication replaced the previous Undo slot'
    );

    expectPublicationFailure(
        fn() => $service->publish('../freetv.json'),
        400,
        'Unsafe playlist filename was accepted'
    );
    expectPublicationFailure(
        fn() => $service->publish('missing.json'),
        404,
        'Missing playlist did not fail clearly'
    );

    unlink($indexPath);
    expectPublicationFailure(
        fn() => $service->publish('freetv.json'),
        409,
        'Missing existing published index did not fail'
    );

    file_put_contents($indexPath, '{invalid json');
    expectPublicationFailure(
        fn() => $service->publish('freetv.json'),
        409,
        'Invalid existing published index JSON did not fail'
    );

    $missingBritish = $publishedIndex;
    $missingBritish['playlists'] = [$publishedIndex['playlists'][1]];
    writePublishedIndex($indexPath, $missingBritish);
    expectPublicationFailure(
        fn() => $service->publish('freetv.json'),
        409,
        'Missing unselected playlist entry did not fail'
    );

    $duplicateBritish = $publishedIndex;
    $duplicateBritish['playlists'][] = $publishedIndex['playlists'][0];
    writePublishedIndex($indexPath, $duplicateBritish);
    expectPublicationFailure(
        fn() => $service->publish('freetv.json'),
        409,
        'Duplicate existing playlist filename did not fail'
    );

    $invalidBritishTimestamp = $publishedIndex;
    $invalidBritishTimestamp['playlists'][0]['lastupdated'] = 'not-a-timestamp';
    writePublishedIndex($indexPath, $invalidBritishTimestamp);
    expectPublicationFailure(
        fn() => $service->publish('freetv.json'),
        409,
        'Invalid unselected playlist timestamp did not fail'
    );
    assertPublicationServiceSame(
        null,
        $update,
        'A failed publication updated the MariaDB publication timestamp'
    );
} finally {
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($testRoot, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iterator as $item) {
        $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }
    rmdir($testRoot);
}

echo "PlaylistPublicationService tests passed\n";
