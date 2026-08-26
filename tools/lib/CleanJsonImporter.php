<?php

declare(strict_types=1);

namespace FreeTV\Tools;

use DateTimeImmutable;
use DateTimeZone;
use JsonException;
use PDO;
use RuntimeException;
use Throwable;

interface ContentDatabase
{
    /** @return array{legacy_shows: bool} */
    public function validateSchema(): array;
    public function fingerprint(string $table): string;
    public function begin(): void;
    public function commit(): void;
    public function rollback(): void;
    public function deleteAll(string $table): void;
    /** @param array<string, mixed> $values */
    public function insertPlaylist(array $values): int;
    /** @param array<string, mixed> $values */
    public function insertShow(array $values): void;
    /** @return array<string, mixed> */
    public function contentState(): array;
}

final class CleanJsonImporter
{
    public const RESET_TABLES = ['problem_report_ips', 'problem_reports', 'playlist_shows', 'playlists'];
    public const PRESERVED_TABLES = ['users', 'app_settings'];

    /**
     * @param list<string>|null $order
     * @return array{playlists: list<array>, show_count: int, default: string, order: list<string>, dark_pairs: array<string, true>}
     */
    public function validate(string $directory, string $default, ?array $order = null): array
    {
        $resolved = realpath($directory);
        if ($resolved === false || !is_dir($resolved)) {
            throw new RuntimeException("Cleaned data directory does not exist: {$directory}");
        }
        $order ??= ProductionJsonCleaner::PLAYLIST_FILES;
        if (count($order) !== count(ProductionJsonCleaner::PLAYLIST_FILES)
            || array_diff($order, ProductionJsonCleaner::PLAYLIST_FILES) !== []
            || array_diff(ProductionJsonCleaner::PLAYLIST_FILES, $order) !== []) {
            throw new RuntimeException('Order must list each expected playlist exactly once');
        }
        if (!in_array($default, $order, true)) {
            throw new RuntimeException('Default playlist must be one of the imported playlist filenames');
        }

        $playlists = [];
        $showCount = 0;
        $knownPairs = [];
        foreach ($order as $playlistOrder => $filename) {
            $document = $this->decode($resolved . DIRECTORY_SEPARATOR . $filename);
            if (isset($document['filename']) && $document['filename'] !== $filename) {
                throw new RuntimeException("{$filename}: embedded filename does not match");
            }
            $dbtitle = $this->requiredString($document, 'dbtitle', $filename);
            if (!isset($document['shows']) || !is_array($document['shows']) || !array_is_list($document['shows'])) {
                throw new RuntimeException("{$filename}: shows must be an array");
            }
            $shows = [];
            foreach ($document['shows'] as $showOrder => $show) {
                if (!is_array($show)) {
                    throw new RuntimeException("{$filename}: show at position {$showOrder} must be an object");
                }
                $identifier = $this->requiredString($show, 'identifier', "{$filename} show {$showOrder}");
                $key = self::pairKey($filename, $identifier);
                if (isset($knownPairs[$key])) {
                    throw new RuntimeException("{$filename}: duplicate identifier {$identifier}");
                }
                $knownPairs[$key] = true;
                $group = null;
                if (array_key_exists('group', $show)) {
                    if (!is_string($show['group'])) {
                        throw new RuntimeException("{$filename} / {$identifier}: group must be a string");
                    }
                    $group = trim($show['group']);
                    $group = $group === '' ? null : $group;
                }
                $shows[] = [
                    'category' => $this->nullableString($show, 'category', $filename, $identifier),
                    'status' => $this->optionalString($show, 'status', 'active', $filename, $identifier),
                    'identifier' => $identifier,
                    'title' => $this->requiredString($show, 'title', "{$filename} / {$identifier}"),
                    'description' => $this->nullableString($show, 'desc', $filename, $identifier),
                    'start_year' => $this->nullableString($show, 'start', $filename, $identifier),
                    'end_year' => $this->nullableString($show, 'end', $filename, $identifier),
                    'imdb' => $this->nullableString($show, 'imdb', $filename, $identifier),
                    'group_name' => $group,
                    'sort_order' => $showOrder,
                ];
                $showCount++;
            }
            $playlists[] = [
                'filename' => $filename,
                'dbtitle' => $dbtitle,
                'dbversion' => $this->nullableString($document, 'dbversion', $filename),
                'author' => $this->nullableString($document, 'author', $filename),
                'email' => $this->nullableString($document, 'email', $filename),
                'link' => $this->nullableString($document, 'link', $filename),
                'lastupdated' => $this->datetime($document['lastupdated'] ?? null, $filename),
                'is_default' => $filename === $default ? 1 : 0,
                'sort_order' => $playlistOrder,
                'shows' => $shows,
            ];
        }

        $darkPairs = $this->loadDarkPairs($resolved . DIRECTORY_SEPARATOR . 'results.json');
        foreach ($darkPairs as $key => $_) {
            if (isset($knownPairs[$key])) {
                [$playlist, $identifier] = explode("\0", $key, 2);
                throw new RuntimeException("Cleaned data still contains dark item {$playlist} / {$identifier}");
            }
        }

        return [
            'playlists' => $playlists,
            'show_count' => $showCount,
            'default' => $default,
            'order' => $order,
            'dark_pairs' => $darkPairs,
        ];
    }

