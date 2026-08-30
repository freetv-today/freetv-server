#!/usr/bin/env php
<?php

declare(strict_types=1);

$serverRoot = dirname(__DIR__);
require_once __DIR__ . '/lib/SqlPackageGenerator.php';
if (is_file($serverRoot . '/vendor/autoload.php')) {
    require_once $serverRoot . '/vendor/autoload.php';
    if (class_exists(\Dotenv\Dotenv::class)) {
        \Dotenv\Dotenv::createImmutable($serverRoot)->safeLoad();
    }
}

use FreeTV\Tools\SqlPackageGenerator;

function restoreEnvironment(string $name, string $default): string
{
    $value = getenv($name);
    if ($value === false && array_key_exists($name, $_ENV)) {
        $value = (string) $_ENV[$name];
    }
    return $value === false || ($name !== 'DB_PASS' && trim((string) $value) === '') ? $default : (string) $value;
}

function restoreUsage(): never
{
    fwrite(STDERR, "Usage: php tools/validate-sql-packages.php --run --expect-playlists=N --expect-shows=N --expect-sample-shows=N\n");
    exit(2);
}

function restoreSafeDatabaseName(string $name): void
{
    if (!preg_match('/^freetv_test_(schema|full|sample)_(create|tables)_[a-f0-9]{8}$/', $name)) {
        throw new RuntimeException("Refusing unsafe disposable database name: {$name}");
    }
}

function restoreReadFile(string $path): string
{
    $contents = file_get_contents($path);
    if ($contents === false) {
        throw new RuntimeException("Could not read SQL package: {$path}");
    }
    return $contents;
}

function restoreExecute(PDO $connection, string $sql): void
{
    foreach (preg_split('/;\s*(?:\n|$)/', $sql) ?: [] as $statement) {
        $statement = trim($statement);
        if ($statement !== '' && !str_starts_with($statement, '--')) {
            $connection->exec($statement);
        } elseif ($statement !== '') {
            $sqlStart = strpos($statement, "\n");
            if ($sqlStart !== false) {
                restoreExecute($connection, substr($statement, $sqlStart + 1));
            }
        }
    }
}

function restoreTablesOnlyBody(string $sql, string $label): string
{
    if (preg_match('/^\s*(CREATE\s+DATABASE|USE\s+)/mi', $sql)) {
        throw new RuntimeException("{$label}: tables-only package creates or selects a database");
    }
    return $sql;
}

function restoreCreateDbBody(string $sql, string $label): string
{
    if (!str_starts_with($sql, SqlPackageGenerator::DATABASE_WRAPPER)) {
        throw new RuntimeException("{$label}: create-db package lacks the canonical database wrapper");
    }
    return substr($sql, strlen(SqlPackageGenerator::DATABASE_WRAPPER));
}

function restoreRedirectCreateDb(string $sql, string $database, string $label): string
{
    restoreSafeDatabaseName($database);
    $body = restoreCreateDbBody($sql, $label);
    return "CREATE DATABASE IF NOT EXISTS `{$database}`\n"
        . "  CHARACTER SET utf8mb4\n"
        . "  COLLATE utf8mb4_unicode_ci;\n\n"
        . "USE `{$database}`;\n\n"
        . $body;
}

