<?php

declare(strict_types=1);

use FreeTV\Tests\Regression\TestDatabaseSupport as Support;

require_once __DIR__ . '/TestDatabaseSupport.php';

/** @return array<string, mixed> */
function bridgePayload(): array
{
    $input = stream_get_contents(STDIN);
    if ($input === false || trim($input) === '') {
        return [];
    }
    $payload = json_decode($input, true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($payload)) {
        throw new RuntimeException('Database test support payload must be a JSON object');
    }
    return $payload;
}

/** @return array{settings: array, state: array, connection: mixed} */
function readyTestDatabase(): array
{
    if (!Support::stateExists()) {
        throw new RuntimeException(
            'No test database is installed; run npm run test:db:install before npm run test:api:db'
        );
    }

    $settings = Support::configuration();
    $state = Support::loadState();
    Support::assertStateMatchesConfiguration($state, $settings);
    if ($state['status'] !== Support::STATE_READY) {
        throw new RuntimeException(
            'Tracked test database is not ready: ' . $state['test_database']
                . ' (' . $state['status'] . ')'
        );
    }

    $source = Support::connection($settings, $settings['database'], 'api_test_source');
    $source->getPdo();
    Support::assertSelectedDatabase($source, $settings['database']);
    if (!Support::databaseExists($source, $state['test_database'])) {
        throw new RuntimeException(
            'Tracked test database does not exist or is not accessible: ' . $state['test_database']
        );
    }

    $connection = Support::connection($settings, $state['test_database'], 'api_test_target');
    $connection->getPdo();
    Support::assertSelectedDatabase($connection, $state['test_database']);
    Support::verifyRequiredTables($connection, $state['test_database']);

    return ['settings' => $settings, 'state' => $state, 'connection' => $connection];
}

function serverTimestamp($connection): string
{
    $row = $connection->selectOne(
        "SELECT DATE_FORMAT(CURRENT_TIMESTAMP, '%Y-%m-%d %H:%i:%s') AS server_time"
    );
    return (string) ($row->server_time ?? '');
}

