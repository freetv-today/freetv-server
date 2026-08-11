<?php

/**
 * Recover optional playlist show groups from public playlist JSON.
 *
 * Dry-run (default): php tools/import-groups-from-json.php
 * Apply updates:     php tools/import-groups-from-json.php --apply
 */

$arguments = $argv;
array_shift($arguments);
if ($arguments !== [] && $arguments !== ['--apply']) {
    fwrite(STDERR, "Usage: php tools/import-groups-from-json.php [--apply]\n");
    exit(2);
}

$apply = $arguments === ['--apply'];
$root = dirname(__DIR__);
$playlistDirectory = $root . '/public/playlists';

$autoloadPath = $root . '/vendor/autoload.php';
if (is_file($autoloadPath)) {
    require_once $autoloadPath;
}
if (class_exists('Dotenv\\Dotenv') && is_file($root . '/.env')) {
    Dotenv\Dotenv::createImmutable($root)->safeLoad();
}

require_once $root . '/public/api/admin/ShowGroup.php';

use FreeTV\Admin\ShowGroup;

function environmentValue(string $key, string $default = ''): string
{
    $value = getenv($key);
    if ($value !== false && $value !== '') {
        return $value;
    }

    $environmentValue = $_ENV[$key] ?? '';
    return is_string($environmentValue) && $environmentValue !== ''
        ? $environmentValue
        : $default;
}

function loadJsonFile(string $path): array
{
    if (!is_file($path)) {
        throw new RuntimeException("Missing file: {$path}");
    }

    $contents = file_get_contents($path);
    if ($contents === false) {
        throw new RuntimeException("Could not read file: {$path}");
    }

    return json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
}

function warning(string $message): void
{
    fwrite(STDERR, "Warning: {$message}\n");
}

$host = environmentValue('DB_HOST', environmentValue('VITE_DB_HOST', '127.0.0.1'));
$port = environmentValue('DB_PORT', environmentValue('VITE_DB_PORT', '3306'));
$database = environmentValue('DB_NAME', environmentValue('VITE_DB_NAME', 'freetv'));
$username = environmentValue('DB_USER', environmentValue('VITE_DB_USER', 'freetv'));
$password = environmentValue('DB_PASS', environmentValue('VITE_DB_PASS'));
$dsn = sprintf(
    'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
    $host,
    (int) $port,
    $database
);

