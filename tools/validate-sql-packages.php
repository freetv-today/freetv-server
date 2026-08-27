#!/usr/bin/env php
<?php

declare(strict_types=1);

$serverRoot = dirname(__DIR__);
if (is_file($serverRoot . '/vendor/autoload.php')) {
    require_once $serverRoot . '/vendor/autoload.php';
    if (class_exists(\Dotenv\Dotenv::class)) {
        \Dotenv\Dotenv::createImmutable($serverRoot)->safeLoad();
    }
}

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
    if (!preg_match('/^freetv_test_(schema|data|sample|full)_[a-f0-9]{8}$/', $name)) {
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
    $setting = $server->query("SELECT setting_value, scope FROM app_settings WHERE setting_key = 'show_ads'")->fetch();
    if ($setting !== ['setting_value' => 'false', 'scope' => 'viewer']) {
        throw new RuntimeException("{$database}: canonical show_ads seed is invalid");
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
    $fkStatement = $server->prepare('SELECT COUNT(*) FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = :schema');
    $fkStatement->execute([':schema' => $database]);
    if ((int) $fkStatement->fetchColumn() !== 3) {
        throw new RuntimeException("{$database}: expected foreign keys are missing");
    }
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
$databases = [
    'schema' => 'freetv_test_schema_' . $suffix,
    'data' => 'freetv_test_data_' . $suffix,
    'sample' => 'freetv_test_sample_' . $suffix,
    'full' => 'freetv_test_full_' . $suffix,
];

try {
    $dsn = sprintf('mysql:host=%s;port=%d;charset=utf8mb4', $host, (int) $port);
    $server = new PDO($dsn, $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::MYSQL_ATTR_MULTI_STATEMENTS => true,
    ]);
    foreach (array_intersect_key($databases, array_flip(['schema', 'data', 'sample'])) as $database) {
        restoreSafeDatabaseName($database);
        $server->exec("CREATE DATABASE `{$database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    }

    $schemaSql = restoreReadFile($serverRoot . '/sql/freetv_mariadb_schema.sql');
    $dataSql = restoreReadFile($serverRoot . '/sql/freetv_mariadb_data.sql');
    $sampleSql = restoreReadFile($serverRoot . '/sql/freetv_mariadb_sample.sql');
    $fullSql = restoreReadFile($serverRoot . '/sql/freetv_mariadb_full.sql');

    $server->exec("USE `{$databases['schema']}`");
    restoreExecute($server, $schemaSql);
    restoreValidate($server, $databases['schema'], 0, 0);
    fwrite(STDOUT, "schema package restore passed: users empty, initialization_required-compatible\n");

    $server->exec("USE `{$databases['data']}`");
    restoreExecute($server, $schemaSql);
    restoreExecute($server, $dataSql);
    restoreValidate($server, $databases['data'], $expectedPlaylists, $expectedShows);
    fwrite(STDOUT, "schema + data package restore passed: {$expectedPlaylists} playlists, {$expectedShows} shows\n");

    $server->exec("USE `{$databases['sample']}`");
    restoreExecute($server, $sampleSql);
    restoreValidate($server, $databases['sample'], $expectedPlaylists, $expectedSampleShows, true);
    fwrite(STDOUT, "sample package restore passed: {$expectedPlaylists} playlists, {$expectedSampleShows} shows\n");

    $rewrittenFull = preg_replace('/CREATE DATABASE IF NOT EXISTS freetv\b/', 'CREATE DATABASE IF NOT EXISTS `' . $databases['full'] . '`', $fullSql, 1, $createCount);
    $rewrittenFull = preg_replace('/\bUSE freetv;/', 'USE `' . $databases['full'] . '`;', (string) $rewrittenFull, 1, $useCount);
    if ($createCount !== 1 || $useCount !== 1) {
        throw new RuntimeException('Full package database wrapper could not be safely redirected');
    }
    restoreExecute($server, $rewrittenFull);
    restoreValidate($server, $databases['full'], $expectedPlaylists, $expectedShows);
    fwrite(STDOUT, "full package restore passed: database creation redirected to disposable fixture\n");
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
