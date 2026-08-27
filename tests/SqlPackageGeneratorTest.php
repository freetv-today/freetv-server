<?php

declare(strict_types=1);

require_once __DIR__ . '/../tools/lib/SqlPackageGenerator.php';

use FreeTV\Tools\SqlPackageGenerator;

function sqlPackageAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function sqlPackageRows(): array
{
    $playlists = [];
    $shows = [];
    $showId = 10;
    foreach (['freetv.json', 'ftv-british.json', 'ftv-holidays.json', 'ftv-movies.json'] as $playlistIndex => $filename) {
        $playlistId = $playlistIndex + 1;
        $playlists[] = [
            'id' => $playlistId,
            'filename' => $filename,
            'dbtitle' => $playlistIndex === 0 ? "FreeTV 'Main'" : 'Playlist ' . $playlistIndex,
            'dbversion' => '1.0',
            'author' => "Author\\Name",
            'email' => null,
            'link' => 'https://example.test/' . $playlistIndex,
            'lastupdated' => '2026-08-01 12:00:00',
            'is_default' => $playlistIndex === 0 ? 1 : 0,
            'sort_order' => $playlistIndex,
        ];
        for ($position = 0; $position < 15; $position++) {
            $shows[] = [
                'id' => $showId++,
                'playlist_id' => $playlistId,
                'category' => 'category-' . ($position % 5),
                'status' => 'active',
                'identifier' => "show-{$playlistIndex}-{$position}",
                'title' => $position === 0 ? "Café 'quoted' \\ title" : "Title {$position}",
                'description' => $position === 0 ? "Line one\nLine two — Unicode" : null,
                'start_year' => '1980',
                'end_year' => '1981',
                'imdb' => 'tt0000001',
                'group_name' => $position % 2 === 0 ? 'Group' : null,
                'sort_order' => $position,
            ];
        }
    }
    return [$playlists, $shows];
}

$schema = (string) file_get_contents(__DIR__ . '/../sql/freetv_mariadb_schema.sql');
$generator = new SqlPackageGenerator($schema);
[$playlists, $shows] = sqlPackageRows();
$first = $generator->generate($playlists, $shows);
$second = $generator->generate($playlists, $shows);

sqlPackageAssert($first === $second, 'Generation is not deterministic');
sqlPackageAssert($first['show_count'] === 60, 'Full show count is incorrect');
sqlPackageAssert($first['sample_count'] === 50, 'Sample count is not 50');
sqlPackageAssert(SqlPackageGenerator::literal("quote' slash\\ café\n") === 'CONVERT(0x' . bin2hex("quote' slash\\ café\n") . ' USING utf8mb4)', 'SQL string escaping is not byte-safe');
sqlPackageAssert(SqlPackageGenerator::literal(null) === 'NULL', 'NULL literal is incorrect');

$packages = $first['packages'];
sqlPackageAssert(!preg_match('/^\s*(CREATE\s+DATABASE|USE\s+)/mi', $packages['schema']), 'Schema package selects a database');
sqlPackageAssert(!str_contains($packages['schema'], 'INSERT INTO playlists'), 'Schema package contains playlist data');
sqlPackageAssert(str_contains($packages['schema'], "VALUES ('show_ads', 'false', 'viewer')"), 'Schema package lacks canonical setting seed');
sqlPackageAssert(!preg_match('/CREATE\s+TABLE/i', $packages['data']), 'Data package contains schema DDL');
sqlPackageAssert(str_contains($packages['data'], 'INSERT INTO playlists') && str_contains($packages['data'], 'INSERT INTO playlist_shows'), 'Data package lacks content inserts');
sqlPackageAssert(str_contains($packages['data'], '`sort_order`) VALUES'), 'Generated INSERT column list is malformed');
sqlPackageAssert(str_contains($packages['sample'], 'CREATE TABLE IF NOT EXISTS users') && str_contains($packages['sample'], 'INSERT INTO playlist_shows'), 'Sample package is not self-contained');
sqlPackageAssert(str_contains($packages['full'], 'CREATE DATABASE IF NOT EXISTS freetv') && str_contains($packages['full'], 'USE freetv;'), 'Full package lacks database bootstrap');

foreach ($packages as $name => $sql) {
    sqlPackageAssert(!preg_match('/INSERT\s+(?:IGNORE\s+)?INTO\s+users\b/i', $sql), "{$name} exports users");
    sqlPackageAssert(!preg_match('/INSERT\s+(?:IGNORE\s+)?INTO\s+problem_reports?\b/i', $sql), "{$name} exports problem reports");
    sqlPackageAssert(!str_contains($sql, '/home/'), "{$name} contains a host path");
    sqlPackageAssert(!preg_match('/generated (?:at|on)/i', $sql), "{$name} contains volatile generation metadata");
}

$tempRoot = sys_get_temp_dir() . '/freetv-sql-package-test-' . bin2hex(random_bytes(5));
$sqlDirectory = $tempRoot . '/sql';
mkdir($sqlDirectory, 0755, true);
try {
    $generator->write($sqlDirectory, $packages);
    $hashes = [];
    foreach (SqlPackageGenerator::FILES as $key => $filename) {
        $path = $sqlDirectory . '/' . $filename;
        sqlPackageAssert(is_file($path), "{$filename} was not written");
        $hashes[$key] = hash_file('sha256', $path);
    }
    $generator->write($sqlDirectory, $second['packages']);
    foreach (SqlPackageGenerator::FILES as $key => $filename) {
        sqlPackageAssert(hash_file('sha256', $sqlDirectory . '/' . $filename) === $hashes[$key], "{$filename} changed across identical generation");
    }
} finally {
    foreach (glob($sqlDirectory . '/*') ?: [] as $path) {
        unlink($path);
    }
    rmdir($sqlDirectory);
    rmdir($tempRoot);
}

fwrite(STDOUT, "SqlPackageGeneratorTest passed\n");