    /** @param array{playlists: list<array>, show_count: int, default: string, order: list<string>} $data */
    public function replace(ContentDatabase $database, array $data): array
    {
        $schema = $database->validateSchema();
        $beforeUsers = $database->fingerprint('users');
        $beforeSettings = $database->fingerprint('app_settings');
        $database->begin();
        try {
            foreach (self::RESET_TABLES as $table) {
                $database->deleteAll($table);
            }
            foreach ($data['playlists'] as $playlist) {
                $shows = $playlist['shows'];
                unset($playlist['shows']);
                $playlistId = $database->insertPlaylist($playlist);
                foreach ($shows as $show) {
                    $database->insertShow(['playlist_id' => $playlistId] + $show);
                }
            }
            $state = $database->contentState();
            $this->verifyState($state, $data, $beforeUsers, $beforeSettings, $database);
            $database->commit();
        } catch (Throwable $exception) {
            $database->rollback();
            throw $exception;
        }
        return ['schema' => $schema, 'state' => $state];
    }

    private function verifyState(array $state, array $data, string $users, string $settings, ContentDatabase $database): void
    {
        if (($state['playlists'] ?? null) !== count($data['playlists'])
            || ($state['playlist_shows'] ?? null) !== $data['show_count']
            || ($state['problem_reports'] ?? null) !== 0
            || ($state['problem_report_ips'] ?? null) !== 0
            || ($state['duplicate_shows'] ?? null) !== 0
            || ($state['default_count'] ?? null) !== 1
            || ($state['playlist_order'] ?? null) !== $data['order']
            || ($state['show_order_valid'] ?? null) !== true) {
            throw new RuntimeException('Post-import content validation failed');
        }
        foreach (($state['show_pairs'] ?? []) as $pair) {
            if (isset($data['dark_pairs'][self::pairKey($pair['playlist'], $pair['identifier'])])) {
                throw new RuntimeException('Post-import validation found an audited dark show');
            }
        }
        if ($database->fingerprint('users') !== $users || $database->fingerprint('app_settings') !== $settings) {
            throw new RuntimeException('A preserved table changed during import');
        }
    }

    /** @return array<string, true> */
    private function loadDarkPairs(string $path): array
    {
        if (!is_file($path)) {
            return [];
        }
        $document = $this->decode($path);
        if (!isset($document['results']) || !is_array($document['results']) || !array_is_list($document['results'])) {
            throw new RuntimeException('results.json: results must be an array');
        }
        $seen = [];
        $dark = [];
        foreach ($document['results'] as $position => $result) {
            if (!is_array($result)
                || !is_string($result['playlist'] ?? null)
                || trim($result['playlist']) === ''
                || !is_string($result['identifier'] ?? null)
                || trim($result['identifier']) === ''
                || !is_bool($result['is_dark'] ?? null)) {
                throw new RuntimeException("results.json: invalid result at position {$position}");
            }
            $key = self::pairKey($result['playlist'], $result['identifier']);
            if (isset($seen[$key])) {
                throw new RuntimeException('results.json: duplicate playlist/identifier pair');
            }
            $seen[$key] = true;
            if ($result['is_dark']) {
                $dark[$key] = true;
            }
        }
        return $dark;
    }

