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

/** @return array{test_database: string, token: string, playlist_a_id?: int, playlist_b_id?: int} */
function showFixtureScope(array $payload, array $state, bool $requirePlaylistIds = true): array
{
    if (!isset($payload['test_database']) || !is_string($payload['test_database'])
        || $payload['test_database'] !== $state['test_database']) {
        throw new RuntimeException('Show fixture target does not match tracked test database');
    }
    if (!isset($payload['token']) || !is_string($payload['token'])
        || preg_match('/\A[a-f0-9]{12}\z/D', $payload['token']) !== 1) {
        throw new RuntimeException('Show fixture token is invalid');
    }

    $scope = [
        'test_database' => $payload['test_database'],
        'token' => $payload['token'],
    ];
    if ($requirePlaylistIds) {
        foreach (['playlist_a_id', 'playlist_b_id'] as $key) {
            if (!isset($payload[$key]) || !is_int($payload[$key]) || $payload[$key] < 1) {
                throw new RuntimeException("Show fixture payload has an invalid {$key}");
            }
            $scope[$key] = $payload[$key];
        }
    }

    return $scope;
}

/** @return array{a: string, b: string} */
function showFixtureFilenames(string $token): array
{
    return [
        'a' => "regression-show-{$token}-a.json",
        'b' => "regression-show-{$token}-b.json",
    ];
}

function showFixtureTitle(string $token, string $suffix): string
{
    return "Regression Show {$token} {$suffix}";
}

/** @return array{playlists: int, shows: int} */
function showFixtureCounts($connection): array
{
    return [
        'playlists' => (int) $connection->table('playlists')->count(),
        'shows' => (int) $connection->table('playlist_shows')->count(),
    ];
}

/** @return array<string, mixed> */
function createShowFixture(array $context, array $payload): array
{
    $scope = showFixtureScope($payload, $context['state'], false);
    $connection = $context['connection'];
    Support::assertSelectedDatabase($connection, $context['state']['test_database']);
    $filenames = showFixtureFilenames($scope['token']);
    $countsBefore = showFixtureCounts($connection);

    $ids = $connection->transaction(function () use ($connection, $scope, $filenames): array {
        $existing = $connection->table('playlists')
            ->whereIn('filename', array_values($filenames))
            ->count();
        if ($existing !== 0) {
            throw new RuntimeException('Show fixture playlist already exists');
        }

        $maxPlaylistOrder = $connection->table('playlists')->max('sort_order');
        $firstPlaylistOrder = $maxPlaylistOrder === null ? 0 : (int) $maxPlaylistOrder + 1;
        $playlistA = $connection->table('playlists')->insertGetId([
            'filename' => $filenames['a'],
            'dbtitle' => showFixtureTitle($scope['token'], 'Playlist A'),
            'dbversion' => 'test',
            'author' => 'FreeTV Regression',
            'email' => 'regression@example.test',
            'link' => null,
            'lastupdated' => '2000-01-01 00:00:00',
            'is_default' => 0,
            'sort_order' => $firstPlaylistOrder,
        ]);
        $playlistB = $connection->table('playlists')->insertGetId([
            'filename' => $filenames['b'],
            'dbtitle' => showFixtureTitle($scope['token'], 'Playlist B'),
            'dbversion' => 'test',
            'author' => 'FreeTV Regression',
            'email' => 'regression@example.test',
            'link' => null,
            'lastupdated' => '2000-01-01 00:00:00',
            'is_default' => 0,
            'sort_order' => $firstPlaylistOrder + 1,
        ]);

        $base = [
            'category' => 'Regression',
            'status' => 'active',
            'description' => 'Regression fixture description',
            'start_year' => '1960',
            'end_year' => '1961',
            'imdb' => 'tt9000001',
            'group_name' => null,
        ];
        $connection->table('playlist_shows')->insert([
            array_merge($base, [
                'playlist_id' => $playlistA,
                'identifier' => "regression-{$scope['token']}-seed-a",
                'title' => 'Seed A',
                'sort_order' => 10,
            ]),
            array_merge($base, [
                'playlist_id' => $playlistA,
                'identifier' => "regression-{$scope['token']}-edit-source",
                'title' => 'Edit Source',
                'sort_order' => 20,
                'imdb' => 'tt9000002',
            ]),
            array_merge($base, [
                'playlist_id' => $playlistA,
                'identifier' => "regression-{$scope['token']}-edit-target",
                'title' => 'Edit Target',
                'sort_order' => 30,
                'imdb' => 'tt9000003',
            ]),
            array_merge($base, [
                'playlist_id' => $playlistB,
                'identifier' => "regression-{$scope['token']}-seed-b",
                'title' => 'Seed B',
                'sort_order' => 5,
                'imdb' => 'tt9000004',
            ]),
        ]);

        return ['playlist_a_id' => (int) $playlistA, 'playlist_b_id' => (int) $playlistB];
    });

    return array_merge($scope, $ids, [
        'filenames' => $filenames,
        'counts_before' => $countsBefore,
        'server_time' => serverTimestamp($connection),
    ]);
}

