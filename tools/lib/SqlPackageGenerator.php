<?php

declare(strict_types=1);

namespace FreeTV\Tools;

use PDO;
use RuntimeException;
use Throwable;

final class SqlPackageGenerator
{
    public const FILES = [
        'schema_create_db' => 'freetv_mariadb_schema-create-db.sql',
        'schema_tables_only' => 'freetv_mariadb_schema-tables-only.sql',
        'full_create_db' => 'freetv_mariadb_full-create-db.sql',
        'full_tables_only' => 'freetv_mariadb_full_data-tables-only.sql',
        'sample_create_db' => 'freetv_mariadb_sample-create-db.sql',
        'sample_tables_only' => 'freetv_mariadb_sample_data-tables-only.sql',
    ];

    public const DATABASE_WRAPPER = <<<'SQL'
CREATE DATABASE IF NOT EXISTS `freetv`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `freetv`;

SQL;

    public function __construct(private string $schemaSql)
    {
        $this->schemaSql = rtrim(str_replace("\r\n", "\n", $this->schemaSql)) . "\n";
        if (preg_match('/^\s*(CREATE\s+DATABASE|USE\s+)/mi', $this->schemaSql)) {
            throw new RuntimeException('Canonical schema must not create or select a database');
        }
        foreach (['app_settings', 'users', 'playlists', 'playlist_shows', 'problem_reports', 'problem_report_ips'] as $table) {
            if (!preg_match('/CREATE\s+TABLE\s+IF\s+NOT\s+EXISTS\s+' . preg_quote($table, '/') . '\b/i', $this->schemaSql)) {
                throw new RuntimeException("Canonical schema is missing table {$table}");
            }
        }
        if (!str_contains($this->schemaSql, "VALUES ('show_ads', 'false', 'viewer')")) {
            throw new RuntimeException('Canonical schema is missing the show_ads=false viewer seed');
        }
    }

    /**
     * @param list<array<string, mixed>> $playlists
     * @param list<array<string, mixed>> $shows
     * @return array{packages: array<string, string>, sample_count: int, playlist_count: int, show_count: int}
     */
    public function generate(array $playlists, array $shows): array
    {
        $this->validateRows($playlists, $shows);
        $dataBody = $this->contentSql($playlists, $shows);
        $sampleShows = $this->sampleShows($playlists, $shows);
        $sampleDataBody = $this->contentSql($playlists, $sampleShows);

        $sampleHeader = <<<'SQL'
-- FreeTV representative sample installation package.
-- Includes the current schema, canonical settings, all playlists, and deterministic sample content.
-- Sample rule: allocate up to 8 shows per playlist, distribute remaining slots in playlist order,
-- then select round-robin across sorted categories while preserving retained source order.
-- Includes no problem reports or users.

SQL;
        $fullHeader = <<<'SQL'
-- FreeTV full official-data installation package.
-- Includes the current schema, canonical settings, and the complete official playlist/show library.
-- Includes no problem reports or users; first-run admin initialization is still required.

SQL;

        $schemaBody = $this->schemaSql;
        $fullBody = $fullHeader . $schemaBody . "\n" . $dataBody;
        $sampleBody = $sampleHeader . $schemaBody . "\n" . $sampleDataBody;

        return [
            'packages' => [
                'schema_create_db' => self::DATABASE_WRAPPER . $schemaBody,
                'schema_tables_only' => $schemaBody,
                'full_create_db' => self::DATABASE_WRAPPER . $fullBody,
                'full_tables_only' => $fullBody,
                'sample_create_db' => self::DATABASE_WRAPPER . $sampleBody,
                'sample_tables_only' => $sampleBody,
            ],
            'sample_count' => count($sampleShows),
            'playlist_count' => count($playlists),
            'show_count' => count($shows),
        ];
    }

