<?php

declare(strict_types=1);

require_once __DIR__ . '/../public/api/admin/DarkThumbnailCorrelationService.php';

use FreeTV\Admin\DarkThumbnailCorrelationException;
use FreeTV\Admin\DarkThumbnailCorrelationService;

function darkCorrelationAssertSame(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message . "\nExpected: " . var_export($expected, true)
            . "\nActual: " . var_export($actual, true));
    }
}

function darkCorrelationWriteJson(string $path, array $value): void
{
    file_put_contents($path, json_encode($value, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n");
}

function darkCorrelationTreeState(string $root): array
{
    $state = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($iterator as $item) {
        if ($item->isFile() && !$item->isLink()) {
            $relative = substr($item->getPathname(), strlen($root) + 1);
            $state[$relative] = hash_file('sha256', $item->getPathname());
        }
    }
    ksort($state, SORT_STRING);
    return $state;
}

function darkCorrelationRemoveTree(string $root): void
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

function darkCorrelationExpectFailure(callable $operation, string $expectedMessage): void
{
    try {
        $operation();
    } catch (DarkThumbnailCorrelationException $exception) {
        if (!str_contains($exception->getMessage(), $expectedMessage)) {
            throw new RuntimeException('Unexpected failure message: ' . $exception->getMessage());
        }
        return;
    }
    throw new RuntimeException('Expected dark thumbnail correlation failure');
}

$root = sys_get_temp_dir() . '/freetv-dark-thumbnail-correlation-test-' . bin2hex(random_bytes(8));
$sourceRoot = $root . '/source';
if (!mkdir($sourceRoot, 0700, true)) {
    throw new RuntimeException('Could not create dark thumbnail correlation test fixture');
}

$resultsPath = $root . '/results.json';
$results = [
    'results' => [
        ['playlist' => 'alpha.json', 'identifier' => 'shared-a', 'is_dark' => true],
        ['playlist' => 'alpha.json', 'identifier' => 'shared-a', 'is_dark' => true],
        ['playlist' => 'beta.json', 'identifier' => 'shared-b', 'is_dark' => true],
        ['playlist' => 'alpha.json', 'identifier' => 'no-file', 'is_dark' => true],
        ['playlist' => 'alpha.json', 'identifier' => 'still-current', 'is_dark' => true],
        ['playlist' => 'alpha.json', 'identifier' => 'missing-imdb', 'is_dark' => true],
        ['playlist' => 'alpha.json', 'identifier' => 'missing-source', 'is_dark' => true],
        ['playlist' => 'alpha.json', 'identifier' => 'not-dark', 'is_dark' => false],
    ],
];
$alpha = [
    'shows' => [
        ['identifier' => 'shared-a', 'title' => 'Shared A', 'category' => 'drama', 'imdb' => 'tt0000100'],
        ['identifier' => 'no-file', 'title' => 'No File', 'category' => 'comedy', 'imdb' => 'tt0000400'],
        ['identifier' => 'still-current', 'title' => 'Still Current', 'category' => 'kids', 'imdb' => 'tt0000300'],
        ['identifier' => 'missing-imdb', 'title' => 'Missing IMDb', 'category' => 'test', 'imdb' => ''],
        ['identifier' => 'not-dark', 'title' => 'Not Dark', 'category' => 'test', 'imdb' => 'tt0000200'],
    ],
];
$beta = [
    'shows' => [
        ['identifier' => 'shared-b', 'title' => 'Shared B', 'category' => 'drama', 'imdb' => 'tt0000100'],
    ],
];

try {
    darkCorrelationWriteJson($resultsPath, $results);
    darkCorrelationWriteJson($sourceRoot . '/alpha.json', $alpha);
    darkCorrelationWriteJson($sourceRoot . '/beta.json', $beta);
    $treeBefore = darkCorrelationTreeState($root);
    $audit = [
        'present' => [['imdb' => 'tt0000300', 'filename' => 'tt0000300.jpg']],
        'missing' => [],
        'orphaned' => [
            ['imdb' => 'tt0000200', 'filename' => 'tt0000200.jpg'],
            ['imdb' => 'tt0000100', 'filename' => 'tt0000100.jpg'],
        ],
    ];
    $auditBefore = $audit;
    $auditCalls = 0;
    $auditLoader = static function () use (&$auditCalls, $audit): array {
        $auditCalls++;
        return $audit;
    };
    $result = (new DarkThumbnailCorrelationService($auditLoader))
        ->correlate($resultsPath, $sourceRoot);

    darkCorrelationAssertSame([
        'current_orphan_thumbnails' => 2,
        'removed_is_dark_records' => 6,
        'removed_is_dark_records_with_valid_imdb' => 4,
        'distinct_removed_is_dark_imdb' => 3,
        'orphan_matches_to_removed_dark_shows' => 1,
        'removed_dark_records_matched_to_orphans' => 2,
        'unexplained_orphans' => 1,
        'removed_dark_records_without_orphan_match' => 4,
        'removed_dark_records_without_valid_imdb_mapping' => 2,
        'shared_removed_imdb_values' => 1,
    ], $result['summary'], 'Dark thumbnail correlation summary is incorrect');
    darkCorrelationAssertSame('tt0000100', $result['removed_dark_show_matches'][0]['imdb'],
        'Matching orphan IMDb is incorrect');
    darkCorrelationAssertSame(['shared-a', 'shared-b'],
        array_column($result['removed_dark_show_matches'][0]['removed_records'], 'identifier'),
        'Shared IMDb removed records are incorrect or duplicate evidence inflated the result');
    darkCorrelationAssertSame([
        ['imdb' => 'tt0000200', 'filename' => 'tt0000200.jpg'],
    ], $result['unexplained_orphans'], 'Non-dark evidence incorrectly explained an orphan');
    darkCorrelationAssertSame([
        'invalid_or_missing_source_imdb',
        'source_show_not_found',
        'no_orphan_thumbnail_file',
        'currently_referenced',
    ], array_column($result['removed_dark_records_without_orphan_match'], 'reason'),
    'Unmatched dark records are classified incorrectly');
    darkCorrelationAssertSame('tt0000100', $result['shared_removed_imdb'][0]['imdb'],
        'Shared removed IMDb summary is incorrect');
    darkCorrelationAssertSame(1, $auditCalls, 'Thumbnail audit was loaded more than once');
    darkCorrelationAssertSame($auditBefore, $audit, 'Thumbnail audit fixture was mutated');
    darkCorrelationAssertSame($treeBefore, darkCorrelationTreeState($root),
        'Correlation mutated its evidence files');

    file_put_contents($root . '/malformed.json', '{invalid');
    darkCorrelationExpectFailure(
        fn() => (new DarkThumbnailCorrelationService($auditLoader))
            ->correlate($root . '/malformed.json', $sourceRoot),
        'invalid JSON'
    );
    darkCorrelationExpectFailure(
        fn() => (new DarkThumbnailCorrelationService($auditLoader))
            ->correlate($root . '/unreadable.json', $sourceRoot),
        'unavailable'
    );
} finally {
    darkCorrelationRemoveTree($root);
}

echo "DarkThumbnailCorrelationService tests passed\n";