/** @return array<string, mixed> */
function showFixtureState(array $context, array $payload): array
{
    $scope = showFixtureScope($payload, $context['state']);
    $connection = $context['connection'];
    $filenames = showFixtureFilenames($scope['token']);
    $playlists = [];

    foreach (['a' => 'playlist_a_id', 'b' => 'playlist_b_id'] as $key => $idKey) {
        $playlist = $connection->table('playlists')
            ->where('id', $scope[$idKey])
            ->where('filename', $filenames[$key])
            ->where('dbtitle', showFixtureTitle($scope['token'], 'Playlist ' . strtoupper($key)))
            ->first([
                'id', 'filename', 'dbtitle', 'lastupdated', 'updated_at', 'sort_order',
            ]);
        if ($playlist === null) {
            throw new RuntimeException("Show fixture playlist {$key} is missing or no longer owned by the test");
        }

        $shows = [];
        foreach ($connection->table('playlist_shows')
            ->where('playlist_id', $scope[$idKey])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get([
                'id', 'playlist_id', 'category', 'status', 'identifier', 'title',
                'description', 'start_year', 'end_year', 'imdb', 'group_name',
                'sort_order', 'created_at', 'updated_at',
            ]) as $show) {
            $shows[] = [
                'id' => (int) $show->id,
                'playlist_id' => (int) $show->playlist_id,
                'category' => $show->category,
                'status' => (string) $show->status,
                'identifier' => (string) $show->identifier,
                'title' => (string) $show->title,
                'description' => $show->description,
                'start_year' => $show->start_year,
                'end_year' => $show->end_year,
                'imdb' => $show->imdb,
                'group_name' => $show->group_name,
                'sort_order' => (int) $show->sort_order,
                'created_at' => (string) $show->created_at,
                'updated_at' => (string) $show->updated_at,
            ];
        }
        $playlists[$key] = [
            'id' => (int) $playlist->id,
            'filename' => (string) $playlist->filename,
            'dbtitle' => (string) $playlist->dbtitle,
            'lastupdated' => $playlist->lastupdated,
            'updated_at' => (string) $playlist->updated_at,
            'sort_order' => (int) $playlist->sort_order,
            'shows' => $shows,
        ];
    }

    return [
        'test_database' => $context['state']['test_database'],
        'server_time' => serverTimestamp($connection),
        'playlists' => $playlists,
    ];
}