/** @return array<string, mixed> */
function fixtureContext(array $context): array
{
    $connection = $context['connection'];
    $playlistRows = $connection->table('playlists')
        ->select([
            'id', 'filename', 'dbtitle', 'dbversion', 'lastupdated',
            'author', 'email', 'link', 'is_default', 'sort_order',
        ])
        ->orderBy('sort_order')
        ->orderBy('id')
        ->get();
    if ($playlistRows->isEmpty()) {
        throw new RuntimeException('Fixture limitation: the installed test database has no playlists');
    }

    $playlists = [];
    $default = null;
    foreach ($playlistRows as $playlist) {
        if ($default === null && !empty($playlist->is_default)) {
            $default = (string) $playlist->filename;
        }
        $playlists[] = [
            'id' => (int) $playlist->id,
            'filename' => (string) $playlist->filename,
            'dbtitle' => (string) $playlist->dbtitle,
            'dbversion' => $playlist->dbversion,
            'lastupdated' => $playlist->lastupdated,
            'author' => $playlist->author,
            'email' => $playlist->email,
            'link' => $playlist->link,
            'is_default' => (bool) $playlist->is_default,
        ];
    }
    $default ??= $playlists[0]['filename'];

    $candidates = $connection->table('playlist_shows as shows')
        ->join('playlists as playlists', 'playlists.id', '=', 'shows.playlist_id')
        ->whereIn('shows.status', ['active', 'disabled'])
        ->select([
            'shows.id as show_id',
            'shows.playlist_id',
            'shows.identifier',
            'shows.status',
            'shows.updated_at as show_updated_at',
            'shows.sort_order as show_sort_order',
            'playlists.filename',
            'playlists.lastupdated',
            'playlists.updated_at as playlist_updated_at',
            'playlists.sort_order as playlist_sort_order',
        ])
        ->orderBy('playlists.sort_order')
        ->orderBy('playlists.id')
        ->orderBy('shows.sort_order')
        ->orderBy('shows.id')
        ->get();

    $toggle = null;
    foreach ($candidates as $candidate) {
        if (is_string($candidate->filename)
            && preg_match('/^[a-zA-Z0-9_-]+\.json$/D', $candidate->filename) === 1
            && strcasecmp($candidate->filename, 'index.json') !== 0) {
            $toggle = $candidate;
            break;
        }
    }
    if ($toggle === null) {
        throw new RuntimeException(
            'Fixture limitation: no playlist show with an active or disabled status is available'
        );
    }

    $selectedPlaylist = null;
    foreach ($playlists as $playlist) {
        if ($playlist['id'] === (int) $toggle->playlist_id) {
            $selectedPlaylist = $playlist;
            break;
        }
    }
    if ($selectedPlaylist === null) {
        throw new RuntimeException('Fixture limitation: selected show has no matching playlist');
    }

    $showRows = $connection->table('playlist_shows')
        ->where('playlist_id', $selectedPlaylist['id'])
        ->select([
            'id', 'category', 'status', 'identifier', 'title', 'description',
            'start_year', 'end_year', 'imdb', 'group_name', 'sort_order',
        ])
        ->orderBy('sort_order')
        ->orderBy('id')
        ->get();
    if ($showRows->isEmpty()) {
        throw new RuntimeException('Fixture limitation: selected playlist has no shows');
    }

    $shows = [];
    foreach ($showRows as $show) {
        $shows[] = [
            'id' => (int) $show->id,
            'category' => $show->category,
            'status' => (string) $show->status,
            'identifier' => (string) $show->identifier,
            'title' => (string) $show->title,
            'description' => $show->description,
            'start_year' => $show->start_year,
            'end_year' => $show->end_year,
            'imdb' => $show->imdb,
            'group_name' => $show->group_name,
        ];
    }

    return [
        'source_database' => $context['state']['source_database'],
        'test_database' => $context['state']['test_database'],
        'server_time' => serverTimestamp($connection),
        'index' => ['default' => $default, 'playlists' => $playlists],
        'playlist' => ['metadata' => $selectedPlaylist, 'shows' => $shows],
        'toggle' => [
            'playlist_id' => (int) $toggle->playlist_id,
            'filename' => (string) $toggle->filename,
            'show_id' => (int) $toggle->show_id,
            'identifier' => (string) $toggle->identifier,
            'status' => (string) $toggle->status,
            'show_updated_at' => (string) $toggle->show_updated_at,
            'lastupdated' => $toggle->lastupdated,
            'playlist_updated_at' => (string) $toggle->playlist_updated_at,
        ],
    ];
}

/** @return array{test_database: string, playlist_id: int, show_id: int, identifier: string} */
function scopedRecordPayload(array $payload, array $state): array
{
    foreach (['test_database', 'identifier'] as $key) {
        if (!isset($payload[$key]) || !is_string($payload[$key]) || $payload[$key] === '') {
            throw new RuntimeException("Database test support payload has an invalid {$key}");
        }
    }
    foreach (['playlist_id', 'show_id'] as $key) {
        if (!isset($payload[$key]) || !is_int($payload[$key]) || $payload[$key] < 1) {
            throw new RuntimeException("Database test support payload has an invalid {$key}");
        }
    }
    if ($payload['test_database'] !== $state['test_database']) {
        throw new RuntimeException('Database test support target does not match tracked test database');
    }

    return [
        'test_database' => $payload['test_database'],
        'playlist_id' => $payload['playlist_id'],
        'show_id' => $payload['show_id'],
        'identifier' => $payload['identifier'],
    ];
}

