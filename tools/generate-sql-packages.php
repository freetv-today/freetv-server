#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once __DIR__ . '/lib/SqlPackageGenerator.php';

$serverRoot = dirname(__DIR__);
if (is_file($serverRoot . '/vendor/autoload.php')) {
    require_once $serverRoot . '/vendor/autoload.php';
    if (class_exists(\Dotenv\Dotenv::class)) {
        \Dotenv\Dotenv::createImmutable($serverRoot)->safeLoad();
    }
}

use FreeTV\Tools\SqlPackageGenerator;
use FreeTV\Tools\SqlPackageSource;

function packageEnvironment(string $name, string $default): string
{
    $value = getenv($name);
    if ($value === false && array_key_exists($name, $_ENV)) {
        $value = (string) $_ENV[$name];
    }
    return $value === false || ($name !== 'DB_PASS' && trim((string) $value) === '') ? $default : (string) $value;
}

function packageUsage(): never
{
    fwrite(STDERR, "Usage: php tools/generate-sql-packages.php --expect-playlists=N --expect-shows=N\n");
    exit(2);
}

$expectedPlaylists = $expectedShows = null;
foreach (array_slice($argv, 1) as $argument) {
    if (preg_match('/^--expect-playlists=(\d+)$/', $argument, $matches)) {
        $expectedPlaylists = (int) $matches[1];
    } elseif (preg_match('/^--expect-shows=(\d+)$/', $argument, $matches)) {
        $expectedShows = (int) $matches[1];
    } else {
        packageUsage();
    }
}
if ($expectedPlaylists === null || $expectedShows === null || $expectedPlaylists < 1 || $expectedShows < 1) {
    packageUsage();
}

$host = packageEnvironment('DB_HOST', '127.0.0.1');
$port = packageEnvironment('DB_PORT', '3306');
$databaseName = packageEnvironment('DB_NAME', 'freetv');
$user = packageEnvironment('DB_USER', 'freetv');
$password = packageEnvironment('DB_PASS', '');

try {
    $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $host, (int) $port, $databaseName);
    $pdo = new PDO($dsn, $user, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $source = (new SqlPackageSource($pdo, $databaseName))->read();
    printf("Source database: %s:%s/%s\n", $host, $port, $databaseName);
    printf("Source counts: %d playlists, %d shows, %d users, %d app settings\n",
        $source['counts']['playlists'],
        $source['counts']['playlist_shows'],
        $source['counts']['users'],
        $source['counts']['app_settings']
    );
    if ($source['counts']['playlists'] !== $expectedPlaylists || $source['counts']['playlist_shows'] !== $expectedShows) {
        throw new RuntimeException('Source counts do not match the explicitly expected checkpoint');
    }

    $schemaPath = $serverRoot . '/sql/freetv_mariadb_schema-tables-only.sql';
    $schema = file_get_contents($schemaPath);
    if ($schema === false) {
        throw new RuntimeException('Could not read canonical schema package');
    }
    $generator = new SqlPackageGenerator($schema);
    $result = $generator->generate($source['playlists'], $source['shows']);
    $generator->write($serverRoot . '/sql', $result['packages']);

    printf("Generated %d packages with %d full shows and %d sample shows.\n",
        count($result['packages']),
        $result['show_count'],
        $result['sample_count']
    );
    foreach (SqlPackageGenerator::FILES as $filename) {
        printf("  sql/%s: %d bytes\n", $filename, filesize($serverRoot . '/sql/' . $filename));
    }
    fwrite(STDOUT, "Database access was read-only; users and local app_settings values were not serialized.\n");
} catch (Throwable $exception) {
    fwrite(STDERR, "ERROR: {$exception->getMessage()}\n");
    exit(1);
}