/** @return array<string, mixed> */
function cleanupShowFixture(array $context, array $payload): array
{
    $scope = showFixtureScope($payload, $context['state']);
    $connection = $context['connection'];
    Support::assertSelectedDatabase($connection, $context['state']['test_database']);
    $filenames = showFixtureFilenames($scope['token']);

    $deleted = $connection->transaction(function () use ($connection, $scope, $filenames): int {
        $deleted = 0;
        foreach (['a' => 'playlist_a_id', 'b' => 'playlist_b_id'] as $key => $idKey) {
            $deleted += $connection->table('playlists')
                ->where('id', $scope[$idKey])
                ->where('filename', $filenames[$key])
                ->where('dbtitle', showFixtureTitle($scope['token'], 'Playlist ' . strtoupper($key)))
                ->delete();
        }
        return $deleted;
    });

    $remainingPlaylists = (int) $connection->table('playlists')
        ->whereIn('filename', array_values($filenames))
        ->count();
    $remainingShows = (int) $connection->table('playlist_shows')
        ->whereIn('playlist_id', [$scope['playlist_a_id'], $scope['playlist_b_id']])
        ->count();

    return [
        'test_database' => $context['state']['test_database'],
        'deleted_playlists' => $deleted,
        'remaining_playlists' => $remainingPlaylists,
        'remaining_shows' => $remainingShows,
        'counts_after' => showFixtureCounts($connection),
    ];
}

/** @return array{test_database: string, token: string, playlist_a_id?: int, playlist_b_id?: int, baseline_attempt_id?: int} */
function reportFixtureScope(array $payload, array $state, bool $requireCreatedState = true): array
{
    if (!isset($payload['test_database']) || !is_string($payload['test_database'])
        || $payload['test_database'] !== $state['test_database']) {
        throw new RuntimeException('Problem-report fixture target does not match tracked test database');
    }
    if (!isset($payload['token']) || !is_string($payload['token'])
        || preg_match('/\A[a-f0-9]{12}\z/D', $payload['token']) !== 1) {
        throw new RuntimeException('Problem-report fixture token is invalid');
    }

    $scope = [
        'test_database' => $payload['test_database'],
        'token' => $payload['token'],
    ];
    if ($requireCreatedState) {
        foreach (['playlist_a_id', 'playlist_b_id'] as $key) {
            if (!isset($payload[$key]) || !is_int($payload[$key]) || $payload[$key] < 1) {
                throw new RuntimeException("Problem-report fixture payload has an invalid {$key}");
            }
            $scope[$key] = $payload[$key];
        }
        if (!isset($payload['baseline_attempt_id']) || !is_int($payload['baseline_attempt_id'])
            || $payload['baseline_attempt_id'] < 0) {
            throw new RuntimeException('Problem-report fixture payload has an invalid baseline_attempt_id');
        }
        $scope['baseline_attempt_id'] = $payload['baseline_attempt_id'];
    }

    return $scope;
}

/** @return array{a: string, b: string} */
function reportFixtureFilenames(string $token): array
{
    return [
        'a' => "regression-report-{$token}-a.json",
        'b' => "regression-report-{$token}-b.json",
    ];
}

/** @return array{primary: string, shared: string, addressed: string, disabled: string} */
function reportFixtureIdentifiers(string $token): array
{
    return [
        'primary' => "regression-report-{$token}-primary",
        'shared' => "regression-report-{$token}-shared",
        'addressed' => "regression-report-{$token}-addressed",
        'disabled' => "regression-report-{$token}-disabled",
    ];
}

function reportFixtureTitle(string $token, string $suffix): string
{
    return "Regression Report {$token} {$suffix}";
}

/** @return array{playlists: int, shows: int, reports: int, attempts: int} */
function reportFixtureCounts($connection): array
{
    return [
        'playlists' => (int) $connection->table('playlists')->count(),
        'shows' => (int) $connection->table('playlist_shows')->count(),
        'reports' => (int) $connection->table('problem_reports')->count(),
        'attempts' => (int) $connection->table('problem_report_ips')->count(),
    ];
}