    /** @param array<string, string> $packages */
    public function write(string $sqlDirectory, array $packages): void
    {
        $resolved = realpath($sqlDirectory);
        if ($resolved === false || !is_dir($resolved) || basename($resolved) !== 'sql') {
            throw new RuntimeException('SQL output must be an existing directory named sql');
        }
        if (array_keys($packages) !== array_keys(self::FILES)) {
            throw new RuntimeException('Refusing to write an incomplete SQL package set');
        }

        $stage = $resolved . '/.sql-packages.tmp-' . getmypid() . '-' . bin2hex(random_bytes(4));
        if (!mkdir($stage, 0755)) {
            throw new RuntimeException('Could not create SQL package staging directory');
        }
        try {
            foreach (self::FILES as $key => $filename) {
                if (file_put_contents($stage . '/' . $filename, $packages[$key], LOCK_EX) === false) {
                    throw new RuntimeException("Could not stage {$filename}");
                }
            }
            foreach (self::FILES as $filename) {
                if (!rename($stage . '/' . $filename, $resolved . '/' . $filename)) {
                    throw new RuntimeException("Could not publish {$filename}");
                }
            }
            if (!rmdir($stage)) {
                throw new RuntimeException('Could not remove SQL package staging directory');
            }
        } catch (Throwable $exception) {
            foreach (self::FILES as $filename) {
                @unlink($stage . '/' . $filename);
            }
            @rmdir($stage);
            throw $exception;
        }
    }

