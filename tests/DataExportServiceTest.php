<?php

require_once __DIR__ . '/../public/api/admin/Settings.php';
require_once __DIR__ . '/../public/api/admin/publication/PublicationException.php';
require_once __DIR__ . '/../public/api/admin/publication/PublicationTimestamp.php';
require_once __DIR__ . '/../public/api/admin/publication/PublicationSemanticHasher.php';
require_once __DIR__ . '/../public/api/admin/publication/PublicationSemanticDelta.php';
require_once __DIR__ . '/../public/api/admin/publication/PlaylistPublicationSerializer.php';
require_once __DIR__ . '/../public/api/admin/publication/ConfigPublicationSerializer.php';
require_once __DIR__ . '/../public/api/admin/publication/PublicationStatusService.php';
require_once __DIR__ . '/../public/api/admin/publication/PublicationUndoService.php';
require_once __DIR__ . '/../public/api/admin/publication/DataExportService.php';

use FreeTV\Admin\Publication\ConfigPublicationSerializer;
use FreeTV\Admin\Publication\DataExportService;
use FreeTV\Admin\Publication\PlaylistPublicationSerializer;
use FreeTV\Admin\Publication\PublicationException;
use FreeTV\Admin\Publication\PublicationStatusService;
use FreeTV\Admin\Publication\PublicationUndoService;

function assertExportSame(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException(
            $message . "\nExpected: " . var_export($expected, true)
            . "\nActual: " . var_export($actual, true)
        );
    }
}

function writeExportJson(string $path, array $artifact): void
{
    $json = json_encode(
        $artifact,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
    ) . "\n";
    if (file_put_contents($path, $json) !== strlen($json)) {
        throw new RuntimeException('Could not write data export fixture');
    }
}

function removeExportTree(string $root): void
{
    if (!is_dir($root)) {
        return;
    }
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iterator as $item) {
        $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }
    rmdir($root);
}

function exportTreeHashes(string $root): array
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
    ksort($hashes, SORT_STRING);
    return $hashes;
}

function expectExportFailure(callable $operation, string $message): void
{
    try {
        $operation();
    } catch (PublicationException $exception) {
        return;
    }
    throw new RuntimeException($message);
}

$testRoot = sys_get_temp_dir() . '/freetv-data-export-test-' . bin2hex(random_bytes(8));
$publicationRoot = $testRoot . '/public';
$playlistRoot = $publicationRoot . '/playlists';
$undoRoot = $testRoot . '/undo';
if (!mkdir($playlistRoot, 0700, true) || !mkdir($undoRoot, 0700, true)) {
    throw new RuntimeException('Could not create data export test directories');
}
file_put_contents($undoRoot . '/.lock', '');
file_put_contents($undoRoot . '/sentinel', 'unchanged undo state');

$playlists = [
    [
        'id' => 2,
        'filename' => 'zeta.json',
        'dbtitle' => 'Zeta',
        'dbversion' => '1.0',
        'author' => null,
        'email' => null,
        'link' => null,
        'is_default' => 0,
        'sort_order' => 1,
        'lastupdated' => '2026-08-23 09:15:00',
    ],
    [
        'id' => 1,
        'filename' => 'alpha.json',
        'dbtitle' => 'Alpha',
        'dbversion' => '1.0',
        'author' => 'Free TV',
        'email' => 'support@example.test',
        'link' => 'https://example.test',
        'is_default' => 1,
        'sort_order' => 0,
        'lastupdated' => '2026-08-22 11:03:27',
    ],
];
usort($playlists, static fn(array $left, array $right): int => $left['sort_order'] <=> $right['sort_order']);
$shows = [
    1 => [[
        'id' => 1,
        'sort_order' => 0,
        'category' => 'comedy',
        'status' => 'active',
        'identifier' => 'alpha-one',
        'title' => 'Alpha One',
        'description' => 'First show',
        'start_year' => '1960',
        'end_year' => '1961',
        'imdb' => 'tt0000001',
        'group_name' => null,
    ]],
    2 => [
        [
            'id' => 2,
            'sort_order' => 0,
            'category' => 'drama',
            'status' => 'active',
            'identifier' => 'zeta-one',
            'title' => 'Zeta One',
            'description' => null,
            'start_year' => '1970',
            'end_year' => null,
            'imdb' => 'tt0000002',
            'group_name' => 'Evening',
        ],
        [
            'id' => 3,
            'sort_order' => 1,
            'category' => null,
            'status' => 'active',
            'identifier' => 'zeta-two',
            'title' => 'Zeta Two',
            'description' => 'Second show',
            'start_year' => null,
            'end_year' => null,
            'imdb' => null,
            'group_name' => null,
        ],
    ],
];
$settings = ['show_ads' => false];
$timestamps = [
    'alpha.json' => '2026-08-20T10:00:00.000Z',
    'zeta.json' => '2026-08-22T12:30:00.000Z',
];