/** @return array<string, mixed> */
function createReportFixture(array $context, array $payload): array
{
    $scope = reportFixtureScope($payload, $context['state'], false);
    $connection = $context['connection'];
    Support::assertSelectedDatabase($connection, $context['state']['test_database']);

    $clientIp = '127.0.0.1';
    $existingClientAttempts = (int) $connection->table('problem_report_ips')
        ->where('ip_address', $clientIp)
        ->count();
    if ($existingClientAttempts !== 0) {
        throw new RuntimeException(
            "Rate-limit isolation requires no pre-existing {$clientIp} attempts; found {$existingClientAttempts}"
        );
    }

    $filenames = reportFixtureFilenames($scope['token']);
    $identifiers = reportFixtureIdentifiers($scope['token']);
    $countsBefore = reportFixtureCounts($connection);
    $maximumAttemptId = $connection->table('problem_report_ips')->max('id');
    $baselineAttemptId = $maximumAttemptId === null ? 0 : (int) $maximumAttemptId;

    $ids = $connection->transaction(function () use (
        $connection,
        $scope,
        $filenames,
        $identifiers
    ): array {
        if ($connection->table('playlists')->whereIn('filename', array_values($filenames))->exists()) {
            throw new RuntimeException('Problem-report fixture playlist already exists');
        }

        $maxPlaylistOrder = $connection->table('playlists')->max('sort_order');
        $firstPlaylistOrder = $maxPlaylistOrder === null ? 0 : (int) $maxPlaylistOrder + 1;
        $playlistA = (int) $connection->table('playlists')->insertGetId([
            'filename' => $filenames['a'],
            'dbtitle' => reportFixtureTitle($scope['token'], 'Playlist A'),
            'dbversion' => 'test',
            'author' => 'FreeTV Regression',
            'email' => 'regression@example.test',
            'link' => null,
            'lastupdated' => '2000-01-01 00:00:00',
            'is_default' => 0,
            'sort_order' => $firstPlaylistOrder,
        ]);
        $playlistB = (int) $connection->table('playlists')->insertGetId([
            'filename' => $filenames['b'],
            'dbtitle' => reportFixtureTitle($scope['token'], 'Playlist B'),
            'dbversion' => 'test',
            'author' => 'FreeTV Regression',
            'email' => 'regression@example.test',
            'link' => null,
            'lastupdated' => '2000-01-01 00:00:00',
            'is_default' => 0,
            'sort_order' => $firstPlaylistOrder + 1,
        ]);

        $base = [
            'category' => 'Regression Reports',
            'status' => 'active',
            'description' => 'Problem-report regression fixture',
            'start_year' => '1960',
            'end_year' => '1961',
            'group_name' => null,
        ];
        $shows = [
            'primary' => (int) $connection->table('playlist_shows')->insertGetId(array_merge($base, [
                'playlist_id' => $playlistA,
                'identifier' => $identifiers['primary'],
                'title' => 'Primary Active Report Show',
                'imdb' => 'tt9200001',
                'sort_order' => 10,
            ])),
            'shared_a' => (int) $connection->table('playlist_shows')->insertGetId(array_merge($base, [
                'playlist_id' => $playlistA,
                'identifier' => $identifiers['shared'],
                'title' => 'Shared Report Show A',
                'imdb' => 'tt9200002',
                'sort_order' => 20,
            ])),
            'addressed' => (int) $connection->table('playlist_shows')->insertGetId(array_merge($base, [
                'playlist_id' => $playlistA,
                'identifier' => $identifiers['addressed'],
                'title' => 'Addressed Report Show',
                'imdb' => 'tt9200003',
                'sort_order' => 30,
            ])),
            'disabled' => (int) $connection->table('playlist_shows')->insertGetId(array_merge($base, [
                'playlist_id' => $playlistA,
                'identifier' => $identifiers['disabled'],
                'title' => 'Disabled Report Show',
                'imdb' => 'tt9200004',
                'status' => 'disabled',
                'sort_order' => 40,
            ])),
            'shared_b' => (int) $connection->table('playlist_shows')->insertGetId(array_merge($base, [
                'playlist_id' => $playlistB,
                'identifier' => $identifiers['shared'],
                'title' => 'Shared Report Show B',
                'imdb' => 'tt9200005',
                'sort_order' => 10,
            ])),
        ];

        return [
            'playlist_a_id' => $playlistA,
            'playlist_b_id' => $playlistB,
            'show_ids' => $shows,
        ];
    });

    return array_merge($scope, $ids, [
        'filenames' => $filenames,
        'identifiers' => $identifiers,
        'client_ip' => $clientIp,
        'baseline_attempt_id' => $baselineAttemptId,
        'counts_before' => $countsBefore,
    ]);
}