    public static function literal(mixed $value): string
    {
        if ($value === null) {
            return 'NULL';
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        if (is_int($value)) {
            return (string) $value;
        }
        if (!is_string($value)) {
            throw new RuntimeException('Unsupported SQL literal type');
        }
        return "CONVERT(0x" . bin2hex($value) . ' USING utf8mb4)';
    }

    /** @param list<array<string, mixed>> $playlists @param list<array<string, mixed>> $shows */
    private function validateRows(array $playlists, array $shows): void
    {
        if ($playlists === [] || $shows === []) {
            throw new RuntimeException('Source content is incomplete');
        }
        $playlistIds = [];
        $filenames = [];
        $defaults = 0;
        $previousPlaylistOrder = null;
        foreach ($playlists as $playlist) {
            foreach (['id', 'filename', 'dbtitle', 'is_default', 'sort_order'] as $field) {
                if (!array_key_exists($field, $playlist)) {
                    throw new RuntimeException("Playlist row is missing {$field}");
                }
            }
            if (!is_int($playlist['id']) || !is_string($playlist['filename']) || $playlist['filename'] === '') {
                throw new RuntimeException('Playlist identity is invalid');
            }
            if (isset($playlistIds[$playlist['id']]) || isset($filenames[$playlist['filename']])) {
                throw new RuntimeException('Source contains duplicate playlists');
            }
            if ($previousPlaylistOrder !== null && $playlist['sort_order'] < $previousPlaylistOrder) {
                throw new RuntimeException('Playlists are not deterministically ordered');
            }
            $previousPlaylistOrder = $playlist['sort_order'];
            $playlistIds[$playlist['id']] = $playlist['filename'];
            $filenames[$playlist['filename']] = true;
            $defaults += (int) $playlist['is_default'] === 1 ? 1 : 0;
        }
        if ($defaults !== 1) {
            throw new RuntimeException("Source must contain exactly one default playlist; found {$defaults}");
        }

        $pairs = [];
        $previous = null;
        foreach ($shows as $show) {
            foreach (['id', 'playlist_id', 'identifier', 'title', 'sort_order'] as $field) {
                if (!array_key_exists($field, $show)) {
                    throw new RuntimeException("Show row is missing {$field}");
                }
            }
            if (!isset($playlistIds[$show['playlist_id']]) || !is_string($show['identifier']) || $show['identifier'] === '') {
                throw new RuntimeException('Show identity or playlist FK is invalid');
            }
            $key = $show['playlist_id'] . "\0" . $show['identifier'];
            if (isset($pairs[$key])) {
                throw new RuntimeException('Source contains duplicate playlist/identifier pairs');
            }
            $ordering = [(int) $show['playlist_id'], (int) $show['sort_order'], (int) $show['id']];
            if ($previous !== null && $ordering < $previous) {
                throw new RuntimeException('Shows are not deterministically ordered');
            }
            $previous = $ordering;
            $pairs[$key] = true;
        }
    }

    /** @param list<array<string, mixed>> $playlists @param list<array<string, mixed>> $shows */
    private function contentSql(array $playlists, array $shows): string
    {
        $playlistColumns = ['id', 'filename', 'dbtitle', 'dbversion', 'author', 'email', 'link', 'lastupdated', 'is_default', 'sort_order'];
        $showColumns = ['playlist_id', 'category', 'status', 'identifier', 'title', 'description', 'start_year', 'end_year', 'imdb', 'group_name', 'sort_order'];
        $packageIdsBySourceId = [];
        $playlistRows = [];
        foreach ($playlists as $position => $playlist) {
            $packageId = $position + 1;
            $packageIdsBySourceId[$playlist['id']] = $packageId;
            $playlistRows[] = $this->tuple($playlistColumns, ['id' => $packageId] + $playlist);
        }
        $showRows = [];
        foreach ($shows as $show) {
            $values = [];
            foreach ($showColumns as $column) {
                $values[] = $column === 'playlist_id'
                    ? self::literal($packageIdsBySourceId[$show['playlist_id']])
                    : self::literal($show[$column] ?? null);
            }
            $showRows[] = '(' . implode(', ', $values) . ')';
        }

        return "START TRANSACTION;\n\n"
            . $this->insert('playlists', $playlistColumns, $playlistRows)
            . "\n"
            . $this->insert('playlist_shows', $showColumns, $showRows)
            . "\nCOMMIT;\n";
    }

    /** @param list<string> $columns @param list<string> $rows */
    private function insert(string $table, array $columns, array $rows): string
    {
        return 'INSERT INTO ' . $table . ' (`' . implode('`, `', $columns) . "`) VALUES\n  "
            . implode(",\n  ", $rows) . ";\n";
    }

    /** @param list<string> $columns @param array<string, mixed> $row */
    private function tuple(array $columns, array $row): string
    {
        return '(' . implode(', ', array_map(
            static fn(string $column): string => self::literal($row[$column] ?? null),
            $columns
        )) . ')';
    }

    /** @param list<array<string, mixed>> $playlists @param list<array<string, mixed>> $shows @return list<array<string, mixed>> */
    private function sampleShows(array $playlists, array $shows): array
    {
        $target = min(50, count($shows));
        $showsByPlaylist = [];
        foreach ($shows as $show) {
            $showsByPlaylist[$show['playlist_id']][] = $show;
        }
        $quotas = [];
        $assigned = 0;
        foreach ($playlists as $playlist) {
            $available = count($showsByPlaylist[$playlist['id']] ?? []);
            $quotas[$playlist['id']] = min(8, $available);
            $assigned += $quotas[$playlist['id']];
        }
        while ($assigned < $target) {
            $progress = false;
            foreach ($playlists as $playlist) {
                $id = $playlist['id'];
                if ($assigned < $target && $quotas[$id] < count($showsByPlaylist[$id] ?? [])) {
                    $quotas[$id]++;
                    $assigned++;
                    $progress = true;
                }
            }
            if (!$progress) {
                break;
            }
        }

        $sample = [];
        foreach ($playlists as $playlist) {
            $groups = [];
            foreach ($showsByPlaylist[$playlist['id']] ?? [] as $show) {
                $category = is_string($show['category'] ?? null) ? $show['category'] : '';
                $groups[$category][] = $show;
            }
            uksort($groups, static fn(string $left, string $right): int => strcasecmp($left, $right) ?: strcmp($left, $right));
            $selected = [];
            while (count($selected) < $quotas[$playlist['id']]) {
                foreach ($groups as &$categoryShows) {
                    if ($categoryShows !== [] && count($selected) < $quotas[$playlist['id']]) {
                        $selected[] = array_shift($categoryShows);
                    }
                }
                unset($categoryShows);
            }
            usort($selected, static fn(array $left, array $right): int => [$left['sort_order'], $left['id']] <=> [$right['sort_order'], $right['id']]);
            foreach ($selected as $sortOrder => $show) {
                $show['sort_order'] = $sortOrder;
                $sample[] = $show;
            }
        }
        return $sample;
    }
}

final class SqlPackageSource
{
    private const REQUIRED_COLUMNS = [
        'app_settings' => ['id', 'setting_key', 'setting_value', 'scope', 'created_at', 'updated_at'],
        'users' => ['id', 'username', 'password_hash', 'role', 'status', 'created_at', 'last_login_at', 'updated_at'],
        'playlists' => ['id', 'filename', 'dbtitle', 'dbversion', 'author', 'email', 'link', 'lastupdated', 'is_default', 'sort_order', 'created_at', 'updated_at'],
        'playlist_shows' => ['id', 'playlist_id', 'category', 'status', 'identifier', 'title', 'description', 'start_year', 'end_year', 'imdb', 'group_name', 'sort_order', 'created_at', 'updated_at'],
        'problem_reports' => ['id', 'playlist_id', 'playlist_show_id'],
        'problem_report_ips' => ['id'],
    ];