$writeFixture = static function () use (
    $publicationRoot,
    $playlistRoot,
    &$playlists,
    &$shows,
    &$settings,
    &$timestamps
): void {
    foreach ($playlists as $playlist) {
        $filename = $playlist['filename'];
        writeExportJson(
            $playlistRoot . '/' . $filename,
            PlaylistPublicationSerializer::serialize(
                $playlist,
                $shows[$playlist['id']],
                $timestamps[$filename]
            )
        );
    }
    $entries = [];
    $ordered = $playlists;
    usort($ordered, static fn(array $left, array $right): int => $left['sort_order'] <=> $right['sort_order']);
    foreach ($ordered as $playlist) {
        $entry = [
            'filename' => $playlist['filename'],
            'dbtitle' => $playlist['dbtitle'],
            'lastupdated' => $timestamps[$playlist['filename']],
        ];
        if ($playlist['author'] !== null) {
            $entry['author'] = $playlist['author'];
        }
        $entries[] = $entry;
    }
    $default = array_values(array_filter(
        $playlists,
        static fn(array $playlist): bool => $playlist['is_default'] === 1
    ));
    writeExportJson($playlistRoot . '/index.json', [
        'default' => $default[0]['filename'] ?? 'missing.json',
        'playlists' => $entries,
    ]);
    writeExportJson(
        $publicationRoot . '/config.json',
        ConfigPublicationSerializer::serialize($settings, '2026-08-21T11:15:00.000Z')
    );
};

$status = new PublicationStatusService(
    $publicationRoot,
    static function () use (&$playlists): array {
        return $playlists;
    },
    static function (int $playlistId) use (&$shows): array {
        return $shows[$playlistId] ?? [];
    },
    static function () use (&$settings): array {
        return $settings;
    }
);
$undo = new PublicationUndoService($publicationRoot, $undoRoot, static function (): void {
    throw new RuntimeException('Data export attempted to update a database timestamp');
});
$newService = static fn(?callable $revision = null): DataExportService => new DataExportService(
    $publicationRoot,
    $status,
    static fn(): DateTimeImmutable => new DateTimeImmutable('2026-08-23T14:32:18.456Z'),
    $revision ?? static fn(): string => str_repeat('a', 40),
    $undo
);

