<?php

require_once __DIR__ . '/../public/api/admin/Database.php';
require_once __DIR__ . '/../public/api/admin/publication/PublicationException.php';
require_once __DIR__ . '/../public/api/admin/publication/PublicationTimestamp.php';
require_once __DIR__ . '/../public/api/admin/publication/PublicationUndoService.php';

use FreeTV\Admin\Publication\PublicationException;
use FreeTV\Admin\Publication\PublicationUndoService;

function assertPublicationUndoSame($expected, $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException(
            $message . "\nExpected: " . var_export($expected, true)
            . "\nActual: " . var_export($actual, true)
        );
    }
}

function expectPublicationUndoFailure(callable $callback, string $message): void
{
    try {
        $callback();
    } catch (PublicationException $exception) {
        return;
    }
    throw new RuntimeException($message . ': expected PublicationException');
}

function replaceTestFile(string $path, string $contents): void
{
    $temporary = tempnam(dirname($path), '.test-write-');
    if ($temporary === false
        || file_put_contents($temporary, $contents) !== strlen($contents)
        || !rename($temporary, $path)) {
        throw new RuntimeException('Could not replace test artifact');
    }
}

function activateUndo(
    PublicationUndoService $service,
    string $operation,
    string $target,
    array $newContents
): void {
    $service->withLock(function (PublicationUndoService $locked) use (
        $operation,
        $target,
        $newContents
    ): void {
        $prepared = $locked->prepare($operation, $target, array_keys($newContents));
        foreach ($newContents as $path => $contents) {
            replaceTestFile($GLOBALS['publicationTestRoot'] . '/' . $path, $contents);
        }
        $locked->promote($prepared);
    });
}

