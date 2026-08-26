<?php
/**
 * DEPRECATED: legacy-schema importer. Do not use for server-refactor databases.
 * Use import-clean-json-to-mariadb.php for current playlist content instead.
 *
 * Temporary importer for moving freetv-data JSON files into MariaDB.
 *
 * Usage:
 *   php tools/import-json-to-mariadb.php
 *
 * Expects MariaDB connection details in environment variables:
 *   DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASS
 */

$root = dirname(__DIR__);
$dataRoot = dirname($root) . '/freetv-data';

$host = getenv('DB_HOST') ?: '127.0.0.1';
$port = getenv('DB_PORT') ?: '3306';
$dbName = getenv('DB_NAME') ?: 'freetv';
$dbUser = getenv('DB_USER') ?: 'freetv';
$dbPass = getenv('DB_PASS') ?: '';

$dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $host, (int) $port, $dbName);

try {
    $pdo = new PDO($dsn, $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (Throwable $e) {
    fwrite(STDERR, "Connection failed: {$e->getMessage()}\n");
    exit(1);
}

function ensureTableExists(PDO $pdo, string $sql): void {
    $pdo->exec($sql);
}

function normalizeString($value): ?string {
    if ($value === null) {
        return null;
    }
    $value = trim((string) $value);
    return $value === '' ? null : $value;
}

function normalizeDatetime($value): ?string {
    $value = normalizeString($value);
    if ($value === null) {
        return null;
    }

    if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $value)) {
        return $value;
    }

    $candidate = str_replace('Z', '+00:00', $value);
    try {
        $dt = new DateTimeImmutable($candidate, new DateTimeZone('UTC'));
        return $dt->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    } catch (Throwable $e) {
        return null;
    }
}

function normalizeBool($value): int {
    return !empty($value) ? 1 : 0;
}

function loadJson(string $path): array {
    if (!is_file($path)) {
        throw new RuntimeException("Missing file: {$path}");
    }
    $data = json_decode((string) file_get_contents($path), true);
    if (!is_array($data)) {
        throw new RuntimeException("Invalid JSON: {$path}");
    }
    return $data;
}

function upsertSettings(PDO $pdo, array $data): void {
    $stmt = $pdo->prepare('INSERT INTO app_settings (collector, offline, appdata, showads, modules, debugmode, lastupdated) VALUES (:collector, :offline, :appdata, :showads, :modules, :debugmode, :lastupdated) ON DUPLICATE KEY UPDATE collector = VALUES(collector), offline = VALUES(offline), appdata = VALUES(appdata), showads = VALUES(showads), modules = VALUES(modules), debugmode = VALUES(debugmode), lastupdated = VALUES(lastupdated)');
    $stmt->execute([
        ':collector' => normalizeString($data['collector'] ?? null),
        ':offline' => normalizeBool($data['offline'] ?? null),
        ':appdata' => normalizeBool($data['appdata'] ?? null),
        ':showads' => normalizeBool($data['showads'] ?? null),
        ':modules' => normalizeBool($data['modules'] ?? null),
        ':debugmode' => normalizeBool($data['debugmode'] ?? null),
        ':lastupdated' => normalizeDatetime($data['lastupdated'] ?? null),
    ]);
}

function upsertUser(PDO $pdo, array $user): void {
    $stmt = $pdo->prepare('INSERT INTO users (id, username, password_hash, role, status, created_at, last_login_at, updated_at) VALUES (:id, :username, :password_hash, :role, :status, :created_at, :last_login_at, :updated_at) ON DUPLICATE KEY UPDATE username = VALUES(username), password_hash = VALUES(password_hash), role = VALUES(role), status = VALUES(status), created_at = VALUES(created_at), last_login_at = VALUES(last_login_at), updated_at = VALUES(updated_at)');
    $createdAt = normalizeDatetime($user['created'] ?? $user['created_at'] ?? null);
    $lastLogin = normalizeDatetime($user['lastLogin'] ?? $user['last_login_at'] ?? null);
    $stmt->execute([
        ':id' => (int) ($user['id'] ?? 0),
        ':username' => (string) ($user['username'] ?? ''),
        ':password_hash' => (string) ($user['password'] ?? ''),
        ':role' => normalizeString($user['role'] ?? 'admin') ?? 'admin',
        ':status' => normalizeString($user['status'] ?? 'active') ?? 'active',
        ':created_at' => $createdAt,
        ':last_login_at' => $lastLogin,
        ':updated_at' => $createdAt,
    ]);
}

