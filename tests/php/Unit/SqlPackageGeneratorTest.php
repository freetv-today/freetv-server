<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../public/api/admin/DatasetPackage.php';
require_once __DIR__ . '/../../../tools/lib/SqlPackageGenerator.php';

use FreeTV\Admin\DatasetPackage;
use FreeTV\Tools\SqlPackageGenerator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SqlPackageGeneratorTest extends TestCase
{
    private string $root;
    private string $schema;
    private SqlPackageGenerator $generator;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/freetv-sql-generator-' . bin2hex(random_bytes(8));
        self::assertTrue(mkdir($this->root, 0700, true));
        $this->schema = (string) file_get_contents(
            dirname(__DIR__, 3) . '/sql/freetv_mariadb_schema-tables-only.sql'
        );
        $this->generator = new SqlPackageGenerator($this->schema);
    }

    protected function tearDown(): void
    {
        DatasetPackage::removeTree($this->root);
    }

    public function testRepresentativeDatasetProducesDeterministicSixPackageContract(): void
    {
        [$playlists, $shows] = $this->representativeRows();
        $first = $this->generator->generate($playlists, $shows);
        $second = $this->generator->generate($playlists, $shows);

        self::assertSame($first, $second);
        self::assertSame(4, $first['playlist_count']);
        self::assertSame(60, $first['show_count']);
        self::assertSame(50, $first['sample_count']);
        self::assertSame([
            'schema_create_db' => 'freetv_mariadb_schema-create-db.sql',
            'schema_tables_only' => 'freetv_mariadb_schema-tables-only.sql',
            'full_create_db' => 'freetv_mariadb_full-create-db.sql',
            'full_tables_only' => 'freetv_mariadb_full_data-tables-only.sql',
            'sample_create_db' => 'freetv_mariadb_sample-create-db.sql',
            'sample_tables_only' => 'freetv_mariadb_sample_data-tables-only.sql',
        ], SqlPackageGenerator::FILES);

        foreach ([
            ['schema_create_db', 'schema_tables_only'],
            ['full_create_db', 'full_tables_only'],
            ['sample_create_db', 'sample_tables_only'],
        ] as [$createKey, $tablesKey]) {
            self::assertStringStartsWith(SqlPackageGenerator::DATABASE_WRAPPER, $first['packages'][$createKey]);
            self::assertSame(
                $first['packages'][$tablesKey],
                substr($first['packages'][$createKey], strlen(SqlPackageGenerator::DATABASE_WRAPPER))
            );
            self::assertDoesNotMatchRegularExpression(
                '/^\s*(CREATE\s+DATABASE|USE\s+)/mi',
                $first['packages'][$tablesKey]
            );
        }
    }

    public function testGeneratedSqlContainsCanonicalSchemaAndByteExactDataWithoutSensitiveRows(): void
    {
        [$playlists, $shows] = $this->representativeRows();
        $packages = $this->generator->generate($playlists, $shows)['packages'];
        $schema = $packages['schema_tables_only'];
        $full = $packages['full_tables_only'];
        $sample = $packages['sample_tables_only'];

        self::assertStringContainsString('CREATE TABLE IF NOT EXISTS users', $schema);
        self::assertStringNotContainsString('INSERT INTO playlists', $schema);
        self::assertStringContainsString('INSERT INTO playlists (`id`, `filename`, `dbtitle`, `dbversion`, `author`, `email`, `link`, `lastupdated`, `is_default`, `sort_order`)', $full);
        self::assertStringContainsString('INSERT INTO playlist_shows (`playlist_id`, `category`, `status`, `identifier`, `title`, `description`, `start_year`, `end_year`, `imdb`, `group_name`, `sort_order`)', $full);
        self::assertStringContainsString("START TRANSACTION;", $full);
        self::assertStringEndsWith("COMMIT;\n", $full);
        self::assertNotSame($full, $sample);
        foreach (["FreeTV 'Main'", "Author\\Name", "Café 'quoted' \\ title", "Line one\nLine two — Unicode"] as $text) {
            self::assertStringContainsString(SqlPackageGenerator::literal($text), $full);
        }
        self::assertStringContainsString('NULL', $full);
        self::assertStringContainsString('(1, ' . SqlPackageGenerator::literal('freetv.json'), $full);
        self::assertStringContainsString('(4, ' . SqlPackageGenerator::literal('ftv-movies.json'), $full);
        $showRows = $this->showRows($full);
        self::assertCount(60, $showRows);
        self::assertSame(
            [1 => 15, 2 => 15, 3 => 15, 4 => 15],
            array_count_values(array_map(static fn(array $row): int => (int) $row[0], $showRows))
        );

        foreach ($packages as $sql) {
            self::assertDoesNotMatchRegularExpression('/INSERT\s+(?:IGNORE\s+)?INTO\s+users\b/i', $sql);
            self::assertDoesNotMatchRegularExpression('/INSERT\s+(?:IGNORE\s+)?INTO\s+problem_report(?:s|_ips)\b/i', $sql);
            self::assertStringNotContainsString('/synthetic/private/path', $sql);
            self::assertStringNotContainsString('fixture-secret-password', $sql);
            self::assertDoesNotMatchRegularExpression('/generated (?:at|on)/i', $sql);
            self::assertStringNotContainsString("VALUES ('show_ads', 'true', 'viewer')", $sql);
        }
        foreach (['schema_tables_only', 'full_tables_only', 'sample_tables_only'] as $key) {
            self::assertSame(1, substr_count(
                $packages[$key],
                "VALUES ('show_ads', 'false', 'viewer')"
            ));
        }
    }

    #[DataProvider('literalProvider')]
    public function testLiteralProducesExactSql(mixed $value, string $expected): void
    {
        self::assertSame($expected, SqlPackageGenerator::literal($value));
    }

    public static function literalProvider(): array
    {
        $strings = ['', 'ordinary ASCII', "quote' slash\\", 'café 日本', "line one\nline two", "nul\0byte"];
        $cases = [
            'null' => [null, 'NULL'],
            'true' => [true, '1'],
            'false' => [false, '0'],
            'positive integer' => [42, '42'],
            'negative integer' => [-7, '-7'],
        ];
        foreach ($strings as $index => $string) {
            $cases['string ' . $index] = [
                $string,
                'CONVERT(0x' . bin2hex($string) . ' USING utf8mb4)',
            ];
        }
        return $cases;
    }

    #[DataProvider('unsupportedLiteralProvider')]
    public function testLiteralRejectsUnsupportedTypes(mixed $value): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unsupported SQL literal type');
        SqlPackageGenerator::literal($value);
    }

    public static function unsupportedLiteralProvider(): array
    {
        return ['array' => [[]], 'float' => [1.5], 'object' => [new stdClass()]];
    }

    public function testRepresentativeSamplingUsesUnevenQuotasCategoryRoundRobinAndSourceOrder(): void
    {
        [$playlists, $shows] = $this->unevenRows();
        $result = $this->generator->generate($playlists, $shows);
        $sample = $result['packages']['sample_tables_only'];

        self::assertSame(50, $result['sample_count']);
        foreach (range(0, 20) as $position) {
            self::assertStringContainsString(SqlPackageGenerator::literal("p1-{$position}"), $sample);
        }
        foreach (range(0, 8) as $position) {
            self::assertStringContainsString(SqlPackageGenerator::literal("p2-{$position}"), $sample);
        }
        foreach (array_merge(range(0, 14), range(25, 29)) as $position) {
            self::assertStringContainsString(SqlPackageGenerator::literal("p3-{$position}"), $sample);
        }
        foreach (range(15, 24) as $position) {
            self::assertStringNotContainsString(SqlPackageGenerator::literal("p3-{$position}"), $sample);
        }
        self::assertLessThan(
            strpos($sample, SqlPackageGenerator::literal('p3-25')),
            strpos($sample, SqlPackageGenerator::literal('p3-14'))
        );
        $rows = $this->showRows($sample);
        self::assertSame([1 => 21, 2 => 9, 3 => 20], array_count_values(
            array_map(static fn(array $row): int => (int) $row[0], $rows)
        ));
        foreach ([1 => 21, 2 => 9, 3 => 20] as $playlistId => $count) {
            self::assertSame(
                range(0, $count - 1),
                array_map(
                    static fn(array $row): int => (int) $row[10],
                    array_values(array_filter($rows, static fn(array $row): bool => (int) $row[0] === $playlistId))
                )
            );
        }
    }

    public function testSampleRetainsEveryShowWhenDatasetDoesNotExceedCap(): void
    {
        [$playlists, $shows] = $this->unevenRows([5, 7, 8]);
        $result = $this->generator->generate($playlists, $shows);
        self::assertSame(count($shows), $result['sample_count']);
        foreach ($shows as $show) {
            self::assertStringContainsString(
                SqlPackageGenerator::literal($show['identifier']),
                $result['packages']['sample_tables_only']
            );
        }
    }

    #[DataProvider('invalidRowsProvider')]
    public function testRejectsInvalidRowsWithoutCreatingFiles(string $case): void
    {
        [$playlists, $shows] = $this->representativeRows();
        switch ($case) {
            case 'no playlists': $playlists = []; break;
            case 'no shows': $shows = []; break;
            case 'missing playlist field': unset($playlists[0]['dbtitle']); break;
            case 'invalid playlist id': $playlists[0]['id'] = '1'; break;
            case 'duplicate playlist id': $playlists[1]['id'] = $playlists[0]['id']; break;
            case 'duplicate filename': $playlists[1]['filename'] = $playlists[0]['filename']; break;
            case 'no default':
                array_walk($playlists, static function (&$row): void { $row['is_default'] = 0; });
                break;
            case 'multiple defaults': $playlists[1]['is_default'] = 1; break;
            case 'unsorted playlists': $playlists[1]['sort_order'] = -1; break;
            case 'missing show field': unset($shows[0]['title']); break;
            case 'unknown playlist': $shows[0]['playlist_id'] = 999; break;
            case 'duplicate show identifier': $shows[1]['identifier'] = $shows[0]['identifier']; break;
            case 'unsorted shows': $shows[1]['sort_order'] = -1; break;
        }

        try {
            $this->generator->generate($playlists, $shows);
            self::fail('Expected invalid generator input');
        } catch (RuntimeException) {
            self::assertSame([], array_values(array_diff(scandir($this->root), ['.', '..'])));
        }
    }

    public static function invalidRowsProvider(): array
    {
        return array_combine(
            $cases = ['no playlists', 'no shows', 'missing playlist field', 'invalid playlist id',
                'duplicate playlist id', 'duplicate filename', 'no default', 'multiple defaults',
                'unsorted playlists', 'missing show field', 'unknown playlist',
                'duplicate show identifier', 'unsorted shows'],
            array_map(static fn(string $case): array => [$case], $cases)
        );
    }

    #[DataProvider('invalidSchemaProvider')]
    public function testRejectsInvalidCanonicalSchema(string $case, string $message): void
    {
        $schema = match ($case) {
            'database directive' => "CREATE DATABASE freetv;\n" . $this->schema,
            'use directive' => "USE freetv;\n" . $this->schema,
            'missing table' => preg_replace('/CREATE TABLE IF NOT EXISTS users/', 'CREATE TABLE IF NOT EXISTS removed_users', $this->schema, 1),
            'missing seed' => str_replace("VALUES ('show_ads', 'false', 'viewer')", "VALUES ('other', 'false', 'viewer')", $this->schema),
        };
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage($message);
        new SqlPackageGenerator((string) $schema);
    }

    public static function invalidSchemaProvider(): array
    {
        return [
            'create database' => ['database directive', 'must not create or select'],
            'use' => ['use directive', 'must not create or select'],
            'required table' => ['missing table', 'missing table users'],
            'settings seed' => ['missing seed', 'missing the show_ads=false'],
        ];
    }

    public function testWriteCreatesExactSixFilesAndIsRepeatablyStable(): void
    {
        [$playlists, $shows] = $this->representativeRows();
        $packages = $this->generator->generate($playlists, $shows)['packages'];
        $sql = $this->root . '/sql';
        self::assertTrue(mkdir($sql, 0755));

        $this->generator->write($sql, $packages);
        $firstHashes = [];
        foreach (SqlPackageGenerator::FILES as $key => $filename) {
            self::assertSame($packages[$key], file_get_contents($sql . '/' . $filename));
            $firstHashes[$key] = hash_file('sha256', $sql . '/' . $filename);
        }
        self::assertSame([], glob($sql . '/.sql-packages.tmp-*') ?: []);

        $this->generator->write($sql, $packages);
        foreach (SqlPackageGenerator::FILES as $key => $filename) {
            self::assertSame($firstHashes[$key], hash_file('sha256', $sql . '/' . $filename));
        }
        self::assertSame([], glob($sql . '/.sql-packages.tmp-*') ?: []);
    }

    public function testWriteRejectsIncompleteMapAndWrongDirectoryName(): void
    {
        [$playlists, $shows] = $this->representativeRows();
        $packages = $this->generator->generate($playlists, $shows)['packages'];
        $sql = $this->root . '/sql';
        $wrong = $this->root . '/output';
        mkdir($sql);
        mkdir($wrong);
        unset($packages['sample_tables_only']);

        try {
            $this->generator->write($sql, $packages);
            self::fail('Incomplete package map accepted');
        } catch (RuntimeException $exception) {
            self::assertSame('Refusing to write an incomplete SQL package set', $exception->getMessage());
        }
        self::assertSame([], array_values(array_diff(scandir($sql), ['.', '..'])));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('existing directory named sql');
        $this->generator->write($wrong, $this->generator->generate($playlists, $shows)['packages']);
    }

    private function representativeRows(): array
    {
        $playlists = [];
        $shows = [];
        $showId = 10;
        foreach (['freetv.json', 'ftv-british.json', 'ftv-holidays.json', 'ftv-movies.json'] as $index => $filename) {
            $playlistId = ($index + 1) * 10;
            $playlists[] = [
                'id' => $playlistId,
                'filename' => $filename,
                'dbtitle' => $index === 0 ? "FreeTV 'Main'" : 'Playlist ' . $index,
                'dbversion' => '1.0',
                'author' => "Author\\Name",
                'email' => null,
                'link' => 'https://example.test/' . $index,
                'host_path' => '/synthetic/private/path',
                'unapproved_secret' => 'fixture-secret-password',
                'lastupdated' => '2026-08-01 12:00:00',
                'is_default' => $index === 0 ? 1 : 0,
                'sort_order' => $index,
            ];
            for ($position = 0; $position < 15; $position++) {
                $shows[] = [
                    'id' => $showId++, 'playlist_id' => $playlistId,
                    'category' => 'category-' . ($position % 5), 'status' => 'active',
                    'identifier' => "show-{$index}-{$position}",
                    'title' => $position === 0 ? "Café 'quoted' \\ title" : "Title {$position}",
                    'description' => $position === 0 ? "Line one\nLine two — Unicode" : null,
                    'start_year' => '1980', 'end_year' => '1981', 'imdb' => 'tt0000001',
                    'group_name' => $position % 2 === 0 ? 'Group' : null, 'sort_order' => $position,
                ];
            }
        }
        return [$playlists, $shows];
    }

    private function unevenRows(?array $counts = null): array
    {
        $counts ??= [21, 9, 30];
        $playlists = [];
        $shows = [];
        $id = 1;
        foreach ($counts as $playlistIndex => $count) {
            $playlistId = ($playlistIndex + 1) * 10;
            $playlists[] = ['id' => $playlistId, 'filename' => "p{$playlistIndex}.json",
                'dbtitle' => "P{$playlistIndex}", 'is_default' => $playlistIndex === 0 ? 1 : 0,
                'sort_order' => $playlistIndex];
            for ($position = 0; $position < $count; $position++) {
                $shows[] = ['id' => $id++, 'playlist_id' => $playlistId,
                    'identifier' => 'p' . ($playlistIndex + 1) . '-' . $position,
                    'title' => 'Show', 'category' => $playlistIndex === 2 && $position >= 25 ? 'B' : 'A',
                    'sort_order' => $position];
            }
        }
        return [$playlists, $shows];
    }

    private function showRows(string $sql): array
    {
        self::assertSame(1, preg_match(
            '/INSERT INTO playlist_shows .*? VALUES\n  (.*?);\n\nCOMMIT;/s',
            $sql,
            $matches
        ));
        return array_map(
            static fn(string $tuple): array => explode(', ', trim($tuple, "()\n ")),
            explode(",\n  ", $matches[1])
        );
    }
}
