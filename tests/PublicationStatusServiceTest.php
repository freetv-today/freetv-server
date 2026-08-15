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

function assertStatusError(array $status, string $message): void
{
    assertStatusSame(null, $status['changed'], $message);
    assertStatusSame(true, is_string($status['error']) && $status['error'] !== '',
        $message . ' did not include an explicit error');
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

    assertStatusSame(false, array_key_exists('filename', $publishedPlaylist),
        'Playlist serializer unexpectedly emitted a top-level filename');
    $baseline = $service->status();
    assertStatusSame(false, $baseline['playlists'][0]['changed'],
        'Different playlist lastupdated incorrectly produced changed status');
    assertStatusSame(false, $baseline['config']['changed'],
        'Different config lastupdated incorrectly produced changed status');
    assertStatusSame(false, $baseline['default_playlist']['changed'],
        'Matching default playlist incorrectly produced changed status');
    assertStatusSame([
        'shows_added' => 0,
        'shows_removed' => 0,
        'shows_edited' => 0,
        'order_changed' => false,
        'metadata_changed' => false,
        'metadata_fields' => [],
    ], $baseline['playlists'][0]['delta'], 'Matching playlist produced an unexpected delta');
    assertStatusSame([], $baseline['config']['delta']['fields'],
        'Matching config produced an unexpected field delta');

    $showsByPlaylist[1][0]['title'] = 'Edited First Show';
    $editedShowStatus = $service->status()['playlists'][0];
    assertStatusSame(true, $editedShowStatus['changed'],
        'Changed show title was not detected');
    assertStatusSame(1, $editedShowStatus['delta']['shows_edited'],
        'Changed show title was not detailed as an edit');
    $showsByPlaylist[1] = $baseShows;

    $showsByPlaylist[1][] = array_merge($baseShows[1], [
        'id' => 3,
        'sort_order' => 2,
        'identifier' => 'third-show',
        'title' => 'Third Show',
    ]);
    $addedShowStatus = $service->status()['playlists'][0];
    assertStatusSame(true, $addedShowStatus['changed'], 'Added show was not detected');
    assertStatusSame(1, $addedShowStatus['delta']['shows_added'], 'Added show was not detailed');
    $showsByPlaylist[1] = [$baseShows[0]];
    $removedShowStatus = $service->status()['playlists'][0];
    assertStatusSame(true, $removedShowStatus['changed'], 'Removed show was not detected');
    assertStatusSame(1, $removedShowStatus['delta']['shows_removed'], 'Removed show was not detailed');

    $showsByPlaylist[1] = $baseShows;
    $showsByPlaylist[1][0]['sort_order'] = 1;
    $showsByPlaylist[1][1]['sort_order'] = 0;
    $reorderedStatus = $service->status()['playlists'][0];
    assertStatusSame(true, $reorderedStatus['changed'], 'Show reorder was not detected');
    assertStatusSame(true, $reorderedStatus['delta']['order_changed'], 'Show reorder was not detailed');
    assertStatusSame(0, $reorderedStatus['delta']['shows_edited'], 'Show reorder was detailed as an edit');
    $showsByPlaylist[1] = $baseShows;

    foreach (['dbversion', 'author', 'email', 'link', 'dbtitle'] as $field) {
        $playlistRows[0] = $basePlaylist;
        $playlistRows[0][$field] = 'Changed ' . $field;
        $metadataStatus = $service->status()['playlists'][0];
        assertStatusSame(true, $metadataStatus['changed'],
            "Changed {$field} was not detected");
        assertStatusSame([$field], $metadataStatus['delta']['metadata_fields'],
            "Changed {$field} was not detailed as playlist metadata");
    }
    $playlistRows[0] = $basePlaylist;

    unlink($playlistDirectory . '/freetv.json');
    $missingPlaylist = $service->status()['playlists'][0];
    assertStatusSame(true, $missingPlaylist['changed'],
        'Missing playlist was not marked unpublished');
    assertStatusSame(false, array_key_exists('delta', $missingPlaylist),
        'Missing playlist invented delta counts');
    file_put_contents($playlistDirectory . '/freetv.json', '{invalid');
    $malformedPlaylist = $service->status()['playlists'][0];
    assertStatusError($malformedPlaylist, 'Malformed playlist was not an error state');
    assertStatusSame(false, array_key_exists('delta', $malformedPlaylist),
        'Malformed playlist included a misleading delta');

    file_put_contents($playlistDirectory . '/freetv.json', '{}');
    assertStatusError($service->status()['playlists'][0],
        'Empty playlist object was not a structural error');

    $invalidPlaylist = $publishedPlaylist;
    unset($invalidPlaylist['dbtitle']);
    writeStatusJson($playlistDirectory . '/freetv.json', $invalidPlaylist);
    assertStatusError($service->status()['playlists'][0],
        'Playlist missing a required top-level field was not a structural error');

    $invalidPlaylist = $publishedPlaylist;
    $invalidPlaylist['filename'] = 'freetv.json';
    writeStatusJson($playlistDirectory . '/freetv.json', $invalidPlaylist);
    assertStatusError($service->status()['playlists'][0],
        'Playlist with an unexpected top-level filename was not a structural error');

    $invalidPlaylist = $publishedPlaylist;
    unset($invalidPlaylist['shows']);
    writeStatusJson($playlistDirectory . '/freetv.json', $invalidPlaylist);
    assertStatusError($service->status()['playlists'][0],
        'Playlist missing shows was not a structural error');

    $invalidPlaylist = $publishedPlaylist;
    $invalidPlaylist['shows'] = 'not-an-array';
    writeStatusJson($playlistDirectory . '/freetv.json', $invalidPlaylist);
    assertStatusError($service->status()['playlists'][0],
        'Playlist with a non-array shows value was not a structural error');

    $invalidPlaylist = $publishedPlaylist;
    $invalidPlaylist['shows'][0]['title'] = false;
    writeStatusJson($playlistDirectory . '/freetv.json', $invalidPlaylist);
    assertStatusError($service->status()['playlists'][0],
        'Playlist with a malformed show was not a structural error');

    $duplicatePlaylist = $publishedPlaylist;
    $duplicatePlaylist['shows'][] = $duplicatePlaylist['shows'][0];
    writeStatusJson($playlistDirectory . '/freetv.json', $duplicatePlaylist);
    $duplicateStatus = $service->status()['playlists'][0];
    assertStatusError($duplicateStatus, 'Duplicate show identifier was not an explicit status error');
    assertStatusSame(false, array_key_exists('delta', $duplicateStatus),
        'Duplicate show identifier produced an ambiguous delta');

    $validChangedPlaylist = $publishedPlaylist;
    unset($validChangedPlaylist['shows'][1]['group']);
    writeStatusJson($playlistDirectory . '/freetv.json', $validChangedPlaylist);
    $optionalGroupStatus = $service->status()['playlists'][0];
    assertStatusSame(true, $optionalGroupStatus['changed'],
        'Valid playlist with an omitted optional group was not marked changed');
    assertStatusSame(null, $optionalGroupStatus['error'],
        'Omitted optional show group was treated as a structural error');
    writeStatusJson($playlistDirectory . '/freetv.json', $publishedPlaylist);

    $settings['show_ads'] = true;
    assertStatusSame(true, $service->status()['config']['changed'], 'Changed show_ads was not detected');
    $settings['show_ads'] = false;
    unlink($testRoot . '/config.json');
    $missingConfig = $service->status()['config'];
    assertStatusSame(true, $missingConfig['changed'],
        'Missing config was not marked unpublished');
    assertStatusSame(false, array_key_exists('delta', $missingConfig),
        'Missing config invented a field delta');
    file_put_contents($testRoot . '/config.json', '{invalid');
    $malformedConfig = $service->status()['config'];
    assertStatusError($malformedConfig, 'Malformed config was not an error state');
    assertStatusSame(false, array_key_exists('delta', $malformedConfig),
        'Malformed config included a misleading delta');

    file_put_contents($testRoot . '/config.json', '{}');
    assertStatusError($service->status()['config'],
        'Empty config object was not a structural error');

    $invalidConfig = $publishedConfig;
    unset($invalidConfig['show_ads']);
    writeStatusJson($testRoot . '/config.json', $invalidConfig);
    assertStatusError($service->status()['config'],
        'Config missing show_ads was not a structural error');

    $invalidConfig = $publishedConfig;
    $invalidConfig['show_ads'] = 'false';
    writeStatusJson($testRoot . '/config.json', $invalidConfig);
    assertStatusError($service->status()['config'],
        'Config with a non-boolean show_ads was not a structural error');

    $validChangedConfig = $publishedConfig;
    $validChangedConfig['show_ads'] = true;
    writeStatusJson($testRoot . '/config.json', $validChangedConfig);
    $validChangedConfigStatus = $service->status()['config'];
    assertStatusSame(true, $validChangedConfigStatus['changed'],
        'Structurally valid changed config was not marked changed');
    assertStatusSame(null, $validChangedConfigStatus['error'],
        'Structurally valid changed config produced an error');
    assertStatusSame(['show_ads'], $validChangedConfigStatus['delta']['fields'],
        'Changed show_ads was not detailed');
    writeStatusJson($testRoot . '/config.json', $publishedConfig);

    $invalidIndex = $publishedIndex;
    $invalidIndex['default'] = 'does-not-exist.json';
    writeStatusJson($playlistDirectory . '/index.json', $invalidIndex);
    assertStatusError($service->status()['default_playlist'],
        'Index default absent from playlists was not a critical error');

    $invalidIndex = $publishedIndex;
    unset($invalidIndex['playlists'][0]['filename']);
    writeStatusJson($playlistDirectory . '/index.json', $invalidIndex);
    assertStatusError($service->status()['default_playlist'],
        'Index entry missing filename was not a critical error');

    $invalidIndex = $publishedIndex;
    unset($invalidIndex['playlists'][0]['dbtitle']);
    writeStatusJson($playlistDirectory . '/index.json', $invalidIndex);
    assertStatusError($service->status()['default_playlist'],
        'Index entry missing a required field was not a critical error');

    $invalidIndex = $publishedIndex;
    $invalidIndex['playlists'][0]['lastupdated'] = '2026-08-01 09:00:00';
    writeStatusJson($playlistDirectory . '/index.json', $invalidIndex);
    assertStatusError($service->status()['default_playlist'],
        'Index entry with a noncanonical timestamp was not a critical error');

    $indexWithoutAuthor = $publishedIndex;
    unset($indexWithoutAuthor['playlists'][0]['author']);
    writeStatusJson($playlistDirectory . '/index.json', $indexWithoutAuthor);
    $indexWithoutAuthorStatus = $service->status()['default_playlist'];
    assertStatusSame(false, $indexWithoutAuthorStatus['changed'],
        'Index without optional author did not preserve matching default status');
    assertStatusSame(null, $indexWithoutAuthorStatus['error'],
        'Index without optional author was treated as malformed');

    $differentRealDefaultIndex = $publishedIndex;
    $differentRealDefaultIndex['default'] = 'other.json';
    $differentRealDefaultIndex['playlists'][] = [
        'filename' => 'other.json',
        'dbtitle' => 'Other',
        'lastupdated' => '2026-08-01T11:00:00.000Z',
    ];
    writeStatusJson($playlistDirectory . '/index.json', $differentRealDefaultIndex);
    $differentDefaultStatus = $service->status()['default_playlist'];
    assertStatusSame(true, $differentDefaultStatus['changed'],
        'Valid changed default playlist was not detected');
    assertStatusSame(null, $differentDefaultStatus['error'],
        'Valid changed default playlist produced an error');
    assertStatusSame('freetv.json', $differentDefaultStatus['database'],
        'Changed default status lost the MariaDB target');
    assertStatusSame('other.json', $differentDefaultStatus['published'],
        'Changed default status lost the published source');
    writeStatusJson($playlistDirectory . '/index.json', $publishedIndex);

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
    writeStatusJson($playlistDirectory . '/index.json', $publishedIndex);

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