function upsertPlaylist(PDO $pdo, array $meta, string $filename): int {
    $stmt = $pdo->prepare('INSERT INTO playlists (filename, dbtitle, dbversion, author, email, link, lastupdated, is_default, sort_order) VALUES (:filename, :dbtitle, :dbversion, :author, :email, :link, :lastupdated, :is_default, :sort_order) ON DUPLICATE KEY UPDATE dbtitle = VALUES(dbtitle), dbversion = VALUES(dbversion), author = VALUES(author), email = VALUES(email), link = VALUES(link), lastupdated = VALUES(lastupdated), is_default = VALUES(is_default), sort_order = VALUES(sort_order)');
    $stmt->execute([
        ':filename' => $filename,
        ':dbtitle' => normalizeString($meta['dbtitle'] ?? $meta['title'] ?? $filename) ?? $filename,
        ':dbversion' => normalizeString($meta['dbversion'] ?? null),
        ':author' => normalizeString($meta['author'] ?? null),
        ':email' => normalizeString($meta['email'] ?? null),
        ':link' => normalizeString($meta['link'] ?? null),
        ':lastupdated' => normalizeDatetime($meta['lastupdated'] ?? null),
        ':is_default' => 0,
        ':sort_order' => 0,
    ]);
    return (int) $pdo->lastInsertId();
}

function upsertShow(PDO $pdo, int $playlistId, array $show, int $sortOrder): void {
    $stmt = $pdo->prepare('INSERT INTO playlist_shows (playlist_id, category, status, identifier, title, description, start_year, end_year, imdb, sort_order, thumbnail_path) VALUES (:playlist_id, :category, :status, :identifier, :title, :description, :start_year, :end_year, :imdb, :sort_order, :thumbnail_path) ON DUPLICATE KEY UPDATE category = VALUES(category), status = VALUES(status), title = VALUES(title), description = VALUES(description), start_year = VALUES(start_year), end_year = VALUES(end_year), imdb = VALUES(imdb), sort_order = VALUES(sort_order), thumbnail_path = VALUES(thumbnail_path)');
    $stmt->execute([
        ':playlist_id' => $playlistId,
        ':category' => normalizeString($show['category'] ?? null),
        ':status' => normalizeString($show['status'] ?? 'active') ?? 'active',
        ':identifier' => (string) ($show['identifier'] ?? ''),
        ':title' => (string) ($show['title'] ?? ''),
        ':description' => normalizeString($show['desc'] ?? $show['description'] ?? null),
        ':start_year' => normalizeString($show['start'] ?? null),
        ':end_year' => normalizeString($show['end'] ?? null),
        ':imdb' => normalizeString($show['imdb'] ?? null),
        ':sort_order' => $sortOrder,
        ':thumbnail_path' => normalizeString($show['thumbnail'] ?? null),
    ]);
}

$pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
$pdo->exec('TRUNCATE TABLE thumbnail_files');
$pdo->exec('TRUNCATE TABLE problem_report_ips');
$pdo->exec('TRUNCATE TABLE problem_reports');
$pdo->exec('TRUNCATE TABLE playlist_shows');
$pdo->exec('TRUNCATE TABLE playlists');
$pdo->exec('TRUNCATE TABLE users');
$pdo->exec('TRUNCATE TABLE app_settings');
$pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

$settings = loadJson($dataRoot . '/config.json');
upsertSettings($pdo, $settings);

$apdata = loadJson($root . '/public/assets/apdata.key');
foreach ($apdata['users'] ?? [] as $user) {
    upsertUser($pdo, $user);
}

$index = loadJson($dataRoot . '/playlists/index.json');
$playlistFiles = $index['playlists'] ?? [];
$defaultFilename = $index['default'] ?? null;

foreach ($playlistFiles as $indexPos => $meta) {
    $filename = $meta['filename'] ?? null;
    if (!$filename) {
        continue;
    }
    $playlistData = loadJson($dataRoot . '/playlists/' . $filename);
    $playlistId = upsertPlaylist($pdo, $playlistData, $filename);

    $isDefault = ($defaultFilename !== null && $filename === $defaultFilename) ? 1 : 0;
    $pdo->prepare('UPDATE playlists SET is_default = :is_default, sort_order = :sort_order WHERE id = :id')->execute([
        ':is_default' => $isDefault,
        ':sort_order' => $indexPos,
        ':id' => $playlistId,
    ]);

    $shows = $playlistData['shows'] ?? [];
    foreach ($shows as $showIndex => $show) {
        upsertShow($pdo, $playlistId, $show, $showIndex);
    }
}

fwrite(STDOUT, "Imported settings, users, and playlists into {$dbName}.\n");