try {
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (Throwable $e) {
    fwrite(STDERR, "Connection failed: {$e->getMessage()}\n");
    exit(1);
}

$counts = [
    'playlist_files_scanned' => 0,
    'grouped_json_rows_found' => 0,
    'changes' => 0,
    'already_correct' => 0,
    'missing_playlist' => 0,
    'missing_show' => 0,
    'invalid_group_values' => 0,
    'errors' => 0,
];

try {
    $index = loadJsonFile($playlistDirectory . '/index.json');
} catch (Throwable $e) {
    fwrite(STDERR, "Could not load playlist index: {$e->getMessage()}\n");
    exit(1);
}

$playlistEntries = $index['playlists'] ?? null;
if (!is_array($playlistEntries)) {
    fwrite(STDERR, "Playlist index does not contain a playlists array.\n");
    exit(1);
}

$findPlaylist = $pdo->prepare(
    'SELECT id FROM playlists WHERE filename = :filename LIMIT 1'
);
$findShow = $pdo->prepare(
    'SELECT id, group_name FROM playlist_shows '
    . 'WHERE playlist_id = :playlist_id AND identifier = :identifier LIMIT 1'
);
$updateGroup = $apply
    ? $pdo->prepare('UPDATE playlist_shows SET group_name = :group_name WHERE id = :id')
    : null;

foreach ($playlistEntries as $entry) {
    $filename = is_array($entry) ? ($entry['filename'] ?? null) : $entry;
    if (
        !is_string($filename)
        || $filename === ''
        || basename($filename) !== $filename
        || pathinfo($filename, PATHINFO_EXTENSION) !== 'json'
    ) {
        warning('Playlist index contains an invalid filename entry');
        $counts['errors']++;
        continue;
    }

    $counts['playlist_files_scanned']++;

    try {
        $playlistJson = loadJsonFile($playlistDirectory . '/' . $filename);
    } catch (Throwable $e) {
        warning("{$filename}: {$e->getMessage()}");
        $counts['errors']++;
        continue;
    }

    $shows = $playlistJson['shows'] ?? null;
    if (!is_array($shows)) {
        warning("{$filename}: shows is not an array");
        $counts['errors']++;
        continue;
    }

    $groupedShows = [];
    foreach ($shows as $show) {
        if (!is_array($show) || !array_key_exists('group', $show)) {
            continue;
        }

        try {
            $groupName = ShowGroup::normalize($show['group']);
        } catch (InvalidArgumentException $e) {
            $counts['invalid_group_values']++;
            continue;
        }

        if ($groupName === null) {
            continue;
        }

        $counts['grouped_json_rows_found']++;
        $identifier = $show['identifier'] ?? null;
        if (!is_string($identifier) || trim($identifier) === '') {
            warning("{$filename}: grouped show has no valid identifier");
            $counts['errors']++;
            continue;
        }

        $groupedShows[] = [
            'identifier' => $identifier,
            'group_name' => $groupName,
        ];
    }

    try {
        $findPlaylist->execute([':filename' => $filename]);
        $playlistRow = $findPlaylist->fetch();
    } catch (Throwable $e) {
        warning("{$filename}: playlist lookup failed: {$e->getMessage()}");
        $counts['errors']++;
        continue;
    }

    if (!$playlistRow) {
        warning("{$filename}: playlist is missing from MariaDB");
        $counts['missing_playlist']++;
        continue;
    }

    foreach ($groupedShows as $groupedShow) {
        try {
            $findShow->execute([
                ':playlist_id' => $playlistRow['id'],
                ':identifier' => $groupedShow['identifier'],
            ]);
            $showRow = $findShow->fetch();
        } catch (Throwable $e) {
            warning(
                "{$filename} / {$groupedShow['identifier']}: "
                . "show lookup failed: {$e->getMessage()}"
            );
            $counts['errors']++;
            continue;
        }

        if (!$showRow) {
            warning("{$filename} / {$groupedShow['identifier']}: show is missing from MariaDB");
            $counts['missing_show']++;
            continue;
        }

        if ($showRow['group_name'] === $groupedShow['group_name']) {
            $counts['already_correct']++;
            continue;
        }

        if (!$apply) {
            $counts['changes']++;
            continue;
        }

        try {
            if ($updateGroup === null) {
                throw new LogicException('Apply update statement is unavailable');
            }
            $updateGroup->execute([
                ':group_name' => $groupedShow['group_name'],
                ':id' => $showRow['id'],
            ]);
            $counts['changes']++;
        } catch (Throwable $e) {
            warning(
                "{$filename} / {$groupedShow['identifier']}: "
                . "update failed: {$e->getMessage()}"
            );
            $counts['errors']++;
        }
    }
}

$changeLabel = $apply ? 'Updated' : 'Would update';

fwrite(STDOUT, 'Mode: ' . ($apply ? 'APPLY' : 'DRY RUN') . "\n");
fwrite(STDOUT, "Playlist files scanned: {$counts['playlist_files_scanned']}\n");
fwrite(STDOUT, "Grouped JSON rows found: {$counts['grouped_json_rows_found']}\n");
fwrite(STDOUT, "{$changeLabel}: {$counts['changes']}\n");
fwrite(STDOUT, "Already correct: {$counts['already_correct']}\n");
fwrite(STDOUT, "Missing playlist: {$counts['missing_playlist']}\n");
fwrite(STDOUT, "Missing show: {$counts['missing_show']}\n");
fwrite(STDOUT, "Invalid group values: {$counts['invalid_group_values']}\n");
fwrite(STDOUT, "Errors: {$counts['errors']}\n");

if (!$apply) {
    fwrite(STDOUT, "No database changes were made (dry-run).\n");
}