try {
    $writeFixture();
    mkdir($publicationRoot . '/thumbs', 0700);
    file_put_contents($publicationRoot . '/thumbs/example.jpg', 'not exported');
    $publicationBefore = exportTreeHashes($publicationRoot);
    $undoBefore = exportTreeHashes($undoRoot);
    $databaseBefore = serialize([$playlists, $shows, $settings, $timestamps]);

    $cleanStatus = $status->status();
    assertExportSame(
        [false, false],
        array_column($cleanStatus['playlists'], 'changed'),
        'Fixture playlists are not semantically clean'
    );
    assertExportSame(false, $cleanStatus['config']['changed'], 'Fixture config is not clean');
    assertExportSame(false, $cleanStatus['default_playlist']['changed'],
        'Fixture default playlist is not clean');
    assertExportSame('2026-08-22 11:03:27', $playlists[0]['lastupdated'],
        'Fixture does not represent a newer MariaDB playlist timestamp');
    assertExportSame(false, $playlists[0]['lastupdated'] === $timestamps['alpha.json'],
        'Fixture MariaDB and artifact timestamps unexpectedly agree');

    $publishedAlpha = json_decode(
        (string) file_get_contents($playlistRoot . '/alpha.json'),
        true,
        512,
        JSON_THROW_ON_ERROR
    );
    $publishedIndex = json_decode(
        (string) file_get_contents($playlistRoot . '/index.json'),
        true,
        512,
        JSON_THROW_ON_ERROR
    );
    assertExportSame($publishedAlpha['lastupdated'], $publishedIndex['playlists'][0]['lastupdated'],
        'Fixture playlist and index timestamps do not agree');

    $destination = $testRoot . '/export';
    $manifest = $newService()->export($destination);
    $writtenManifest = json_decode(
        (string) file_get_contents($destination . '/manifest.json'),
        true,
        512,
        JSON_THROW_ON_ERROR
    );
    assertExportSame($manifest, $writtenManifest, 'Returned and staged manifests differ');
    assertExportSame(1, $manifest['contract_version'], 'Manifest contract version is incorrect');
    assertExportSame('2026-08-23T14:32:18.456Z', $manifest['created_at'], 'Export timestamp is incorrect');
    assertExportSame(str_repeat('a', 40), $manifest['server_revision'], 'Server revision is incorrect');
    assertExportSame([
        'config' => '2026-08-21T11:15:00.000Z',
        'latest' => '2026-08-22T12:30:00.000Z',
    ], $manifest['publication'], 'Publication timestamps are incorrect');
    assertExportSame(['playlist_count' => 2, 'show_count' => 3], $manifest['dataset'],
        'Dataset counts are incorrect');
    assertExportSame([
        ['filename' => 'alpha.json', 'published_at' => '2026-08-20T10:00:00.000Z'],
        ['filename' => 'zeta.json', 'published_at' => '2026-08-22T12:30:00.000Z'],
    ], $manifest['playlists'], 'Playlist manifest entries are not deterministic');
    assertExportSame([
        'config.json',
        'playlists/index.json',
        'playlists/alpha.json',
        'playlists/zeta.json',
    ], array_column($manifest['files'], 'path'), 'Manifest file ordering is incorrect');
    foreach ($manifest['files'] as $file) {
        $path = $destination . '/' . $file['path'];
        assertExportSame(hash_file('sha256', $path), $file['sha256'], 'Staged SHA-256 is incorrect');
        assertExportSame(filesize($path), $file['bytes'], 'Staged byte size is incorrect');
        assertExportSame(
            file_get_contents($publicationRoot . '/' . $file['path']),
            file_get_contents($path),
            'Export did not preserve exact published bytes'
        );
    }
    assertExportSame(false, file_exists($destination . '/thumbs'), 'Export included thumbnails');
    assertExportSame($publicationBefore, exportTreeHashes($publicationRoot), 'Export mutated publication files');
    assertExportSame($undoBefore, exportTreeHashes($undoRoot), 'Export mutated publication Undo state');
    assertExportSame($databaseBefore, serialize([$playlists, $shows, $settings, $timestamps]),
        'Export mutated authoritative fixture state');

    $nullRevisionDestination = $testRoot . '/null-revision';
    $nullManifest = $newService(static fn(): ?string => null)->export($nullRevisionDestination);
    assertExportSame(null, $nullManifest['server_revision'], 'Unavailable revision did not use null');

    $shows[1][0]['title'] = 'Unpublished title';
    expectExportFailure(
        fn() => $newService()->export($testRoot . '/unpublished-playlist'),
        'Unpublished playlist changes did not refuse export'
    );
    assertExportSame(false, file_exists($testRoot . '/unpublished-playlist'),
        'Failed playlist validation left an export directory');
    $shows[1][0]['title'] = 'Alpha One';

    $settings['show_ads'] = true;
    expectExportFailure(
        fn() => $newService()->export($testRoot . '/unpublished-config'),
        'Unpublished config changes did not refuse export'
    );
    $settings['show_ads'] = false;

    unlink($playlistRoot . '/zeta.json');
    expectExportFailure(
        fn() => $newService()->export($testRoot . '/missing-artifact'),
        'Missing playlist artifact did not refuse export'
    );
    $writeFixture();

    file_put_contents($playlistRoot . '/alpha.json', '{invalid');
    expectExportFailure(
        fn() => $newService()->export($testRoot . '/malformed-artifact'),
        'Malformed playlist artifact did not refuse export'
    );
    $writeFixture();

    $playlists[1]['is_default'] = 1;
    expectExportFailure(
        fn() => $newService()->export($testRoot . '/invalid-default'),
        'Invalid MariaDB default state did not refuse export'
    );
    $playlists[1]['is_default'] = 0;
    $writeFixture();

    $index = json_decode((string) file_get_contents($playlistRoot . '/index.json'), true, 512, JSON_THROW_ON_ERROR);
    $index['default'] = 'zeta.json';
    writeExportJson($playlistRoot . '/index.json', $index);
    expectExportFailure(
        fn() => $newService()->export($testRoot . '/default-mismatch'),
        'Published/MariaDB default mismatch did not refuse export'
    );
    $writeFixture();

    $index = json_decode((string) file_get_contents($playlistRoot . '/index.json'), true, 512, JSON_THROW_ON_ERROR);
    array_pop($index['playlists']);
    writeExportJson($playlistRoot . '/index.json', $index);
    expectExportFailure(
        fn() => $newService()->export($testRoot . '/inconsistent-index'),
        'Inconsistent playlist index did not refuse export'
    );
    $writeFixture();

    $index = json_decode((string) file_get_contents($playlistRoot . '/index.json'), true, 512, JSON_THROW_ON_ERROR);
    $index['playlists'][0]['lastupdated'] = '2026-08-20T10:00:01.000Z';
    writeExportJson($playlistRoot . '/index.json', $index);
    expectExportFailure(
        fn() => $newService()->export($testRoot . '/timestamp-mismatch'),
        'Playlist/index timestamp mismatch did not refuse export'
    );
    $writeFixture();

    mkdir($testRoot . '/existing-empty', 0700);
    expectExportFailure(
        fn() => $newService()->export($testRoot . '/existing-empty'),
        'Existing empty destination was accepted'
    );
    mkdir($testRoot . '/existing-nonempty', 0700);
    file_put_contents($testRoot . '/existing-nonempty/keep', 'keep');
    expectExportFailure(
        fn() => $newService()->export($testRoot . '/existing-nonempty'),
        'Existing non-empty destination was accepted'
    );
    assertExportSame('keep', file_get_contents($testRoot . '/existing-nonempty/keep'),
        'Rejected destination was modified');
    expectExportFailure(
        fn() => $newService()->export($publicationRoot),
        'Publication root was accepted as an export destination'
    );
    assertExportSame($publicationBefore, exportTreeHashes($publicationRoot),
        'Rejected publication-root destination was modified');

    unlink($undoRoot . '/.lock');
    expectExportFailure(
        fn() => $newService()->export($testRoot . '/missing-publication-lock'),
        'Missing publication lock was accepted'
    );
    assertExportSame(false, file_exists($undoRoot . '/.lock'),
        'Data export created a missing publication lock');
    assertExportSame(false, file_exists($testRoot . '/missing-publication-lock'),
        'Missing publication lock left an export directory');
} finally {
    removeExportTree($testRoot);
}

echo "DataExportService tests passed\n";