function playlistJson(string $timestamp, string $title): string
{
    return json_encode([
        'lastupdated' => $timestamp,
        'dbtitle' => $title,
        'filename' => 'freetv.json',
        'dbversion' => '1.0',
        'author' => 'Free TV',
        'email' => 'support@example.test',
        'link' => 'https://example.test',
        'shows' => [],
    ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
}

function indexJson(string $timestamp): string
{
    return json_encode([
        'default' => 'freetv.json',
        'playlists' => [[
            'filename' => 'freetv.json',
            'dbtitle' => 'FreeTV',
            'lastupdated' => $timestamp,
            'author' => 'Free TV',
        ]],
    ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
}

$testRoot = sys_get_temp_dir() . '/freetv-publication-undo-test-' . bin2hex(random_bytes(8));
$undoRoot = $testRoot . '/undo';
$GLOBALS['publicationTestRoot'] = $testRoot;
mkdir($testRoot . '/playlists', 0700, true);
$timestampUpdates = [];
$service = new PublicationUndoService(
    $testRoot,
    $undoRoot,
    static function (string $filename, string $timestamp) use (&$timestampUpdates): void {
        $timestampUpdates[] = [$filename, $timestamp];
    }
);

$oldTimestamp = '2026-08-12T09:00:00.000Z';
$newTimestamp = '2026-08-12T10:00:00.000Z';
$oldPlaylist = playlistJson($oldTimestamp, 'Old Playlist');
$oldIndex = indexJson($oldTimestamp);
$newPlaylist = playlistJson($newTimestamp, 'New Playlist');
$newIndex = indexJson($newTimestamp);

try {
    replaceTestFile($testRoot . '/playlists/freetv.json', $oldPlaylist);
    replaceTestFile($testRoot . '/playlists/index.json', $oldIndex);
    activateUndo($service, 'playlist', 'freetv.json', [
        'playlists/freetv.json' => $newPlaylist,
        'playlists/index.json' => $newIndex,
    ]);

    $metadata = json_decode(file_get_contents($undoRoot . '/active/operation.json'), true, 32, JSON_THROW_ON_ERROR);
    assertPublicationUndoSame(
        ['playlists/freetv.json', 'playlists/index.json'],
        array_column($metadata['files'], 'path'),
        'Playlist publication did not back up both coupled artifacts'
    );
    assertPublicationUndoSame(true, $service->status()['available'], 'Undo was not made available');

    $undoResult = $service->undo();
    assertPublicationUndoSame($oldPlaylist, file_get_contents($testRoot . '/playlists/freetv.json'),
        'Playlist Undo did not restore the playlist exactly');
    assertPublicationUndoSame($oldIndex, file_get_contents($testRoot . '/playlists/index.json'),
        'Playlist Undo did not restore the index exactly');
    assertPublicationUndoSame(
        [['freetv.json', '2026-08-12 09:00:00']],
        $timestampUpdates,
        'Playlist Undo did not restore MariaDB publication timestamp state'
    );
    assertPublicationUndoSame('playlist', $undoResult['operation'], 'Undo returned wrong operation');
    assertPublicationUndoSame(false, $service->status()['available'], 'Successful Undo was not consumed');

    replaceTestFile($testRoot . '/config.json', '{"lastupdated":"old","show_ads":false}');
    activateUndo($service, 'config', 'config.json', [
        'config.json' => '{"lastupdated":"new","show_ads":true}',
    ]);
    activateUndo($service, 'playlist', 'freetv.json', [
        'playlists/freetv.json' => $newPlaylist,
        'playlists/index.json' => $newIndex,
    ]);
    assertPublicationUndoSame(
        'playlist',
        $service->status()['operation'],
        'Second successful publication did not replace the first Undo slot'
    );

    activateUndo($service, 'config', 'config.json', [
        'config.json' => '{"lastupdated":"newer","show_ads":false}',
    ]);
    assertPublicationUndoSame('config', $service->status()['operation'],
        'Config publication did not replace playlist Undo');
    $service->undo();
    assertPublicationUndoSame(
        '{"lastupdated":"new","show_ads":true}',
        file_get_contents($testRoot . '/config.json'),
        'Config Undo did not restore the previous config exactly'
    );
    assertPublicationUndoSame($newPlaylist, file_get_contents($testRoot . '/playlists/freetv.json'),
        'Config Undo unexpectedly restored playlist artifacts');

    activateUndo($service, 'config', 'config.json', [
        'config.json' => '{"lastupdated":"latest","show_ads":true}',
    ]);
    $service->withLock(function (PublicationUndoService $locked) use ($testRoot): void {
        $prepared = $locked->prepare('config', 'config.json', ['config.json']);
        replaceTestFile($testRoot . '/config.json', '{"failed":"publication"}');
        $locked->rollbackPrepared($prepared);
    });
    assertPublicationUndoSame('config', $service->status()['operation'],
        'Failed publication replaced the valid Undo slot');

    file_put_contents($undoRoot . '/active/files/config.json', 'corrupted');
    expectPublicationUndoFailure(fn() => $service->undo(), 'Corrupt backup was restored');
    assertPublicationUndoSame(true, $service->status()['available'], 'Corrupt Undo was consumed');

    $metadataPath = $undoRoot . '/active/operation.json';
    $metadata = json_decode(file_get_contents($metadataPath), true, 32, JSON_THROW_ON_ERROR);
    replaceTestFile(
        $undoRoot . '/active/files/config.json',
        '{"lastupdated":"new","show_ads":true}'
    );
    $metadata['files'][0]['backup_hash'] = hash_file(
        'sha256',
        $undoRoot . '/active/files/config.json'
    );
    file_put_contents($metadataPath, json_encode($metadata, JSON_THROW_ON_ERROR));
    replaceTestFile($testRoot . '/config.json', '{"unexpected":"newer change"}');
    expectPublicationUndoFailure(fn() => $service->undo(), 'Stale Undo overwrote changed live file');

    file_put_contents($metadataPath, '{bad metadata');
    expectPublicationUndoFailure(fn() => $service->undo(), 'Malformed Undo metadata was accepted');
} finally {
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($testRoot, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iterator as $item) {
        $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }
    rmdir($testRoot);
    unset($GLOBALS['publicationTestRoot']);
}

echo "PublicationUndoService tests passed\n";
