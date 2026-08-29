<?php

declare(strict_types=1);

require_once __DIR__ . '/../public/api/admin/ThumbnailIntegrityService.php';

use FreeTV\Admin\ThumbnailIntegrityService;

function thumbnailIntegrityAssertSame(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message . "\nExpected: " . var_export($expected, true)
            . "\nActual: " . var_export($actual, true));
    }
}

function thumbnailIntegrityTreeState(string $root): array
{
    $state = [];
    foreach (scandir($root) ?: [] as $filename) {
        if ($filename === '.' || $filename === '..') {
            continue;
        }
        $path = $root . '/' . $filename;
        $state[$filename] = is_file($path) ? hash_file('sha256', $path) : 'non-file';
    }
    ksort($state, SORT_STRING);
    return $state;
}

function thumbnailIntegrityRemoveTree(string $root): void
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

$root = sys_get_temp_dir() . '/freetv-thumbnail-integrity-test-' . bin2hex(random_bytes(8));
if (!mkdir($root, 0700, true)) {
    throw new RuntimeException('Could not create thumbnail integrity test directory');
}

$rows = [
    [
        'id' => 9, 'playlist_id' => 2, 'playlist_filename' => 'beta.json',
        'playlist_title' => 'Beta', 'identifier' => 'invalid-two', 'title' => 'Invalid Two',
        'category' => 'test', 'imdb' => 'zzz',
    ],
    [
        'id' => 4, 'playlist_id' => 2, 'playlist_filename' => 'beta.json',
        'playlist_title' => 'Beta', 'identifier' => 'missing', 'title' => 'Missing Thumbnail',
        'category' => 'drama', 'imdb' => 'tt0000200',
    ],
    [
        'id' => 3, 'playlist_id' => 2, 'playlist_filename' => 'beta.json',
        'playlist_title' => 'Beta', 'identifier' => 'shared-beta', 'title' => 'Shared Beta',
        'category' => 'comedy', 'imdb' => 'tt0000100',
    ],
    [
        'id' => 2, 'playlist_id' => 1, 'playlist_filename' => 'alpha.json',
        'playlist_title' => 'Alpha', 'identifier' => 'shared-alpha', 'title' => 'Shared Alpha',
        'category' => 'comedy', 'imdb' => 'tt0000100',
    ],
    [
        'id' => 1, 'playlist_id' => 1, 'playlist_filename' => 'alpha.json',
        'playlist_title' => 'Alpha', 'identifier' => 'present', 'title' => 'Present Thumbnail',
        'category' => 'kids', 'imdb' => 'tt0000400',
    ],
    [
        'id' => 8, 'playlist_id' => 1, 'playlist_filename' => 'alpha.json',
        'playlist_title' => 'Alpha', 'identifier' => 'invalid-one', 'title' => 'Invalid One',
        'category' => 'test', 'imdb' => 'bad imdb',
    ],
    ['id' => 5, 'playlist_id' => 1, 'imdb' => null],
    ['id' => 6, 'playlist_id' => 1, 'imdb' => ''],
    ['id' => 7, 'playlist_id' => 1, 'imdb' => '   '],
];

try {
    file_put_contents($root . '/tt0000400.jpg', 'present 400');
    file_put_contents($root . '/tt0000100.jpg', 'present shared 100');
    file_put_contents($root . '/tt0000300.jpg', 'orphan 300');
    file_put_contents($root . '/tt0000050.jpg', 'orphan 050');
    file_put_contents($root . '/index.html', 'support file');
    file_put_contents($root . '/poster.jpg', 'unrelated jpg');
    file_put_contents($root . '/tt0000999.jpeg', 'wrong extension');
    mkdir($root . '/tt0000888.jpg', 0700);

    $treeBefore = thumbnailIntegrityTreeState($root);
    $rowsBefore = $rows;
    $loaderCalls = 0;
    $loader = static function () use (&$loaderCalls, $rows): array {
        $loaderCalls++;
        return $rows;
    };
    $result = (new ThumbnailIntegrityService($root, $loader))->audit();

    thumbnailIntegrityAssertSame([
        'total_distinct_valid_imdb_references' => 3,
        'total_thumbnail_jpg_files_considered' => 4,
        'present' => 2,
        'missing' => 1,
        'orphaned' => 2,
        'invalid_database_imdb_values' => 2,
        'invalid_database_imdb_rows' => 2,
    ], $result['summary'], 'Thumbnail integrity summary is incorrect');
    thumbnailIntegrityAssertSame([
        [
            'imdb' => 'tt0000100',
            'filename' => 'tt0000100.jpg',
            'usage' => ['show_count' => 2, 'playlist_count' => 2],
        ],
        [
            'imdb' => 'tt0000400',
            'filename' => 'tt0000400.jpg',
            'usage' => ['show_count' => 1, 'playlist_count' => 1],
        ],
    ], $result['present'], 'Present thumbnails are incorrect or not sorted');
    thumbnailIntegrityAssertSame([
        [
            'imdb' => 'tt0000200',
            'filename' => 'tt0000200.jpg',
            'usage' => ['show_count' => 1, 'playlist_count' => 1],
            'representative_show' => [
                'playlist_filename' => 'beta.json',
                'playlist_title' => 'Beta',
                'identifier' => 'missing',
                'title' => 'Missing Thumbnail',
                'category' => 'drama',
            ],
        ],
    ], $result['missing'], 'Missing thumbnail context is incorrect');
    thumbnailIntegrityAssertSame([
        ['imdb' => 'tt0000050', 'filename' => 'tt0000050.jpg'],
        ['imdb' => 'tt0000300', 'filename' => 'tt0000300.jpg'],
    ], $result['orphaned'], 'Orphan thumbnails are incorrect or not sorted');
    thumbnailIntegrityAssertSame(['bad imdb', 'zzz'],
        array_column($result['invalid_database_imdb'], 'value'),
        'Invalid database IMDb values are incorrect or not sorted');
    thumbnailIntegrityAssertSame(1, $loaderCalls, 'Database rows were loaded more than once');
    thumbnailIntegrityAssertSame($rowsBefore, $rows, 'Database fixture rows were mutated');
    thumbnailIntegrityAssertSame($treeBefore, thumbnailIntegrityTreeState($root),
        'Thumbnail integrity audit mutated the filesystem');
} finally {
    thumbnailIntegrityRemoveTree($root);
}

echo "ThumbnailIntegrityService tests passed\n";