/** @return array<string, mixed> */
function reportFixtureState(array $context, array $payload): array
{
    $scope = reportFixtureScope($payload, $context['state']);
    $connection = $context['connection'];
    Support::assertSelectedDatabase($connection, $context['state']['test_database']);
    $filenames = reportFixtureFilenames($scope['token']);
    $identifiers = reportFixtureIdentifiers($scope['token']);
    $playlistIds = [$scope['playlist_a_id'], $scope['playlist_b_id']];

    $playlistCount = (int) $connection->table('playlists')
        ->where(function ($query) use ($scope, $filenames): void {
            $query->where(function ($inner) use ($scope, $filenames): void {
                $inner->where('id', $scope['playlist_a_id'])->where('filename', $filenames['a']);
            })->orWhere(function ($inner) use ($scope, $filenames): void {
                $inner->where('id', $scope['playlist_b_id'])->where('filename', $filenames['b']);
            });
        })
        ->count();
    if ($playlistCount !== 2) {
        throw new RuntimeException('Problem-report fixture playlists are missing or no longer test-owned');
    }

    $shows = [];
    foreach ($connection->table('playlist_shows')
        ->whereIn('playlist_id', $playlistIds)
        ->orderBy('playlist_id')
        ->orderBy('sort_order')
        ->get(['id', 'playlist_id', 'identifier', 'title', 'category', 'imdb', 'status']) as $show) {
        $shows[] = [
            'id' => (int) $show->id,
            'playlist_id' => (int) $show->playlist_id,
            'identifier' => (string) $show->identifier,
            'title' => (string) $show->title,
            'category' => $show->category,
            'imdb' => $show->imdb,
            'status' => (string) $show->status,
        ];
    }

    $reports = [];
    foreach ($connection->table('problem_reports')
        ->whereIn('playlist_id', $playlistIds)
        ->orderBy('id')
        ->get([
            'id', 'playlist_id', 'playlist_show_id', 'identifier', 'title', 'category', 'imdb',
            'status', 'report_count', 'first_reported_at', 'last_reported_at',
        ]) as $report) {
        $reports[] = [
            'id' => (int) $report->id,
            'playlist_id' => (int) $report->playlist_id,
            'playlist_show_id' => $report->playlist_show_id === null ? null : (int) $report->playlist_show_id,
            'identifier' => (string) $report->identifier,
            'title' => $report->title,
            'category' => $report->category,
            'imdb' => $report->imdb,
            'status' => (string) $report->status,
            'report_count' => (int) $report->report_count,
            'first_reported_at' => (string) $report->first_reported_at,
            'last_reported_at' => (string) $report->last_reported_at,
        ];
    }

    $attempts = [];
    foreach ($connection->table('problem_report_ips')
        ->where('ip_address', '127.0.0.1')
        ->orderBy('id')
        ->get(['id', 'ip_address', 'attempted_at']) as $attempt) {
        if ((int) $attempt->id <= $scope['baseline_attempt_id']) {
            throw new RuntimeException('Unexpected pre-fixture client-IP attempt appeared during reporting tests');
        }
        $attempts[] = [
            'id' => (int) $attempt->id,
            'ip_address' => (string) $attempt->ip_address,
            'attempted_at' => (string) $attempt->attempted_at,
        ];
    }

    return [
        'test_database' => $context['state']['test_database'],
        'filenames' => $filenames,
        'identifiers' => $identifiers,
        'shows' => $shows,
        'reports' => $reports,
        'attempts' => $attempts,
        'counts' => reportFixtureCounts($connection),
    ];
}

