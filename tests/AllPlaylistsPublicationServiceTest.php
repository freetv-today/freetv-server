<?php

require_once __DIR__ . '/../public/api/admin/Settings.php';
require_once __DIR__ . '/../public/api/admin/publication/AllPlaylistsPublicationService.php';

use FreeTV\Admin\Publication\AllPlaylistsPublicationService;
use FreeTV\Admin\Publication\PlaylistPublicationSerializer;
use FreeTV\Admin\Publication\PublicationException;
use FreeTV\Admin\Publication\PublicationUndoService;

function assertAllPlaylistsSame($expected, $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException(
            $message . "\nExpected: " . var_export($expected, true)
            . "\nActual: " . var_export($actual, true)
        );
    }
}

function expectAllPlaylistsFailure(callable $callback, int $status, string $message): void
{
    try {
        $callback();
    } catch (PublicationException $exception) {
        assertAllPlaylistsSame($status, $exception->getHttpStatus(), $message);
        return;
    }
    throw new RuntimeException($message . ': expected PublicationException');
}

function replaceAllPlaylistsFile(string $path, string $contents): void
{
    $temporary = tempnam(dirname($path), '.all-playlists-test-');
    if ($temporary === false
        || file_put_contents($temporary, $contents) !== strlen($contents)
        || !chmod($temporary, 0644)
        || !rename($temporary, $path)) {
        throw new RuntimeException('Could not write all-playlists fixture');
    }
}

function encodeAllPlaylistsArtifact(array $artifact): string
{
    return json_encode(
        $artifact,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
    ) . "\n";
}

function allPlaylistsHashes(string $root): array
{
    $hashes = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($iterator as $item) {
        if ($item->isFile() && $item->getFilename() !== '.lock') {
            $relative = substr($item->getPathname(), strlen($root) + 1);
            $hashes[$relative] = hash_file('sha256', $item->getPathname());
        }
    }
    ksort($hashes);
    return $hashes;
}

function allPlaylistsShow(int $id, string $identifier, string $title, int $sortOrder = 0): array
{
    return [
        'id' => $id,
        'sort_order' => $sortOrder,
        'category' => 'comedy',
        'status' => 'active',
        'identifier' => $identifier,
        'title' => $title,
        'description' => 'Description for ' . $title,
        'start_year' => '1960',
        'end_year' => '1961',
        'imdb' => 'tt0000001',
        'group_name' => null,
    ];
}

function allPlaylistsFixture(): array
{
    $playlists = [
        [
            'id' => 1,
            'filename' => 'freetv.json',
            'dbtitle' => 'FreeTV',
            'dbversion' => '2.0',
            'author' => 'Free TV',
            'email' => 'support@example.test',
            'link' => 'https://example.test',
            'is_default' => 0,
            'sort_order' => 0,
        ],
        [
            'id' => 2,
            'filename' => 'british.json',
            'dbtitle' => 'British',
            'dbversion' => '1.0',
            'author' => 'Free TV',
            'email' => 'support@example.test',
            'link' => 'https://example.test',
            'is_default' => 1,
            'sort_order' => 1,
        ],
        [
            'id' => 3,
            'filename' => 'holidays.json',
            'dbtitle' => 'Holidays',
            'dbversion' => '1.0',
            'author' => 'Free TV',
            'email' => 'support@example.test',
            'link' => 'https://example.test',
            'is_default' => 0,
            'sort_order' => 2,
        ],
    ];
    $shows = [
        1 => [
            allPlaylistsShow(1, 'first-show', 'First Show'),
            allPlaylistsShow(2, 'new-show', 'New Show', 1),
        ],
        2 => [allPlaylistsShow(3, 'british-show', 'British Show')],
        3 => [allPlaylistsShow(4, 'holiday-show', 'Edited Holiday Show')],
    ];
    $publishedPlaylists = $playlists;
    $publishedPlaylists[0]['dbversion'] = '1.0';
    $publishedShows = $shows;
    $publishedShows[1] = [$shows[1][0]];
    $publishedShows[3][0]['title'] = 'Holiday Show';
    $publishedShows[3][0]['description'] = 'Description for Holiday Show';

    return [$playlists, $shows, $publishedPlaylists, $publishedShows];
}

