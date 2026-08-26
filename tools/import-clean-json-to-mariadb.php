#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once __DIR__ . '/lib/ProductionJsonCleaner.php';
require_once __DIR__ . '/lib/CleanJsonImporter.php';

$serverRoot = dirname(__DIR__);
if (is_file($serverRoot . '/vendor/autoload.php')) {
    require_once $serverRoot . '/vendor/autoload.php';
    if (class_exists(\Dotenv\Dotenv::class)) {
        \Dotenv\Dotenv::createImmutable($serverRoot)->safeLoad();
    }
}

use FreeTV\Tools\CleanJsonImporter;
use FreeTV\Tools\MariaDbContentDatabase;

function importerEnvironment(string $name, string $default): string
{
    $value = getenv($name);
    if ($value === false && array_key_exists($name, $_ENV)) {
        $value = (string) $_ENV[$name];
    }
    return $value === false || ($name !== 'DB_PASS' && trim((string) $value) === '')
        ? $default
        : (string) $value;
}

function importerUsage(): never
{
    fwrite(STDERR, "Usage: php tools/import-clean-json-to-mariadb.php CLEANED_DIR --default=FILE [--order=FILE,...] [--replace]\n");
    exit(2);
}

$directory = $default = null;
$order = null;
$replace = false;
foreach (array_slice($argv, 1) as $argument) {
    if ($argument === '--replace') {
        $replace = true;
    } elseif (str_starts_with($argument, '--default=')) {
        $default = substr($argument, strlen('--default='));
    } elseif (str_starts_with($argument, '--order=')) {
        $order = explode(',', substr($argument, strlen('--order=')));
    } elseif (!str_starts_with($argument, '--') && $directory === null) {
        $directory = $argument;
    } else {
        importerUsage();
    }
}
if ($directory === null || $default === null || $default === '') {
    importerUsage();
}

$host = importerEnvironment('DB_HOST', '127.0.0.1');
$port = importerEnvironment('DB_PORT', '3306');
$databaseName = importerEnvironment('DB_NAME', 'freetv');
$user = importerEnvironment('DB_USER', 'freetv');
$password = importerEnvironment('DB_PASS', '');

try {
    $importer = new CleanJsonImporter();
    $data = $importer->validate($directory, $default, $order);
    printf("Target database: %s:%s/%s\n", $host, $port, $databaseName);
    printf("Validated content: %d playlists, %d shows\n", count($data['playlists']), $data['show_count']);
    printf("Default playlist: %s\n", $data['default']);
    printf("Playlist order: %s\n", implode(', ', $data['order']));
    printf("Tables to clear: %s\n", implode(', ', CleanJsonImporter::RESET_TABLES));
    printf("Tables preserved: %s\n", implode(', ', CleanJsonImporter::PRESERVED_TABLES));
    fwrite(STDOUT, "All other tables are outside the importer's write allowlist.\n");

    if (!$replace) {
        fwrite(STDOUT, "Dry run only; MariaDB was not connected to or modified. Use --replace for replacement.\n");
        exit(0);
    }

    $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $host, (int) $port, $databaseName);
    $pdo = new PDO($dsn, $user, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $contentDatabase = new MariaDbContentDatabase($pdo, $databaseName);
    $schema = $contentDatabase->validateSchema();
    if ($schema['legacy_shows']) {
        fwrite(STDOUT, "NOTICE: non-canonical table `shows` exists; it is outside the write allowlist and will not be touched.\n");
    }
    $importer->replace($contentDatabase, $data);
    fwrite(STDOUT, "Replacement committed and post-import validation passed.\n");
} catch (Throwable $exception) {
    fwrite(STDERR, "ERROR: {$exception->getMessage()}\n");
    exit(1);
}
