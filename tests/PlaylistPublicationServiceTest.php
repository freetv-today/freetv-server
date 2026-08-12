<?php

require_once __DIR__ . '/../public/api/admin/publication/PublicationException.php';
require_once __DIR__ . '/../public/api/admin/publication/PublicationTimestamp.php';
require_once __DIR__ . '/../public/api/admin/publication/PlaylistPublicationSerializer.php';
require_once __DIR__ . '/../public/api/admin/publication/PlaylistIndexSerializer.php';
require_once __DIR__ . '/../public/api/admin/publication/PlaylistPublicationService.php';

use FreeTV\Admin\Publication\PlaylistPublicationService;
use FreeTV\Admin\Publication\PublicationException;

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

$testRoot = sys_get_temp_dir() . '/freetv-publication-test-' . bin2hex(random_bytes(8));
if (!mkdir($testRoot, 0700, true)) {
    throw new RuntimeException('Could not create publication test directory');
}

$playlists = [
    [
        'id' => 1,
        'filename' => 'default.json',
        'dbtitle' => 'Default',
        'dbversion' => '1.0',
        'author' => 'Free TV',
        'email' => 'support@example.test',
        'link' => 'https://example.test',
        'lastupdated' => '2026-08-01 12:00:00',
        'is_default' => 1,
        'sort_order' => 0,
    ],
    [
        'id' => 2,
        'filename' => 'selected.json',
        'dbtitle' => 'Selected',
        'dbversion' => '1.0',
        'author' => 'Free TV',
        'email' => 'support@example.test',
        'link' => 'https://example.test',
        'lastupdated' => '2026-08-02 12:00:00',
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
$update = null;

try {
    $service = new PlaylistPublicationService(
        $testRoot,
        static fn() => $playlists,
        static fn(int $playlistId) => $playlistId === 2 ? $shows : [],
        static function (int $playlistId, string $timestamp) use (&$update, $testRoot): void {
            if (!is_file($testRoot . '/playlists/selected.json')
                || !is_file($testRoot . '/playlists/index.json')) {
                throw new RuntimeException('Database timestamp updated before both artifacts existed');
            }
            $update = [$playlistId, $timestamp];
        },
        static fn() => new DateTimeImmutable('2026-08-12T16:30:00.987Z')
    );

    $result = $service->publish('selected.json');
    $playlistPath = $testRoot . '/playlists/selected.json';
    $indexPath = $testRoot . '/playlists/index.json';
    $playlistArtifact = json_decode(file_get_contents($playlistPath), true, 512, JSON_THROW_ON_ERROR);
    $indexArtifact = json_decode(file_get_contents($indexPath), true, 512, JSON_THROW_ON_ERROR);
    $selectedIndexEntry = array_values(array_filter(
        $indexArtifact['playlists'],
        static fn(array $entry): bool => $entry['filename'] === 'selected.json'
    ))[0];

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
        $selectedIndexEntry['lastupdated'],
        'Playlist and matching index timestamps are not identical'
    );
    assertPublicationServiceSame(
        [2, '2026-08-12 16:30:00'],
        $update,
        'Selected playlist database timestamp was not updated after publication'
    );

    expectPublicationFailure(
        fn() => $service->publish('../selected.json'),
        400,
        'Unsafe playlist filename was accepted'
    );
    expectPublicationFailure(
        fn() => $service->publish('missing.json'),
        404,
        'Missing playlist did not fail clearly'
    );
} finally {
    $playlistDirectory = $testRoot . '/playlists';
    if (is_dir($playlistDirectory)) {
        foreach (glob($playlistDirectory . '/*') ?: [] as $path) {
            unlink($path);
        }
        rmdir($playlistDirectory);
    }
    rmdir($testRoot);
}

echo "PlaylistPublicationService tests passed\n";
