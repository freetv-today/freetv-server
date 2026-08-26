<?php

declare(strict_types=1);

require_once __DIR__ . '/../tools/lib/ProductionJsonCleaner.php';
require_once __DIR__ . '/../tools/lib/CleanJsonImporter.php';

use FreeTV\Tools\CleanJsonImporter;
use FreeTV\Tools\ContentDatabase;
use FreeTV\Tools\ProductionJsonCleaner;

function importerAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

final class FakeContentDatabase implements ContentDatabase
{
    public array $tables = [
        'users' => [['id' => 9, 'username' => 'preserve']],
        'app_settings' => [['id' => 4, 'setting_key' => 'show_ads', 'setting_value' => 'true']],
        'problem_report_ips' => [['id' => 1]],
        'problem_reports' => [['id' => 1]],
        'playlist_shows' => [['id' => 50, 'playlist_id' => 40, 'identifier' => 'old', 'sort_order' => 0]],
        'playlists' => [['id' => 40, 'filename' => 'old.json', 'is_default' => 1, 'sort_order' => 0]],
    ];
    public bool $schemaValid = true;
    public bool $legacyShows = true;
    public ?int $failShowNumber = null;
    private ?array $snapshot = null;
    private int $showInsertions = 0;

    public function validateSchema(): array
    {
        if (!$this->schemaValid) {
            throw new RuntimeException('Required table is missing: playlist_shows');
        }
        return ['legacy_shows' => $this->legacyShows];
    }
    public function fingerprint(string $table): string { return hash('sha256', serialize($this->tables[$table])); }
    public function begin(): void { $this->snapshot = $this->tables; }
    public function commit(): void { $this->snapshot = null; }
    public function rollback(): void { if ($this->snapshot !== null) { $this->tables = $this->snapshot; $this->snapshot = null; } }
    public function deleteAll(string $table): void { $this->tables[$table] = []; }
    public function insertPlaylist(array $values): int
    {
        $values['id'] = 100 + count($this->tables['playlists']);
        $this->tables['playlists'][] = $values;
        return $values['id'];
    }
    public function insertShow(array $values): void
    {
        $this->showInsertions++;
        if ($this->failShowNumber === $this->showInsertions) {
            throw new RuntimeException('injected insertion failure');
        }
        $values['id'] = 1000 + count($this->tables['playlist_shows']);
        $this->tables['playlist_shows'][] = $values;
    }
    public function contentState(): array
    {
        $identifiers = [];
        $duplicates = 0;
        foreach ($this->tables['playlist_shows'] as $show) {
            $key = $show['playlist_id'] . "\0" . $show['identifier'];
            $duplicates += isset($identifiers[$key]) ? 1 : 0;
            $identifiers[$key] = true;
        }
        $playlists = $this->tables['playlists'];
        usort($playlists, fn($a, $b) => [$a['sort_order'], $a['id']] <=> [$b['sort_order'], $b['id']]);
        $validOrder = true;
        foreach ($playlists as $playlist) {
            $orders = array_column(array_values(array_filter($this->tables['playlist_shows'], fn($show) => $show['playlist_id'] === $playlist['id'])), 'sort_order');
            sort($orders);
            $validOrder = $validOrder && $orders === ($orders === [] ? [] : range(0, count($orders) - 1));
        }
        return [
            'playlists' => count($playlists),
            'playlist_shows' => count($this->tables['playlist_shows']),
            'problem_reports' => count($this->tables['problem_reports']),
            'problem_report_ips' => count($this->tables['problem_report_ips']),
            'duplicate_shows' => $duplicates,
            'default_count' => count(array_filter($playlists, fn($row) => $row['is_default'] === 1)),
            'playlist_order' => array_column($playlists, 'filename'),
            'show_order_valid' => $validOrder,
            'show_pairs' => array_map(function (array $show) use ($playlists): array {
                $playlist = array_values(array_filter(
                    $playlists,
                    fn(array $row): bool => $row['id'] === $show['playlist_id']
                ))[0];
                return ['playlist' => $playlist['filename'], 'identifier' => $show['identifier']];
            }, $this->tables['playlist_shows']),
        ];
    }
}

function importerFixture(): string
{
    $dir = sys_get_temp_dir() . '/freetv-importer-' . bin2hex(random_bytes(6));
    mkdir($dir);
    $audit = [];
    foreach (ProductionJsonCleaner::PLAYLIST_FILES as $index => $filename) {
        $show = [
            'category' => 'category-' . $index,
            'status' => $index === 1 ? 'disabled' : 'active',
            'identifier' => 'show-' . $index,
            'title' => 'Title ' . $index,
            'desc' => 'Description ' . $index,
            'start' => (string) (1980 + $index),
            'end' => (string) (1990 + $index),
            'imdb' => 'tt000000' . $index,
        ];
        if ($index === 0) {
            $show['group'] = '  Group One  ';
        }
        $document = [
            'lastupdated' => '2026-08-0' . ($index + 1) . 'T12:00:00.000Z',
            'dbtitle' => 'Playlist ' . $index,
            'dbversion' => '1.0',
            'author' => 'Author',
            'email' => 'author@example.test',
            'link' => 'https://example.test',
            'shows' => [$show],
        ];
        if ($filename !== 'ftv-movies.json') {
            $document['filename'] = $filename;
        }
        file_put_contents($dir . '/' . $filename, json_encode($document, JSON_THROW_ON_ERROR));
        $audit[] = ['playlist' => $filename, 'identifier' => $show['identifier'], 'is_dark' => false];
    }
    $audit[] = ['playlist' => 'freetv.json', 'identifier' => 'removed-dark-item', 'is_dark' => true];
    file_put_contents($dir . '/results.json', json_encode(['results' => $audit], JSON_THROW_ON_ERROR));
    return $dir;
}