    public function __construct(private PDO $pdo, private string $databaseName)
    {
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    }

    /** @return array{playlists: list<array<string, mixed>>, shows: list<array<string, mixed>>, counts: array<string, int>} */
    public function read(): array
    {
        $this->validateSchema();
        $counts = [];
        foreach (array_keys(self::REQUIRED_COLUMNS) as $table) {
            $counts[$table] = (int) $this->pdo->query("SELECT COUNT(*) FROM `{$table}`")->fetchColumn();
        }
        if ($counts['problem_reports'] !== 0 || $counts['problem_report_ips'] !== 0) {
            throw new RuntimeException('Source problem-report tables must be empty before packaging');
        }
        $playlists = $this->pdo->query(
            'SELECT id, filename, dbtitle, dbversion, author, email, link, lastupdated, is_default, sort_order FROM playlists ORDER BY sort_order, id'
        )->fetchAll();
        $shows = $this->pdo->query(
            'SELECT id, playlist_id, category, status, identifier, title, description, start_year, end_year, imdb, group_name, sort_order FROM playlist_shows ORDER BY playlist_id, sort_order, id'
        )->fetchAll();
        foreach ($playlists as &$playlist) {
            $playlist['id'] = (int) $playlist['id'];
            $playlist['is_default'] = (int) $playlist['is_default'];
            $playlist['sort_order'] = (int) $playlist['sort_order'];
        }
        unset($playlist);
        foreach ($shows as &$show) {
            $show['id'] = (int) $show['id'];
            $show['playlist_id'] = (int) $show['playlist_id'];
            $show['sort_order'] = (int) $show['sort_order'];
        }
        unset($show);
        return ['playlists' => $playlists, 'shows' => $shows, 'counts' => $counts];
    }

    private function validateSchema(): void
    {
        $statement = $this->pdo->prepare('SELECT TABLE_NAME, COLUMN_NAME, ENGINE FROM information_schema.COLUMNS c JOIN information_schema.TABLES t USING (TABLE_SCHEMA, TABLE_NAME) WHERE c.TABLE_SCHEMA = :schema');
        $statement->execute([':schema' => $this->databaseName]);
        $tables = [];
        foreach ($statement->fetchAll() as $row) {
            $tables[$row['TABLE_NAME']]['columns'][] = $row['COLUMN_NAME'];
            $tables[$row['TABLE_NAME']]['engine'] = $row['ENGINE'];
        }
        foreach (self::REQUIRED_COLUMNS as $table => $columns) {
            if (!isset($tables[$table])) {
                throw new RuntimeException("Required source table is missing: {$table}");
            }
            if (strtoupper((string) $tables[$table]['engine']) !== 'INNODB') {
                throw new RuntimeException("Required source table is not InnoDB: {$table}");
            }
            foreach ($columns as $column) {
                if (!in_array($column, $tables[$table]['columns'], true)) {
                    throw new RuntimeException("Required source column is missing: {$table}.{$column}");
                }
            }
        }
    }
}