function restoreValidate(PDO $server, string $database, int $playlists, int $shows, bool $representativeSample = false): void
{
    restoreSafeDatabaseName($database);
    $server->exec("USE `{$database}`");
    $requiredTables = ['app_settings', 'users', 'playlists', 'playlist_shows', 'problem_reports', 'problem_report_ips'];
    $statement = $server->prepare('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = :schema');
    $statement->execute([':schema' => $database]);
    $actualTables = $statement->fetchAll(PDO::FETCH_COLUMN);
    foreach ($requiredTables as $table) {
        if (!in_array($table, $actualTables, true)) {
            throw new RuntimeException("{$database}: missing table {$table}");
        }
    }
    $count = static fn(string $table): int => (int) $server->query("SELECT COUNT(*) FROM `{$table}`")->fetchColumn();
    $actual = [
        'playlists' => $count('playlists'),
        'playlist_shows' => $count('playlist_shows'),
        'users' => $count('users'),
        'problem_reports' => $count('problem_reports'),
        'problem_report_ips' => $count('problem_report_ips'),
    ];
    if ($actual !== [
        'playlists' => $playlists,
        'playlist_shows' => $shows,
        'users' => 0,
        'problem_reports' => 0,
        'problem_report_ips' => 0,
    ]) {
        throw new RuntimeException($database . ': unexpected restored counts ' . json_encode($actual));
    }
    $settings = $server->query('SELECT setting_key, setting_value, scope FROM app_settings ORDER BY setting_key')->fetchAll();
    if ($settings !== [['setting_key' => 'show_ads', 'setting_value' => 'false', 'scope' => 'viewer']]) {
        throw new RuntimeException("{$database}: canonical app_settings defaults are invalid");
    }
    if ($playlists > 0) {
        $default = $server->query('SELECT filename FROM playlists WHERE is_default = 1')->fetchAll(PDO::FETCH_COLUMN);
        if ($default !== ['freetv.json']) {
            throw new RuntimeException("{$database}: default playlist is invalid");
        }
        $duplicates = (int) $server->query('SELECT COUNT(*) FROM (SELECT playlist_id, identifier FROM playlist_shows GROUP BY playlist_id, identifier HAVING COUNT(*) > 1) duplicate_rows')->fetchColumn();
        if ($duplicates !== 0) {
            throw new RuntimeException("{$database}: duplicate show identifiers found");
        }
        $duplicatePlaylists = (int) $server->query('SELECT COUNT(*) FROM (SELECT filename FROM playlists GROUP BY filename HAVING COUNT(*) > 1) duplicate_rows')->fetchColumn();
        if ($duplicatePlaylists !== 0) {
            throw new RuntimeException("{$database}: duplicate playlist identifiers found");
        }
        $playlistOrder = $server->query('SELECT filename FROM playlists ORDER BY sort_order, id')->fetchAll(PDO::FETCH_COLUMN);
        if ($playlistOrder !== ['freetv.json', 'ftv-british.json', 'ftv-holidays.json', 'ftv-movies.json']) {
            throw new RuntimeException("{$database}: playlist order is invalid");
        }
        $invalidShowOrder = (int) $server->query('SELECT COUNT(*) FROM playlist_shows s WHERE s.sort_order <> (SELECT COUNT(*) FROM playlist_shows earlier WHERE earlier.playlist_id = s.playlist_id AND (earlier.sort_order < s.sort_order OR (earlier.sort_order = s.sort_order AND earlier.id < s.id)))')->fetchColumn();
        if ($invalidShowOrder !== 0) {
            throw new RuntimeException("{$database}: show ordering is invalid");
        }
        if ($representativeSample) {
            $underrepresented = (int) $server->query('SELECT COUNT(*) FROM (SELECT playlist_id FROM playlist_shows GROUP BY playlist_id HAVING COUNT(DISTINCT category) < 4) sample_playlists')->fetchColumn();
            if ($underrepresented !== 0) {
                throw new RuntimeException("{$database}: sample category diversity is insufficient");
            }
        }
    }
    $fkStatement = $server->prepare('SELECT CONSTRAINT_NAME, TABLE_NAME, REFERENCED_TABLE_NAME FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = :schema ORDER BY CONSTRAINT_NAME');
    $fkStatement->execute([':schema' => $database]);
    if ($fkStatement->fetchAll() !== [
        ['CONSTRAINT_NAME' => 'fk_playlist_shows_playlist', 'TABLE_NAME' => 'playlist_shows', 'REFERENCED_TABLE_NAME' => 'playlists'],
        ['CONSTRAINT_NAME' => 'fk_problem_reports_playlist', 'TABLE_NAME' => 'problem_reports', 'REFERENCED_TABLE_NAME' => 'playlists'],
        ['CONSTRAINT_NAME' => 'fk_problem_reports_show', 'TABLE_NAME' => 'problem_reports', 'REFERENCED_TABLE_NAME' => 'playlist_shows'],
    ]) {
        throw new RuntimeException("{$database}: expected foreign keys are missing");
    }
}

function restoreLogicalFingerprint(PDO $server, string $database): string
{
    restoreSafeDatabaseName($database);
    $server->exec("USE `{$database}`");
    $tables = ['app_settings', 'playlist_shows', 'playlists', 'problem_report_ips', 'problem_reports', 'users'];
    $schema = [];
    foreach ($tables as $table) {
        $create = $server->query("SHOW CREATE TABLE `{$table}`")->fetch(PDO::FETCH_NUM);
        if (!is_array($create) || !isset($create[1])) {
            throw new RuntimeException("{$database}: could not fingerprint table {$table}");
        }
        $schema[$table] = $create[1];
    }
    $content = [
        'app_settings' => $server->query('SELECT setting_key, setting_value, scope FROM app_settings ORDER BY setting_key')->fetchAll(),
        'playlists' => $server->query('SELECT id, filename, dbtitle, dbversion, author, email, link, lastupdated, is_default, sort_order FROM playlists ORDER BY sort_order, id')->fetchAll(),
        'playlist_shows' => $server->query('SELECT id, playlist_id, category, status, identifier, title, description, start_year, end_year, imdb, group_name, sort_order FROM playlist_shows ORDER BY playlist_id, sort_order, id')->fetchAll(),
        'users' => $server->query('SELECT * FROM users ORDER BY id')->fetchAll(),
        'problem_reports' => $server->query('SELECT * FROM problem_reports ORDER BY id')->fetchAll(),
        'problem_report_ips' => $server->query('SELECT * FROM problem_report_ips ORDER BY id')->fetchAll(),
    ];
    return hash('sha256', json_encode(['schema' => $schema, 'content' => $content], JSON_THROW_ON_ERROR));
}