/** @return array<string, mixed> */
function recordState(array $context, array $payload): array
{
    $scope = scopedRecordPayload($payload, $context['state']);
    $connection = $context['connection'];
    $show = $connection->table('playlist_shows')
        ->where('id', $scope['show_id'])
        ->where('playlist_id', $scope['playlist_id'])
        ->where('identifier', $scope['identifier'])
        ->first(['id', 'playlist_id', 'identifier', 'status', 'updated_at']);
    $playlist = $connection->table('playlists')
        ->where('id', $scope['playlist_id'])
        ->first(['id', 'filename', 'lastupdated', 'updated_at']);
    if ($show === null || $playlist === null) {
        throw new RuntimeException('Scoped cloned record no longer exists');
    }

    return [
        'test_database' => $context['state']['test_database'],
        'server_time' => serverTimestamp($connection),
        'playlist_id' => (int) $playlist->id,
        'filename' => (string) $playlist->filename,
        'show_id' => (int) $show->id,
        'identifier' => (string) $show->identifier,
        'status' => (string) $show->status,
        'show_updated_at' => (string) $show->updated_at,
        'lastupdated' => $playlist->lastupdated,
        'playlist_updated_at' => (string) $playlist->updated_at,
    ];
}

/** @return array<string, mixed> */
function restoreRecord(array $context, array $payload): array
{
    $scope = scopedRecordPayload($payload, $context['state']);
    if (!isset($payload['status']) || !is_string($payload['status'])
        || !in_array($payload['status'], ['active', 'disabled'], true)) {
        throw new RuntimeException('Database test restore status is invalid');
    }
    if (!array_key_exists('lastupdated', $payload)
        || (!is_string($payload['lastupdated']) && $payload['lastupdated'] !== null)) {
        throw new RuntimeException('Database test restore timestamp is invalid');
    }
    foreach (['show_updated_at', 'playlist_updated_at'] as $key) {
        if (!isset($payload[$key]) || !is_string($payload[$key]) || $payload[$key] === '') {
            throw new RuntimeException("Database test restore {$key} is invalid");
        }
    }

    $connection = $context['connection'];
    $connection->transaction(function () use ($connection, $scope, $payload): void {
        $show = $connection->table('playlist_shows')
            ->where('id', $scope['show_id'])
            ->where('playlist_id', $scope['playlist_id'])
            ->where('identifier', $scope['identifier'])
            ->lockForUpdate()
            ->first(['id', 'status']);
        $playlist = $connection->table('playlists')
            ->where('id', $scope['playlist_id'])
            ->lockForUpdate()
            ->first(['id']);
        if ($show === null || $playlist === null) {
            throw new RuntimeException('Cannot restore missing cloned playlist record');
        }
        if (!in_array($show->status, ['active', 'disabled'], true)) {
            throw new RuntimeException('Cannot restore cloned record from an unexpected status');
        }

        $updated = $connection->table('playlist_shows')
            ->where('id', $scope['show_id'])
            ->where('playlist_id', $scope['playlist_id'])
            ->where('identifier', $scope['identifier'])
            ->update([
                'status' => $payload['status'],
                'updated_at' => $payload['show_updated_at'],
            ]);
        if (!in_array($updated, [0, 1], true)) {
            throw new RuntimeException('Clone status restoration affected an unexpected number of rows');
        }

        $playlistUpdated = $connection->table('playlists')
            ->where('id', $scope['playlist_id'])
            ->update([
                'lastupdated' => $payload['lastupdated'],
                'updated_at' => $payload['playlist_updated_at'],
            ]);
        if (!in_array($playlistUpdated, [0, 1], true)) {
            throw new RuntimeException('Clone playlist restoration affected an unexpected number of rows');
        }
    });

    return recordState($context, $scope);
}

try {
    if ($argc !== 2 || !in_array($argv[1], ['context', 'state', 'restore'], true)) {
        throw new RuntimeException('Database test support requires context, state, or restore');
    }

    $context = readyTestDatabase();
    $payload = bridgePayload();
    $result = match ($argv[1]) {
        'context' => fixtureContext($context),
        'state' => recordState($context, $payload),
        'restore' => restoreRecord($context, $payload),
    };
    fwrite(STDOUT, json_encode($result, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES) . "\n");
} catch (Throwable $exception) {
    fwrite(STDERR, 'Database-backed API test setup failed: ' . $exception->getMessage() . "\n");
    exit(1);
}