function writeAllPlaylistsFixture(
    string $root,
    array $publishedPlaylists,
    array $publishedShows,
    string $publishedDefault = 'freetv.json'
): array {
    $timestamps = [
        'freetv.json' => '2026-08-12T08:00:00.000Z',
        'british.json' => '2026-08-12T09:00:00.000Z',
        'holidays.json' => '2026-08-12T10:00:00.000Z',
    ];
    $entries = [];
    foreach ($publishedPlaylists as $playlist) {
        $filename = $playlist['filename'];
        $artifact = PlaylistPublicationSerializer::serialize(
            $playlist,
            $publishedShows[$playlist['id']],
            $timestamps[$filename]
        );
        replaceAllPlaylistsFile($root . '/playlists/' . $filename, encodeAllPlaylistsArtifact($artifact));
        $entries[] = [
            'filename' => $filename,
            'dbtitle' => $playlist['dbtitle'],
            'lastupdated' => $timestamps[$filename],
            'author' => $playlist['author'],
        ];
    }
    replaceAllPlaylistsFile($root . '/playlists/index.json', encodeAllPlaylistsArtifact([
        'default' => $publishedDefault,
        'playlists' => $entries,
    ]));
    replaceAllPlaylistsFile(
        $root . '/config.json',
        encodeAllPlaylistsArtifact([
            'lastupdated' => '2026-08-12T07:00:00.000Z',
            'show_ads' => true,
        ])
    );
    return $timestamps;
}

function allPlaylistsWriter(array &$writes, ?int $failAt = null): callable
{
    return static function (string $path, string $contents) use (&$writes, $failAt): void {
        $writes[] = $path;
        if ($failAt !== null && count($writes) === $failAt) {
            throw new RuntimeException('Simulated publication write failure');
        }
        replaceAllPlaylistsFile($path, $contents);
    };
}

function activateConfigUndo(PublicationUndoService $undoService, string $root): void
{
    $undoService->withLock(function (PublicationUndoService $locked) use ($root): void {
        $prepared = $locked->prepare('config', 'config.json', ['config.json']);
        replaceAllPlaylistsFile(
            $root . '/config.json',
            encodeAllPlaylistsArtifact([
                'lastupdated' => '2026-08-12T07:30:00.000Z',
                'show_ads' => true,
            ])
        );
        $locked->promote($prepared);
    });
}

$testRoot = sys_get_temp_dir() . '/freetv-all-playlists-test-' . bin2hex(random_bytes(8));
mkdir($testRoot . '/playlists', 0700, true);