/** @return array<string, mixed> */
function prepareAddressedReport(array $context, array $payload): array
{
    $scope = reportFixtureScope($payload, $context['state']);
    $connection = $context['connection'];
    Support::assertSelectedDatabase($connection, $context['state']['test_database']);
    $identifier = reportFixtureIdentifiers($scope['token'])['addressed'];

    $reportId = $connection->transaction(function () use ($connection, $scope, $identifier): int {
        $show = $connection->table('playlist_shows')
            ->where('playlist_id', $scope['playlist_a_id'])
            ->where('identifier', $identifier)
            ->where('status', 'active')
            ->first(['id', 'title', 'category', 'imdb']);
        if ($show === null) {
            throw new RuntimeException('Addressed-report fixture show is missing');
        }
        if ($connection->table('problem_reports')
            ->where('playlist_id', $scope['playlist_a_id'])
            ->where('identifier', $identifier)
            ->exists()) {
            throw new RuntimeException('Addressed-report fixture already exists');
        }

        return (int) $connection->table('problem_reports')->insertGetId([
            'playlist_id' => $scope['playlist_a_id'],
            'playlist_show_id' => (int) $show->id,
            'identifier' => $identifier,
            'title' => $show->title,
            'category' => $show->category,
            'imdb' => $show->imdb,
            'status' => 'addressed',
            'report_count' => 4,
            'first_reported_at' => '2000-01-01 00:00:00',
            'last_reported_at' => '2000-01-02 00:00:00',
        ]);
    });

    return ['test_database' => $context['state']['test_database'], 'report_id' => $reportId];
}

/** @return array<string, mixed> */
function resetReportFixtureAttempts(array $context, array $payload): array
{
    $scope = reportFixtureScope($payload, $context['state']);
    if (!isset($payload['attempt_ids']) || !is_array($payload['attempt_ids'])) {
        throw new RuntimeException('Problem-report fixture attempt_ids must be an array');
    }
    $attemptIds = $payload['attempt_ids'];
    foreach ($attemptIds as $attemptId) {
        if (!is_int($attemptId) || $attemptId <= $scope['baseline_attempt_id']) {
            throw new RuntimeException('Problem-report fixture attempt ID is invalid');
        }
    }
    sort($attemptIds);
    if ($attemptIds !== array_values(array_unique($attemptIds))) {
        throw new RuntimeException('Problem-report fixture attempt IDs must be unique');
    }

    $connection = $context['connection'];
    Support::assertSelectedDatabase($connection, $context['state']['test_database']);
    $currentIds = array_map(
        static fn(object $row): int => (int) $row->id,
        $connection->table('problem_report_ips')
            ->where('ip_address', '127.0.0.1')
            ->orderBy('id')
            ->get(['id'])
            ->all()
    );
    if ($currentIds !== $attemptIds) {
        throw new RuntimeException('Problem-report attempt cleanup does not match the exact test-owned IP rows');
    }

    $deleted = $attemptIds === [] ? 0 : $connection->table('problem_report_ips')
        ->where('ip_address', '127.0.0.1')
        ->whereIn('id', $attemptIds)
        ->delete();
    if ($deleted !== count($attemptIds)) {
        throw new RuntimeException('Problem-report attempt cleanup affected an unexpected number of rows');
    }

    return [
        'test_database' => $context['state']['test_database'],
        'deleted_attempts' => $deleted,
        'remaining_client_attempts' => (int) $connection->table('problem_report_ips')
            ->where('ip_address', '127.0.0.1')
            ->count(),
    ];
}

