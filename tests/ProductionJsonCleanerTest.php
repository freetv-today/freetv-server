<?php

declare(strict_types=1);

require_once __DIR__ . '/../tools/lib/ProductionJsonCleaner.php';

use FreeTV\Tools\ProductionJsonCleaner;

function cleanerAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function cleanerJson(string $path, array $value): void
{
    file_put_contents($path, json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
}

/** @return array{dir: string, playlists: array<string, array>, audit: array} */
function cleanerFixture(): array
{
    $dir = sys_get_temp_dir() . '/freetv-cleaner-' . bin2hex(random_bytes(6));
    mkdir($dir);
    $definitions = [
        'freetv.json' => [['identifier' => 'shared', 'title' => 'Remove me', 'custom' => ['untouched' => true]]],
        'ftv-british.json' => [['identifier' => 'shared', 'title' => 'Keep shared', 'custom' => ['number' => 12]]],
        'ftv-holidays.json' => [['identifier' => 'holiday', 'title' => 'Keep holiday', 'desc' => 'exact']],
        'ftv-movies.json' => [['identifier' => 'movie', 'title' => 'Keep movie', 'status' => 'disabled']],
    ];
    $playlists = [];
    $results = [];
    foreach ($definitions as $filename => $shows) {
        $playlists[$filename] = ['dbtitle' => $filename, 'metadata' => ['preserve' => $filename], 'shows' => $shows];
        cleanerJson($dir . '/' . $filename, $playlists[$filename]);
        foreach ($shows as $show) {
            $results[] = [
                'timestamp' => '2026-01-01T00:00:00Z',
                'playlist' => $filename,
                'identifier' => $show['identifier'],
                'is_dark' => $filename === 'freetv.json',
            ];
        }
    }
    $audit = ['results' => $results];
    cleanerJson($dir . '/results.json', $audit);
    return ['dir' => $dir, 'playlists' => $playlists, 'audit' => $audit];
}

function cleanerRemove(string $path): void
{
    if (!file_exists($path)) {
        return;
    }
    if (is_dir($path)) {
        foreach (scandir($path) ?: [] as $entry) {
            if ($entry !== '.' && $entry !== '..') {
                cleanerRemove($path . '/' . $entry);
            }
        }
        rmdir($path);
    } else {
        unlink($path);
    }
}

function cleanerExpectFailure(callable $operation, string $needle): void
{
    try {
        $operation();
    } catch (Throwable $exception) {
        cleanerAssert(str_contains($exception->getMessage(), $needle), "Unexpected failure: {$exception->getMessage()}");
        return;
    }
    throw new RuntimeException("Expected failure containing {$needle}");
}

$cleaner = new ProductionJsonCleaner();
$fixture = cleanerFixture();
try {
    $beforeHashes = [];
    foreach (array_merge(ProductionJsonCleaner::PLAYLIST_FILES, ['results.json']) as $filename) {
        $beforeHashes[$filename] = hash_file('sha256', $fixture['dir'] . '/' . $filename);
    }

    $validated = $cleaner->validate($fixture['dir']);
    cleanerAssert($validated['report']['freetv.json']['removed'] === 1, 'Dry run did not identify dark show');
    cleanerAssert($validated['report']['ftv-british.json']['removed'] === 0, 'Pair-key matching removed same identifier from another playlist');
    cleanerAssert(!is_dir($fixture['dir'] . '/cleaned'), 'Dry run wrote output');

    $written = $cleaner->write($fixture['dir']);
    cleanerAssert($written === $fixture['dir'] . '/cleaned', 'Unexpected output directory');
    $cleanedBritish = json_decode((string) file_get_contents($written . '/ftv-british.json'), true, 512, JSON_THROW_ON_ERROR);
    cleanerAssert($cleanedBritish === $fixture['playlists']['ftv-british.json'], 'False audit record was not preserved field-equivalently');
    $cleanedDefault = json_decode((string) file_get_contents($written . '/freetv.json'), true, 512, JSON_THROW_ON_ERROR);
    cleanerAssert($cleanedDefault['shows'] === [], 'Dark show was not filtered');
    foreach ($beforeHashes as $filename => $hash) {
        cleanerAssert(hash_file('sha256', $fixture['dir'] . '/' . $filename) === $hash, "Original {$filename} changed");
    }

    $cases = [
        'missing' => function (array &$audit): void { array_pop($audit['results']); },
        'duplicate' => function (array &$audit): void { $audit['results'][] = $audit['results'][0]; },
        'unknown' => function (array &$audit): void { $audit['results'][] = ['playlist' => 'freetv.json', 'identifier' => 'unknown', 'is_dark' => false]; },
    ];
    foreach ($cases as $name => $mutate) {
        $case = cleanerFixture();
        try {
            $mutate($case['audit']);
            cleanerJson($case['dir'] . '/results.json', $case['audit']);
            cleanerExpectFailure(fn() => $cleaner->validate($case['dir']), $name === 'missing' ? 'Missing audit pair' : ($name === 'duplicate' ? 'duplicate audit pair' : 'unknown audit pair'));
        } finally {
            cleanerRemove($case['dir']);
        }
    }

    $ambiguous = cleanerFixture();
    try {
        $playlist = $ambiguous['playlists']['freetv.json'];
        $playlist['shows'][] = $playlist['shows'][0];
        cleanerJson($ambiguous['dir'] . '/freetv.json', $playlist);
        cleanerExpectFailure(fn() => $cleaner->write($ambiguous['dir']), 'duplicate/ambiguous identifier');
        cleanerAssert(!is_dir($ambiguous['dir'] . '/cleaned'), 'Failed validation left an output directory');
    } finally {
        cleanerRemove($ambiguous['dir']);
    }
} finally {
    cleanerRemove($fixture['dir']);
}

fwrite(STDOUT, "ProductionJsonCleanerTest passed\n");