try {
    [$playlists, $shows, $publishedPlaylists, $publishedShows] = allPlaylistsFixture();
    $oldTimestamps = writeAllPlaylistsFixture($testRoot, $publishedPlaylists, $publishedShows);
    $oldFiles = [
        'freetv.json' => file_get_contents($testRoot . '/playlists/freetv.json'),
        'british.json' => file_get_contents($testRoot . '/playlists/british.json'),
        'holidays.json' => file_get_contents($testRoot . '/playlists/holidays.json'),
        'index.json' => file_get_contents($testRoot . '/playlists/index.json'),
        'config.json' => file_get_contents($testRoot . '/config.json'),
    ];
    $undoUpdates = [];
    $undoService = new PublicationUndoService(
        $testRoot,
        $testRoot . '/undo',
        static function (string $filename, string $timestamp) use (&$undoUpdates): void {
            $undoUpdates[] = [$filename, $timestamp];
        }
    );
    activateConfigUndo($undoService, $testRoot);
    $configBeforePublication = file_get_contents($testRoot . '/config.json');
    $timestampUpdates = [];
    $writes = [];
    $service = new AllPlaylistsPublicationService(
        $testRoot,
        static fn() => $playlists,
        static fn(int $playlistId) => $shows[$playlistId],
        static function (int $playlistId, string $timestamp) use (&$timestampUpdates): void {
            $timestampUpdates[] = [$playlistId, $timestamp];
        },
        static fn() => new DateTimeImmutable('2026-08-12T16:30:00.987Z'),
        $undoService,
        allPlaylistsWriter($writes)
    );

    $result = $service->publish();
    assertAllPlaylistsSame(['freetv.json', 'holidays.json'], $result['playlists'],
        'Changed playlist set was incorrect');
    assertAllPlaylistsSame(true, $result['default_changed'], 'Default change was not included');
    assertAllPlaylistsSame('2026-08-12T16:30:00.000Z', $result['lastupdated'],
        'Shared operation timestamp was not canonical');
    assertAllPlaylistsSame(1, count(array_filter($writes,
        static fn(string $path): bool => basename($path) === 'index.json')),
        'Index was not written exactly once');
    assertAllPlaylistsSame($oldFiles['british.json'], file_get_contents($testRoot . '/playlists/british.json'),
        'Clean playlist was rewritten');
    assertAllPlaylistsSame($configBeforePublication, file_get_contents($testRoot . '/config.json'),
        'Config was rewritten by playlist-content publication');

    $publishedFreeTv = json_decode(file_get_contents($testRoot . '/playlists/freetv.json'), true, 512,
        JSON_THROW_ON_ERROR);
    $publishedHolidays = json_decode(file_get_contents($testRoot . '/playlists/holidays.json'), true, 512,
        JSON_THROW_ON_ERROR);
    $finalIndex = json_decode(file_get_contents($testRoot . '/playlists/index.json'), true, 512,
        JSON_THROW_ON_ERROR);
    $finalEntries = array_column($finalIndex['playlists'], null, 'filename');
    assertAllPlaylistsSame($publishedFreeTv['lastupdated'], $publishedHolidays['lastupdated'],
        'Changed playlists did not share one timestamp');
    assertAllPlaylistsSame($result['lastupdated'], $finalEntries['freetv.json']['lastupdated'],
        'Changed FreeTV index timestamp differed from the operation');
    assertAllPlaylistsSame($result['lastupdated'], $finalEntries['holidays.json']['lastupdated'],
        'Changed Holidays index timestamp differed from the operation');
    assertAllPlaylistsSame($oldTimestamps['british.json'], $finalEntries['british.json']['lastupdated'],
        'Clean playlist index timestamp was not preserved');
    assertAllPlaylistsSame('british.json', $finalIndex['default'], 'MariaDB default was not published');
    assertAllPlaylistsSame([
        [1, '2026-08-12 16:30:00'],
        [3, '2026-08-12 16:30:00'],
    ], $timestampUpdates, 'Only changed playlist DB timestamps were not updated');

    $metadata = json_decode(file_get_contents($testRoot . '/undo/active/operation.json'), true, 64,
        JSON_THROW_ON_ERROR);
    assertAllPlaylistsSame('playlist_all', $metadata['operation'], 'Multi-publication Undo type was incorrect');
    assertAllPlaylistsSame([
        'playlists/freetv.json',
        'playlists/holidays.json',
        'playlists/index.json',
    ], array_column($metadata['files'], 'path'), 'Multi-publication Undo did not contain the full file set');

    $undoResult = $undoService->undo();
    assertAllPlaylistsSame('playlist_all', $undoResult['operation'], 'Undo returned the wrong operation');
    foreach (['freetv.json', 'holidays.json', 'index.json'] as $filename) {
        assertAllPlaylistsSame($oldFiles[$filename], file_get_contents($testRoot . '/playlists/' . $filename),
            'Multi-publication Undo did not restore ' . $filename);
    }
    assertAllPlaylistsSame([
        ['freetv.json', '2026-08-12 08:00:00'],
        ['holidays.json', '2026-08-12 10:00:00'],
    ], $undoUpdates, 'Multi-publication Undo did not restore all DB timestamps');
    assertAllPlaylistsSame(false, $undoService->status()['available'], 'Multi-publication Undo was not consumed');

    writeAllPlaylistsFixture($testRoot, $playlists, $shows, 'freetv.json');
    $defaultOnlyWrites = [];
    $defaultOnlyUpdates = [];
    $defaultOnlyService = new AllPlaylistsPublicationService(
        $testRoot,
        static fn() => $playlists,
        static fn(int $playlistId) => $shows[$playlistId],
        static function (int $playlistId, string $timestamp) use (&$defaultOnlyUpdates): void {
            $defaultOnlyUpdates[] = [$playlistId, $timestamp];
        },
        static fn() => new DateTimeImmutable('2026-08-12T17:00:00Z'),
        $undoService,
        allPlaylistsWriter($defaultOnlyWrites)
    );
    $defaultOnlyFiles = allPlaylistsHashes($testRoot . '/playlists');
    $defaultOnlyIndex = file_get_contents($testRoot . '/playlists/index.json');
    $defaultOnlyResult = $defaultOnlyService->publish();
    assertAllPlaylistsSame([], $defaultOnlyResult['playlists'], 'Default-only operation published playlists');
    assertAllPlaylistsSame(true, $defaultOnlyResult['default_changed'], 'Default-only change was missed');
    assertAllPlaylistsSame(1, count($defaultOnlyWrites), 'Default-only operation wrote more than index.json');
    assertAllPlaylistsSame([], $defaultOnlyUpdates, 'Default-only operation updated DB timestamps');
    $defaultMetadata = json_decode(file_get_contents($testRoot . '/undo/active/operation.json'), true, 64,
        JSON_THROW_ON_ERROR);
    assertAllPlaylistsSame(['playlists/index.json'], array_column($defaultMetadata['files'], 'path'),
        'Default-only Undo contained playlist files');
    foreach (['freetv.json', 'british.json', 'holidays.json'] as $filename) {
        assertAllPlaylistsSame(
            $defaultOnlyFiles[$filename],
            hash_file('sha256', $testRoot . '/playlists/' . $filename),
            'Default-only operation rewrote ' . $filename
        );
    }

    $noOpHashes = allPlaylistsHashes($testRoot);
    $noOpUndoStatus = $undoService->status();
    $noOpWrites = [];
    $noOpUpdates = [];
    $noOpService = new AllPlaylistsPublicationService(
        $testRoot,
        static fn() => $playlists,
        static fn(int $playlistId) => $shows[$playlistId],
        static function (int $playlistId, string $timestamp) use (&$noOpUpdates): void {
            $noOpUpdates[] = [$playlistId, $timestamp];
        },
        static function (): DateTimeImmutable {
            throw new RuntimeException('No-op requested a timestamp');
        },
        $undoService,
        allPlaylistsWriter($noOpWrites)
    );
    $noOpResult = $noOpService->publish();
    assertAllPlaylistsSame(true, $noOpResult['no_op'], 'Clean operation was not a no-op');
    assertAllPlaylistsSame([], $noOpWrites, 'No-op wrote publication files');
    assertAllPlaylistsSame([], $noOpUpdates, 'No-op updated DB timestamps');
    assertAllPlaylistsSame($noOpUndoStatus, $undoService->status(), 'No-op replaced the Undo slot');
    assertAllPlaylistsSame($noOpHashes, allPlaylistsHashes($testRoot), 'No-op changed filesystem state');

    $undoUpdatesBeforeDefaultUndo = $undoUpdates;
    $undoService->undo();
    assertAllPlaylistsSame($defaultOnlyIndex, file_get_contents($testRoot . '/playlists/index.json'),
        'Default-only Undo did not restore index.json');
    assertAllPlaylistsSame($undoUpdatesBeforeDefaultUndo, $undoUpdates,
        'Default-only Undo changed playlist DB timestamps');

    $replacementShows = $shows;
    $replacementShows[1][0]['title'] = 'First Replacement';
    $firstReplacement = new AllPlaylistsPublicationService(
        $testRoot,
        static fn() => $playlists,
        static fn(int $playlistId) => $replacementShows[$playlistId],
        static function (): void {},
        static fn() => new DateTimeImmutable('2026-08-12T17:30:00Z'),
        $undoService
    );
    $firstReplacement->publish();
    $firstReplacementArtifact = file_get_contents($testRoot . '/playlists/freetv.json');
    $replacementShows[1][0]['title'] = 'Second Replacement';
    (new AllPlaylistsPublicationService(
        $testRoot,
        static fn() => $playlists,
        static fn(int $playlistId) => $replacementShows[$playlistId],
        static function (): void {},
        static fn() => new DateTimeImmutable('2026-08-12T17:45:00Z'),
        $undoService
    ))->publish();
    assertAllPlaylistsSame(
        $firstReplacementArtifact,
        file_get_contents($testRoot . '/undo/active/files/playlists/freetv.json'),
        'Second successful multi-publication did not replace the previous Undo slot'
    );
    $undoService->undo();

    writeAllPlaylistsFixture($testRoot, $playlists, $shows, 'british.json');
    unlink($testRoot . '/playlists/holidays.json');
    $missingArtifactWrites = [];
    (new AllPlaylistsPublicationService(
        $testRoot,
        static fn() => $playlists,
        static fn(int $playlistId) => $shows[$playlistId],
        static function (): void {},
        static fn() => new DateTimeImmutable('2026-08-12T17:50:00Z'),
        $undoService,
        allPlaylistsWriter($missingArtifactWrites)
    ))->publish();
    assertAllPlaylistsSame(true, is_file($testRoot . '/playlists/holidays.json'),
        'Missing changed playlist was not initially published');
    $missingMetadata = json_decode(file_get_contents($testRoot . '/undo/active/operation.json'), true, 64,
        JSON_THROW_ON_ERROR);
    $missingFiles = array_column($missingMetadata['files'], null, 'path');
    assertAllPlaylistsSame(false, $missingFiles['playlists/holidays.json']['existed'],
        'Undo did not record that the initially published playlist was previously absent');
    $undoService->undo();
    assertAllPlaylistsSame(false, is_file($testRoot . '/playlists/holidays.json'),
        'Undo did not remove an initially published playlist artifact');

    writeAllPlaylistsFixture($testRoot, $publishedPlaylists, $publishedShows);
    activateConfigUndo($undoService, $testRoot);
    $priorUndo = $undoService->status();
    $failureFiles = allPlaylistsHashes($testRoot);
    $failedWrites = [];
    $failedUpdates = [];
    $writeFailureService = new AllPlaylistsPublicationService(
        $testRoot,
        static fn() => $playlists,
        static fn(int $playlistId) => $shows[$playlistId],
        static function (int $playlistId, string $timestamp) use (&$failedUpdates): void {
            $failedUpdates[] = [$playlistId, $timestamp];
        },
        static fn() => new DateTimeImmutable('2026-08-12T18:00:00Z'),
        $undoService,
        allPlaylistsWriter($failedWrites, 2)
    );
    expectAllPlaylistsFailure(fn() => $writeFailureService->publish(), 500,
        'Write failure did not abort the operation');
    assertAllPlaylistsSame($failureFiles, allPlaylistsHashes($testRoot),
        'Write failure did not restore the complete previous state');
    assertAllPlaylistsSame($priorUndo, $undoService->status(), 'Write failure replaced prior Undo');
    assertAllPlaylistsSame([], $failedUpdates, 'Write failure updated DB timestamps');

    $databaseTimestamps = [
        1 => '2026-08-12 08:00:00',
        3 => '2026-08-12 10:00:00',
    ];
    $timestampFailureService = new AllPlaylistsPublicationService(
        $testRoot,
        static fn() => $playlists,
        static fn(int $playlistId) => $shows[$playlistId],
        static function (int $playlistId, string $timestamp) use (&$databaseTimestamps): void {
            if ($playlistId === 3 && $timestamp === '2026-08-12 18:30:00') {
                throw new RuntimeException('Simulated DB timestamp failure');
            }
            $databaseTimestamps[$playlistId] = $timestamp;
        },
        static fn() => new DateTimeImmutable('2026-08-12T18:30:00Z'),
        $undoService
    );
    expectAllPlaylistsFailure(fn() => $timestampFailureService->publish(), 500,
        'DB timestamp failure did not abort the operation');
    assertAllPlaylistsSame($failureFiles, allPlaylistsHashes($testRoot),
        'DB timestamp failure did not restore publication files');
    assertAllPlaylistsSame('2026-08-12 08:00:00', $databaseTimestamps[1],
        'DB timestamp failure did not restore an already updated playlist timestamp');
    assertAllPlaylistsSame($priorUndo, $undoService->status(), 'DB timestamp failure replaced prior Undo');

    file_put_contents($testRoot . '/playlists/freetv.json', '{invalid');
    $errorHashes = allPlaylistsHashes($testRoot);
    expectAllPlaylistsFailure(fn() => $service->publish(), 409,
        'Malformed playlist status did not abort before publication');
    assertAllPlaylistsSame($errorHashes, allPlaylistsHashes($testRoot),
        'Malformed playlist status caused filesystem writes');

    writeAllPlaylistsFixture($testRoot, $publishedPlaylists, $publishedShows);
    $invalidDefaults = $playlists;
    $invalidDefaults[0]['is_default'] = 1;
    expectAllPlaylistsFailure(
        fn() => (new AllPlaylistsPublicationService(
            $testRoot,
            static fn() => $invalidDefaults,
            static fn(int $playlistId) => $shows[$playlistId],
            static function (): void {}
        ))->publish(),
        409,
        'Multiple MariaDB defaults were accepted'
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

echo "AllPlaylistsPublicationService tests passed\n";