    private function requiredString(array $row, string $field, string $context): string
    {
        $value = $row[$field] ?? null;
        if (!is_string($value) || trim($value) === '') {
            throw new RuntimeException("{$context}: {$field} must be a non-empty string");
        }
        return $value;
    }

    private function optionalString(array $row, string $field, string $default, string $playlist, string $identifier): string
    {
        if (!array_key_exists($field, $row)) {
            return $default;
        }
        $value = $this->nullableString($row, $field, $playlist, $identifier);
        return $value ?? $default;
    }

    private function nullableString(array $row, string $field, string $context, ?string $identifier = null): ?string
    {
        if (!array_key_exists($field, $row) || $row[$field] === null || $row[$field] === '') {
            return null;
        }
        if (!is_string($row[$field])) {
            $suffix = $identifier === null ? '' : " / {$identifier}";
            throw new RuntimeException("{$context}{$suffix}: {$field} must be a string or null");
        }
        return $row[$field];
    }

    private function datetime(mixed $value, string $filename): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (!is_string($value)) {
            throw new RuntimeException("{$filename}: lastupdated must be a timestamp string or null");
        }
        try {
            return (new DateTimeImmutable($value))->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        } catch (Throwable) {
            throw new RuntimeException("{$filename}: lastupdated is invalid");
        }
    }

    /** @return array<string, mixed> */
    private function decode(string $path): array
    {
        if (!is_file($path)) {
            throw new RuntimeException("Missing required file: {$path}");
        }
        try {
            $decoded = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException("Invalid JSON in {$path}: {$exception->getMessage()}");
        }
        if (!is_array($decoded)) {
            throw new RuntimeException("JSON root must be an object: {$path}");
        }
        return $decoded;
    }

    private static function pairKey(string $playlist, string $identifier): string
    {
        return $playlist . "\0" . $identifier;
    }
}

final class MariaDbContentDatabase implements ContentDatabase
{
    private const REQUIRED_COLUMNS = [
        'playlists' => ['id', 'filename', 'dbtitle', 'dbversion', 'author', 'email', 'link', 'lastupdated', 'is_default', 'sort_order'],
        'playlist_shows' => ['id', 'playlist_id', 'category', 'status', 'identifier', 'title', 'description', 'start_year', 'end_year', 'imdb', 'group_name', 'sort_order'],
        'problem_reports' => ['id'],
        'problem_report_ips' => ['id'],
        'users' => ['id'],
        'app_settings' => ['id'],
    ];

    public function __construct(private PDO $pdo, private string $databaseName)
    {
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    }

    public function validateSchema(): array
    {
        $stmt = $this->pdo->prepare('SELECT TABLE_NAME, COLUMN_NAME, ENGINE FROM information_schema.COLUMNS c JOIN information_schema.TABLES t USING (TABLE_SCHEMA, TABLE_NAME) WHERE c.TABLE_SCHEMA = :schema');
        $stmt->execute([':schema' => $this->databaseName]);
        $tables = [];
        foreach ($stmt->fetchAll() as $row) {
            $tables[$row['TABLE_NAME']]['columns'][] = $row['COLUMN_NAME'];
            $tables[$row['TABLE_NAME']]['engine'] = $row['ENGINE'];
        }
        foreach (self::REQUIRED_COLUMNS as $table => $columns) {
            if (!isset($tables[$table])) {
                throw new RuntimeException("Required table is missing: {$table}");
            }
            if (strtoupper((string) $tables[$table]['engine']) !== 'INNODB') {
                throw new RuntimeException("Required table is not transactional InnoDB: {$table}");
            }
            foreach ($columns as $column) {
                if (!in_array($column, $tables[$table]['columns'], true)) {
                    throw new RuntimeException("Required column is missing: {$table}.{$column}");
                }
            }
        }
        return ['legacy_shows' => isset($tables['shows'])];
    }

