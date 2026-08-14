<?php

require_once __DIR__ . '/../public/api/admin/Settings.php';
require_once __DIR__ . '/../public/api/admin/publication/PublicationException.php';
require_once __DIR__ . '/../public/api/admin/publication/PublicationTimestamp.php';
require_once __DIR__ . '/../public/api/admin/publication/PublicationSemanticHasher.php';
require_once __DIR__ . '/../public/api/admin/publication/PlaylistPublicationSerializer.php';
require_once __DIR__ . '/../public/api/admin/publication/ConfigPublicationSerializer.php';
require_once __DIR__ . '/../public/api/admin/publication/PublicationStatusService.php';

use FreeTV\Admin\Publication\ConfigPublicationSerializer;
use FreeTV\Admin\Publication\PlaylistPublicationSerializer;
use FreeTV\Admin\Publication\PublicationStatusService;

function assertStatusSame($expected, $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException(
            $message . "\nExpected: " . var_export($expected, true)
            . "\nActual: " . var_export($actual, true)
        );
    }
}

function writeStatusJson(string $path, array $artifact): void
{
    $json = json_encode($artifact, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    if (file_put_contents($path, $json) !== strlen($json)) {
        throw new RuntimeException('Could not write status fixture');
    }
}

function statusFileHashes(string $root): array
{
    $hashes = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($iterator as $item) {
        if ($item->isFile()) {
            $relative = substr($item->getPathname(), strlen($root) + 1);
            $hashes[$relative] = hash_file('sha256', $item->getPathname());
        }
    }
    ksort($hashes);
    return $hashes;
}

$testRoot = sys_get_temp_dir() . '/freetv-publication-status-test-' . bin2hex(random_bytes(8));
$playlistDirectory = $testRoot . '/playlists';
if (!mkdir($playlistDirectory, 0700, true)) {
    throw new RuntimeException('Could not create publication status test directory');
}

$basePlaylist = [
    'id' => 1,
    'filename' => 'freetv.json',
    'dbtitle' => 'FreeTV',
    'dbversion' => '1.0',
    'author' => 'Free TV',
    'email' => 'support@example.test',
    'link' => 'https://example.test',
    'is_default' => 1,
    'sort_order' => 0,
];
$baseShows = [
    [
        'id' => 1,
        'sort_order' => 0,
        'category' => 'comedy',
        'status' => 'active',
        'identifier' => 'first-show',
        'title' => 'First Show',
        'description' => 'First description',
        'start_year' => '1960',
        'end_year' => '1961',
        'imdb' => 'tt0000001',
        'group_name' => null,
    ],
    [
        'id' => 2,
        'sort_order' => 1,
        'category' => 'drama',
        'status' => 'active',
        'identifier' => 'second-show',
        'title' => 'Second Show',
        'description' => 'Second description',
        'start_year' => '1970',
        'end_year' => '1971',
        'imdb' => 'tt0000002',
        'group_name' => 'Evening',
    ],
];
$playlistRows = [$basePlaylist];
$showsByPlaylist = [1 => $baseShows];
$settings = ['show_ads' => false];
$readCounts = ['playlists' => 0, 'shows' => 0, 'settings' => 0];
$service = new PublicationStatusService(
    $testRoot,
    static function () use (&$playlistRows, &$readCounts): array {
        $readCounts['playlists']++;
        return $playlistRows;
    },
    static function (int $playlistId) use (&$showsByPlaylist, &$readCounts): array {
        $readCounts['shows']++;
        return $showsByPlaylist[$playlistId] ?? [];
    },
    static function () use (&$settings, &$readCounts): array {
        $readCounts['settings']++;
        return $settings;
    }
);

try {
    $publishedPlaylist = PlaylistPublicationSerializer::serialize(
        $basePlaylist,
        $baseShows,
        '2026-08-01T09:00:00.000Z'
    );
    $publishedConfig = ConfigPublicationSerializer::serialize(
        ['show_ads' => false],
        '2026-08-01T10:00:00.000Z'
    );
    $publishedIndex = [
        'default' => 'freetv.json',
        'playlists' => [[
            'filename' => 'freetv.json',
            'dbtitle' => 'FreeTV',
            'lastupdated' => '2026-08-01T09:00:00.000Z',
            'author' => 'Free TV',
        ]],
    ];
    writeStatusJson($playlistDirectory . '/freetv.json', $publishedPlaylist);
    writeStatusJson($playlistDirectory . '/index.json', $publishedIndex);
    writeStatusJson($testRoot . '/config.json', $publishedConfig);

    $baseline = $service->status();
    assertStatusSame(false, $baseline['playlists'][0]['changed'],
        'Different playlist lastupdated incorrectly produced changed status');
    assertStatusSame(false, $baseline['config']['changed'],
        'Different config lastupdated incorrectly produced changed status');
    assertStatusSame(false, $baseline['default_playlist']['changed'],
        'Matching default playlist incorrectly produced changed status');

    $showsByPlaylist[1][0]['title'] = 'Edited First Show';
    assertStatusSame(true, $service->status()['playlists'][0]['changed'],
        'Changed show title was not detected');
    $showsByPlaylist[1] = $baseShows;

    $showsByPlaylist[1][] = array_merge($baseShows[1], [
        'id' => 3,
        'sort_order' => 2,
        'identifier' => 'third-show',
        'title' => 'Third Show',
    ]);
    assertStatusSame(true, $service->status()['playlists'][0]['changed'], 'Added show was not detected');
    $showsByPlaylist[1] = [$baseShows[0]];
    assertStatusSame(true, $service->status()['playlists'][0]['changed'], 'Removed show was not detected');

    $showsByPlaylist[1] = $baseShows;
    $showsByPlaylist[1][0]['sort_order'] = 1;
    $showsByPlaylist[1][1]['sort_order'] = 0;
    assertStatusSame(true, $service->status()['playlists'][0]['changed'], 'Show reorder was not detected');
    $showsByPlaylist[1] = $baseShows;

    foreach (['dbversion', 'author', 'email', 'link', 'dbtitle'] as $field) {
        $playlistRows[0] = $basePlaylist;
        $playlistRows[0][$field] = 'Changed ' . $field;
        assertStatusSame(true, $service->status()['playlists'][0]['changed'],
            "Changed {$field} was not detected");
    }
    $playlistRows[0] = $basePlaylist;

    unlink($playlistDirectory . '/freetv.json');
    assertStatusSame(true, $service->status()['playlists'][0]['changed'],
        'Missing playlist was not marked unpublished');
    file_put_contents($playlistDirectory . '/freetv.json', '{invalid');
    $malformedPlaylist = $service->status()['playlists'][0];
    assertStatusSame(null, $malformedPlaylist['changed'], 'Malformed playlist was not an error state');
    assertStatusSame(true, is_string($malformedPlaylist['error']), 'Malformed playlist error was not explicit');
    writeStatusJson($playlistDirectory . '/freetv.json', $publishedPlaylist);

    $settings['show_ads'] = true;
    assertStatusSame(true, $service->status()['config']['changed'], 'Changed show_ads was not detected');
    $settings['show_ads'] = false;
    unlink($testRoot . '/config.json');
    assertStatusSame(true, $service->status()['config']['changed'],
        'Missing config was not marked unpublished');
    file_put_contents($testRoot . '/config.json', '{invalid');
    $malformedConfig = $service->status()['config'];
    assertStatusSame(null, $malformedConfig['changed'], 'Malformed config was not an error state');
    assertStatusSame(true, is_string($malformedConfig['error']), 'Malformed config error was not explicit');
    writeStatusJson($testRoot . '/config.json', $publishedConfig);

    $publishedIndex['default'] = 'other.json';
    writeStatusJson($playlistDirectory . '/index.json', $publishedIndex);
    assertStatusSame(true, $service->status()['default_playlist']['changed'],
        'Changed default playlist was not detected');
    writeStatusJson($playlistDirectory . '/index.json', [
        'default' => 'freetv.json',
        'playlists' => $publishedIndex['playlists'],
    ]);

    unlink($playlistDirectory . '/index.json');
    $missingIndex = $service->status()['default_playlist'];
    assertStatusSame(null, $missingIndex['changed'], 'Missing index was not a critical error');
    assertStatusSame(true, is_string($missingIndex['error']), 'Missing index error was not explicit');
    file_put_contents($playlistDirectory . '/index.json', '{invalid');
    $malformedIndex = $service->status()['default_playlist'];
    assertStatusSame(null, $malformedIndex['changed'], 'Malformed index was not a critical error');
    assertStatusSame(true, is_string($malformedIndex['error']), 'Malformed index error was not explicit');
    writeStatusJson($playlistDirectory . '/index.json', ['default' => 'freetv.json']);
    assertStatusSame(null, $service->status()['default_playlist']['changed'],
        'Structurally malformed index was not a critical error');
    writeStatusJson($playlistDirectory . '/index.json', [
        'default' => 'freetv.json',
        'playlists' => $publishedIndex['playlists'],
    ]);

    $hashesBefore = statusFileHashes($testRoot);
    $firstRepeatedStatus = $service->status();
    $secondRepeatedStatus = $service->status();
    assertStatusSame($firstRepeatedStatus, $secondRepeatedStatus,
        'Repeated publication status checks were not deterministic');
    assertStatusSame($hashesBefore, statusFileHashes($testRoot),
        'Publication status check wrote to the filesystem');
    assertStatusSame(true, $readCounts['playlists'] > 0 && $readCounts['shows'] > 0
        && $readCounts['settings'] > 0, 'Status service did not use its read-only data loaders');
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

echo "PublicationStatusService tests passed\n";