function importerRemove(string $dir): void
{
    foreach (scandir($dir) ?: [] as $entry) {
        if ($entry !== '.' && $entry !== '..') { unlink($dir . '/' . $entry); }
    }
    rmdir($dir);
}

$directory = importerFixture();
try {
    $importer = new CleanJsonImporter();
    $database = new FakeContentDatabase();
    $beforeValidation = $database->tables;
    $order = ['ftv-movies.json', 'freetv.json', 'ftv-british.json', 'ftv-holidays.json'];
    $data = $importer->validate($directory, 'freetv.json', $order);
    importerAssert($database->tables === $beforeValidation, 'Validation mode changed database state');
    importerAssert($data['show_count'] === 4, 'Wrong validated show count');

    $result = $importer->replace($database, $data);
    importerAssert($result['schema']['legacy_shows'] === true, 'Legacy shows table was not reported');
    importerAssert($database->tables['users'] === $beforeValidation['users'], 'Users changed');
    importerAssert($database->tables['app_settings'] === $beforeValidation['app_settings'], 'App settings changed');
    importerAssert($database->tables['problem_reports'] === [], 'Problem reports were not cleared');
    importerAssert($database->tables['problem_report_ips'] === [], 'Problem report IPs were not cleared');
    importerAssert(count($database->tables['playlists']) === 4 && count($database->tables['playlist_shows']) === 4, 'Content was not replaced');
    importerAssert(array_column($database->tables['playlists'], 'filename') === $order, 'Playlist ordering was not preserved');
    importerAssert(array_sum(array_column($database->tables['playlists'], 'is_default')) === 1, 'Default handling failed');
    $firstFreetv = array_values(array_filter($database->tables['playlist_shows'], fn($show) => $show['identifier'] === 'show-0'))[0];
    importerAssert($firstFreetv['description'] === 'Description 0' && $firstFreetv['start_year'] === '1980' && $firstFreetv['end_year'] === '1990', 'Show field mapping failed');
    importerAssert($firstFreetv['group_name'] === 'Group One' && $firstFreetv['sort_order'] === 0, 'Group/order mapping failed');

    $rollbackDatabase = new FakeContentDatabase();
    $rollbackBefore = $rollbackDatabase->tables;
    $rollbackDatabase->failShowNumber = 2;
    try {
        $importer->replace($rollbackDatabase, $data);
        throw new RuntimeException('Expected insertion failure');
    } catch (RuntimeException $exception) {
        importerAssert($exception->getMessage() === 'injected insertion failure', 'Unexpected rollback error');
    }
    importerAssert($rollbackDatabase->tables === $rollbackBefore, 'Insertion failure did not roll back');

    $darkDatabase = new FakeContentDatabase();
    $darkBefore = $darkDatabase->tables;
    $darkData = $data;
    $darkData['dark_pairs']["freetv.json\0show-0"] = true;
    try {
        $importer->replace($darkDatabase, $darkData);
        throw new RuntimeException('Expected dark post-import failure');
    } catch (RuntimeException $exception) {
        importerAssert(str_contains($exception->getMessage(), 'audited dark show'), 'Unexpected dark validation error');
    }
    importerAssert($darkDatabase->tables === $darkBefore, 'Dark post-import failure did not roll back');

    $invalidSchema = new FakeContentDatabase();
    $invalidSchema->schemaValid = false;
    $invalidBefore = $invalidSchema->tables;
    try {
        $importer->replace($invalidSchema, $data);
        throw new RuntimeException('Expected schema failure');
    } catch (RuntimeException $exception) {
        importerAssert(str_contains($exception->getMessage(), 'Required table is missing'), 'Unexpected schema error');
    }
    importerAssert($invalidSchema->tables === $invalidBefore, 'Invalid schema path changed database');

    $legacySource = file_get_contents(__DIR__ . '/../tools/import-clean-json-to-mariadb.php')
        . file_get_contents(__DIR__ . '/../tools/lib/CleanJsonImporter.php');
    foreach (['apdata.key', 'freetv-data', 'thumbnail_path', 'TRUNCATE'] as $forbidden) {
        importerAssert(!str_contains($legacySource, $forbidden), "New importer depends on forbidden legacy concept {$forbidden}");
    }
} finally {
    importerRemove($directory);
}

fwrite(STDOUT, "CleanJsonImporterTest passed\n");