    public function fingerprint(string $table): string
    {
        $this->assertTable($table, CleanJsonImporter::PRESERVED_TABLES);
        $rows = $this->pdo->query("SELECT * FROM `{$table}` ORDER BY id")->fetchAll();
        return hash('sha256', serialize($rows));
    }

    public function begin(): void { $this->pdo->beginTransaction(); }
    public function commit(): void { $this->pdo->commit(); }
    public function rollback(): void { if ($this->pdo->inTransaction()) { $this->pdo->rollBack(); } }

    public function deleteAll(string $table): void
    {
        $this->assertTable($table, CleanJsonImporter::RESET_TABLES);
        $this->pdo->exec("DELETE FROM `{$table}`");
    }

    public function insertPlaylist(array $values): int
    {
        $sql = 'INSERT INTO playlists (filename, dbtitle, dbversion, author, email, link, lastupdated, is_default, sort_order) VALUES (:filename, :dbtitle, :dbversion, :author, :email, :link, :lastupdated, :is_default, :sort_order)';
        $this->pdo->prepare($sql)->execute($this->parameters($values));
        return (int) $this->pdo->lastInsertId();
    }

    public function insertShow(array $values): void
    {
        $sql = 'INSERT INTO playlist_shows (playlist_id, category, status, identifier, title, description, start_year, end_year, imdb, group_name, sort_order) VALUES (:playlist_id, :category, :status, :identifier, :title, :description, :start_year, :end_year, :imdb, :group_name, :sort_order)';
        $this->pdo->prepare($sql)->execute($this->parameters($values));
    }

    public function contentState(): array
    {
        $count = fn(string $table): int => (int) $this->pdo->query("SELECT COUNT(*) FROM `{$table}`")->fetchColumn();
        $order = $this->pdo->query('SELECT filename FROM playlists ORDER BY sort_order, id')->fetchAll(PDO::FETCH_COLUMN);
        $invalidOrder = (int) $this->pdo->query('SELECT COUNT(*) FROM playlist_shows s WHERE s.sort_order <> (SELECT COUNT(*) FROM playlist_shows earlier WHERE earlier.playlist_id = s.playlist_id AND (earlier.sort_order < s.sort_order OR (earlier.sort_order = s.sort_order AND earlier.id < s.id)))')->fetchColumn();
        $showPairs = $this->pdo->query('SELECT p.filename AS playlist, s.identifier FROM playlist_shows s JOIN playlists p ON p.id = s.playlist_id')->fetchAll();
        return [
            'playlists' => $count('playlists'),
            'playlist_shows' => $count('playlist_shows'),
            'problem_reports' => $count('problem_reports'),
            'problem_report_ips' => $count('problem_report_ips'),
            'duplicate_shows' => (int) $this->pdo->query('SELECT COUNT(*) FROM (SELECT playlist_id, identifier FROM playlist_shows GROUP BY playlist_id, identifier HAVING COUNT(*) > 1) duplicates')->fetchColumn(),
            'default_count' => (int) $this->pdo->query('SELECT COUNT(*) FROM playlists WHERE is_default = 1')->fetchColumn(),
            'playlist_order' => $order,
            'show_order_valid' => $invalidOrder === 0,
            'show_pairs' => $showPairs,
        ];
    }

    private function parameters(array $values): array
    {
        $parameters = [];
        foreach ($values as $key => $value) {
            $parameters[':' . $key] = $value;
        }
        return $parameters;
    }

    private function assertTable(string $table, array $allowed): void
    {
        if (!in_array($table, $allowed, true)) {
            throw new RuntimeException("Refusing operation on non-allowlisted table {$table}");
        }
    }
}
