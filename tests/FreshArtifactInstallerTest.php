<?php

declare(strict_types=1);

require_once __DIR__ . '/../public/api/admin/FreshArtifactInstaller.php';

use FreeTV\Admin\FreshArtifactInstaller;

$root = sys_get_temp_dir() . '/freetv-fresh-artifacts-' . bin2hex(random_bytes(8));
if (!mkdir($root . '/playlists', 0700, true)) {
    throw new RuntimeException('Could not create Fresh artifact test directory');
}

$artifacts = [
    'config.json' => ['lastupdated' => '2026-09-02T12:00:00.000Z', 'show_ads' => false],
    'playlists/index.json' => [
        'default' => 'playlist-one.json',
        'playlists' => [[
            'filename' => 'playlist-one.json',
            'dbtitle' => 'Playlist One',
            'lastupdated' => '2026-09-02T12:00:00.000Z',
        ]],
    ],
    'playlists/playlist-one.json' => [
        'lastupdated' => '2026-09-02T12:00:00.000Z',
        'dbtitle' => 'Playlist One',
        'dbversion' => null,
        'author' => null,
        'email' => null,
        'link' => null,
        'shows' => [],
    ],
];

try {
    foreach (array_keys($artifacts) as $relativePath) {
        file_put_contents($root . '/' . $relativePath, '{"stale":true}');
    }
    file_put_contents($root . '/unrelated.json', '{"preserved":true}');
    file_put_contents($root . '/playlists/unrelated.json', '{"preserved":true}');

    $installation = (new FreshArtifactInstaller($root))->prepare($artifacts);
    $installation->promote();
    $installation->commit();

    foreach ($artifacts as $relativePath => $expected) {
        $actual = json_decode(
            (string) file_get_contents($root . '/' . $relativePath),
            true,
            512,
            JSON_THROW_ON_ERROR
        );
        if ($actual !== $expected) {
            throw new RuntimeException("Stale managed artifact was not replaced: {$relativePath}");
        }
    }
    if (file_get_contents($root . '/unrelated.json') !== '{"preserved":true}'
        || file_get_contents($root . '/playlists/unrelated.json') !== '{"preserved":true}') {
        throw new RuntimeException('Fresh artifact installation changed an unrelated file');
    }
} finally {
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iterator as $item) {
        $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }
    rmdir($root);
}

fwrite(STDOUT, "FreshArtifactInstallerTest passed\n");
