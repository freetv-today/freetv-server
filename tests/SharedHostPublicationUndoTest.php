<?php

declare(strict_types=1);

require_once __DIR__ . '/../public/api/admin/Database.php';
require_once __DIR__ . '/../public/api/admin/ServerPaths.php';
require_once __DIR__ . '/../public/api/admin/publication/PublicationException.php';
require_once __DIR__ . '/../public/api/admin/publication/PublicationTimestamp.php';
require_once __DIR__ . '/../public/api/admin/publication/PublicationUndoService.php';

use FreeTV\Admin\ServerPaths;
use FreeTV\Admin\Publication\PublicationException;
use FreeTV\Admin\Publication\PublicationUndoService;

function sharedHostAssertSame(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message . "\nExpected: " . var_export($expected, true)
            . "\nActual: " . var_export($actual, true));
    }
}

function sharedHostReplace(string $path, string $contents): void
{
    $temporary = tempnam(dirname($path), '.shared-host-');
    if ($temporary === false
        || file_put_contents($temporary, $contents) !== strlen($contents)
        || !rename($temporary, $path)) {
        throw new RuntimeException('Could not replace shared-host fixture file');
    }
}

function sharedHostRemove(string $root): void
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

$previousEnvironment = getenv('FREETV_PUBLIC_PATH');
$hadEnvironmentArray = array_key_exists('FREETV_PUBLIC_PATH', $_ENV);
$previousEnvironmentArray = $_ENV['FREETV_PUBLIC_PATH'] ?? null;
$appRoot = sys_get_temp_dir() . '/freetv-shared-host-undo-' . bin2hex(random_bytes(6));
$publicRoot = $appRoot . '/public_html';
$playlistRoot = $publicRoot . '/playlists';
mkdir($playlistRoot, 0700, true);
mkdir($appRoot . '/temp', 0700, true);

$oldTimestamp = '2026-08-12T09:00:00.000Z';
$newTimestamp = '2026-08-12T10:00:00.000Z';
$oldPlaylist = json_encode(['lastupdated' => $oldTimestamp, 'dbtitle' => 'Old', 'shows' => []], JSON_THROW_ON_ERROR);
$newPlaylist = json_encode(['lastupdated' => $newTimestamp, 'dbtitle' => 'New', 'shows' => []], JSON_THROW_ON_ERROR);
$oldIndex = json_encode(['default' => 'freetv.json', 'playlists' => [['filename' => 'freetv.json', 'lastupdated' => $oldTimestamp]]], JSON_THROW_ON_ERROR);
$newIndex = json_encode(['default' => 'freetv.json', 'playlists' => [['filename' => 'freetv.json', 'lastupdated' => $newTimestamp]]], JSON_THROW_ON_ERROR);

try {
    putenv('FREETV_PUBLIC_PATH=public_html');
    $_ENV['FREETV_PUBLIC_PATH'] = 'public_html';
    $paths = new ServerPaths($appRoot);
    $timestamps = [];
    $service = new PublicationUndoService(
        null,
        null,
        static function (string $filename, string $timestamp) use (&$timestamps): void {
            $timestamps[] = [$filename, $timestamp];
        },
        $paths
    );

    sharedHostReplace($playlistRoot . '/freetv.json', $oldPlaylist);
    sharedHostReplace($playlistRoot . '/index.json', $oldIndex);
    $service->withLock(function (PublicationUndoService $locked) use (
        $playlistRoot,
        $newPlaylist,
        $newIndex
    ): void {
        $prepared = $locked->prepare('playlist', 'freetv.json', [
            'playlists/freetv.json',
            'playlists/index.json',
        ]);
        sharedHostReplace($playlistRoot . '/freetv.json', $newPlaylist);
        sharedHostReplace($playlistRoot . '/index.json', $newIndex);
        $locked->promote($prepared);
    });

    $undoRoot = $appRoot . '/temp/publication-undo';
    $metadata = json_decode((string) file_get_contents($undoRoot . '/active/operation.json'), true, 32, JSON_THROW_ON_ERROR);
    foreach ($metadata['files'] as $file) {
        sharedHostAssertSame(
            hash_file('sha256', $publicRoot . '/' . $file['path']),
            $file['published_hash'],
            'Published hash was not captured from public_html'
        );
    }
    sharedHostAssertSame(false, file_exists($appRoot . '/public'), 'Test unexpectedly created or read app/public');
    sharedHostAssertSame(true, is_dir($undoRoot . '/active'), 'Undo state was not kept under private app temp');

    $service->undo();
    sharedHostAssertSame($oldPlaylist, file_get_contents($playlistRoot . '/freetv.json'),
        'Shared-host Undo did not restore playlist');
    sharedHostAssertSame($oldIndex, file_get_contents($playlistRoot . '/index.json'),
        'Shared-host Undo did not restore index');
    sharedHostAssertSame([['freetv.json', '2026-08-12 09:00:00']], $timestamps,
        'Shared-host Undo did not restore publication timestamp');

    $service->withLock(function (PublicationUndoService $locked) use ($playlistRoot, $newPlaylist, $newIndex): void {
        $prepared = $locked->prepare('playlist', 'freetv.json', [
            'playlists/freetv.json',
            'playlists/index.json',
        ]);
        sharedHostReplace($playlistRoot . '/freetv.json', $newPlaylist);
        sharedHostReplace($playlistRoot . '/index.json', $newIndex);
        $locked->promote($prepared);
    });
    sharedHostReplace($playlistRoot . '/freetv.json', '{"unexpected":true}');
    try {
        $service->undo();
        throw new RuntimeException('Changed live file incorrectly passed the Undo hash guard');
    } catch (PublicationException $exception) {
        sharedHostAssertSame(
            'Live publication no longer matches the available Undo',
            $exception->getMessage(),
            'Hash guard failure message changed'
        );
    }
    sharedHostAssertSame(true, $service->status()['available'], 'Rejected stale Undo was consumed');
} finally {
    if ($previousEnvironment === false) {
        putenv('FREETV_PUBLIC_PATH');
    } else {
        putenv('FREETV_PUBLIC_PATH=' . $previousEnvironment);
    }
    if ($hadEnvironmentArray) {
        $_ENV['FREETV_PUBLIC_PATH'] = $previousEnvironmentArray;
    } else {
        unset($_ENV['FREETV_PUBLIC_PATH']);
    }
    sharedHostRemove($appRoot);
}

fwrite(STDOUT, "SharedHostPublicationUndoTest passed\n");