/** @return array<string, mixed> */
function cleanupReportFixture(array $context, array $payload): array
{
    $scope = reportFixtureScope($payload, $context['state']);
    $connection = $context['connection'];
    Support::assertSelectedDatabase($connection, $context['state']['test_database']);
    $filenames = reportFixtureFilenames($scope['token']);
    $identifiers = reportFixtureIdentifiers($scope['token']);
    $playlistIds = [$scope['playlist_a_id'], $scope['playlist_b_id']];

    $attemptCleanup = resetReportFixtureAttempts($context, $payload);
    $deleted = $connection->transaction(function () use (
        $connection,
        $scope,
        $filenames,
        $identifiers,
        $playlistIds
    ): array {
        $fixtureReports = $connection->table('problem_reports')
            ->whereIn('playlist_id', $playlistIds)
            ->get(['id', 'identifier']);
        foreach ($fixtureReports as $report) {
            if (!in_array($report->identifier, array_values($identifiers), true)) {
                throw new RuntimeException('Problem-report fixture playlist contains a non-fixture report');
            }
        }
        $deletedReports = $connection->table('problem_reports')
            ->whereIn('playlist_id', $playlistIds)
            ->delete();

        $deletedPlaylists = 0;
        foreach (['a' => 'playlist_a_id', 'b' => 'playlist_b_id'] as $key => $idKey) {
            $deletedPlaylists += $connection->table('playlists')
                ->where('id', $scope[$idKey])
                ->where('filename', $filenames[$key])
                ->where('dbtitle', reportFixtureTitle($scope['token'], 'Playlist ' . strtoupper($key)))
                ->delete();
        }
        return ['reports' => $deletedReports, 'playlists' => $deletedPlaylists];
    });

    return [
        'test_database' => $context['state']['test_database'],
        'deleted_reports' => $deleted['reports'],
        'deleted_playlists' => $deleted['playlists'],
        'deleted_attempts' => $attemptCleanup['deleted_attempts'],
        'remaining_playlists' => (int) $connection->table('playlists')
            ->whereIn('filename', array_values($filenames))
            ->count(),
        'remaining_shows' => (int) $connection->table('playlist_shows')
            ->whereIn('playlist_id', $playlistIds)
            ->count(),
        'remaining_reports' => (int) $connection->table('problem_reports')
            ->whereIn('playlist_id', $playlistIds)
            ->count(),
        'remaining_client_attempts' => (int) $connection->table('problem_report_ips')
            ->where('ip_address', '127.0.0.1')
            ->count(),
        'counts_after' => reportFixtureCounts($connection),
    ];
}

try {
    $actions = [
        'context',
        'state',
        'restore',
        'show-fixture-create',
        'show-fixture-state',
        'show-fixture-cleanup',
        'report-fixture-create',
        'report-fixture-state',
        'report-fixture-prepare-addressed',
        'report-fixture-reset-attempts',
        'report-fixture-cleanup',
    ];
    if ($argc !== 2 || !in_array($argv[1], $actions, true)) {
        throw new RuntimeException('Database test support received an unsupported action');
    }

    $context = readyTestDatabase();
    $payload = bridgePayload();
    $result = match ($argv[1]) {
        'context' => fixtureContext($context),
        'state' => recordState($context, $payload),
        'restore' => restoreRecord($context, $payload),
        'show-fixture-create' => createShowFixture($context, $payload),
        'show-fixture-state' => showFixtureState($context, $payload),
        'show-fixture-cleanup' => cleanupShowFixture($context, $payload),
        'report-fixture-create' => createReportFixture($context, $payload),
        'report-fixture-state' => reportFixtureState($context, $payload),
        'report-fixture-prepare-addressed' => prepareAddressedReport($context, $payload),
        'report-fixture-reset-attempts' => resetReportFixtureAttempts($context, $payload),
        'report-fixture-cleanup' => cleanupReportFixture($context, $payload),
    };
    fwrite(STDOUT, json_encode($result, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES) . "\n");
} catch (Throwable $exception) {
    fwrite(STDERR, 'Database-backed API test setup failed: ' . $exception->getMessage() . "\n");
    exit(1);
}