$run = false;
$expectedPlaylists = $expectedShows = $expectedSampleShows = null;
foreach (array_slice($argv, 1) as $argument) {
    if ($argument === '--run') {
        $run = true;
    } elseif (preg_match('/^--expect-playlists=(\d+)$/', $argument, $matches)) {
        $expectedPlaylists = (int) $matches[1];
    } elseif (preg_match('/^--expect-shows=(\d+)$/', $argument, $matches)) {
        $expectedShows = (int) $matches[1];
    } elseif (preg_match('/^--expect-sample-shows=(\d+)$/', $argument, $matches)) {
        $expectedSampleShows = (int) $matches[1];
    } else {
        restoreUsage();
    }
}
if (!$run || $expectedPlaylists === null || $expectedShows === null || $expectedSampleShows === null) {
    restoreUsage();
}

$host = restoreEnvironment('DB_HOST', '127.0.0.1');
$port = restoreEnvironment('DB_PORT', '3306');
$user = restoreEnvironment('DB_USER', 'freetv');
$password = restoreEnvironment('DB_PASS', '');
$suffix = bin2hex(random_bytes(4));
$contracts = [
    'schema' => [
        'create_file' => SqlPackageGenerator::FILES['schema_create_db'],
        'tables_file' => SqlPackageGenerator::FILES['schema_tables_only'],
        'playlists' => 0,
        'shows' => 0,
        'sample' => false,
    ],
    'full' => [
        'create_file' => SqlPackageGenerator::FILES['full_create_db'],
        'tables_file' => SqlPackageGenerator::FILES['full_tables_only'],
        'playlists' => $expectedPlaylists,
        'shows' => $expectedShows,
        'sample' => false,
    ],
    'sample' => [
        'create_file' => SqlPackageGenerator::FILES['sample_create_db'],
        'tables_file' => SqlPackageGenerator::FILES['sample_tables_only'],
        'playlists' => $expectedPlaylists,
        'shows' => $expectedSampleShows,
        'sample' => true,
    ],
];
$databases = [];
foreach (array_keys($contracts) as $kind) {
    $databases["{$kind}_create"] = "freetv_test_{$kind}_create_{$suffix}";
    $databases["{$kind}_tables"] = "freetv_test_{$kind}_tables_{$suffix}";
}

try {
    $dsn = sprintf('mysql:host=%s;port=%d;charset=utf8mb4', $host, (int) $port);
    $server = new PDO($dsn, $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::MYSQL_ATTR_MULTI_STATEMENTS => true,
    ]);
    foreach ($contracts as $kind => $contract) {
        $createLabel = $contract['create_file'];
        $tablesLabel = $contract['tables_file'];
        $createSql = restoreReadFile($serverRoot . '/sql/' . $createLabel);
        $tablesSql = restoreReadFile($serverRoot . '/sql/' . $tablesLabel);
        $createBody = restoreCreateDbBody($createSql, $createLabel);
        restoreTablesOnlyBody($tablesSql, $tablesLabel);
        if ($createBody !== $tablesSql) {
            throw new RuntimeException("{$kind}: create-db and tables-only package bodies differ");
        }

        $tablesDatabase = $databases["{$kind}_tables"];
        restoreSafeDatabaseName($tablesDatabase);
        $server->exec("CREATE DATABASE `{$tablesDatabase}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $server->exec("USE `{$tablesDatabase}`");
        restoreExecute($server, $tablesSql);
        restoreValidate($server, $tablesDatabase, $contract['playlists'], $contract['shows'], $contract['sample']);
        fwrite(STDOUT, "{$tablesLabel} restore passed: externally created selected database\n");

        $createDatabase = $databases["{$kind}_create"];
        restoreExecute($server, restoreRedirectCreateDb($createSql, $createDatabase, $createLabel));
        restoreValidate($server, $createDatabase, $contract['playlists'], $contract['shows'], $contract['sample']);
        fwrite(STDOUT, "{$createLabel} restore passed: create/select wrapper redirected to disposable database\n");

        if (restoreLogicalFingerprint($server, $createDatabase) !== restoreLogicalFingerprint($server, $tablesDatabase)) {
            throw new RuntimeException("{$kind}: create-db and tables-only restores are not logically equivalent");
        }
        fwrite(STDOUT, "{$kind} package pair equivalence passed\n");
    }
} catch (Throwable $exception) {
    fwrite(STDERR, "ERROR: {$exception->getMessage()}\n");
    $exitCode = 1;
} finally {
    if (isset($server)) {
        foreach ($databases as $database) {
            try {
                restoreSafeDatabaseName($database);
                $server->exec("DROP DATABASE IF EXISTS `{$database}`");
            } catch (Throwable $cleanupException) {
                fwrite(STDERR, "WARNING: could not remove {$database}: {$cleanupException->getMessage()}\n");
                $exitCode = 1;
            }
        }
    }
}

exit($exitCode ?? 0);
