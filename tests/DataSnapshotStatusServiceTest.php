<?php

require_once __DIR__ . '/../public/api/admin/DataSnapshotManifest.php';
require_once __DIR__ . '/../public/api/admin/DataSnapshotStatusService.php';

use FreeTV\Admin\DataSnapshot\DataSnapshotManifest;
use FreeTV\Admin\DataSnapshot\DataSnapshotStatusService;
use FreeTV\Admin\Publication\PublicationTimestamp;

function assertSnapshotSame($expected, $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException(
            $message . "\nExpected: " . var_export($expected, true)
            . "\nActual: " . var_export($actual, true)
        );
    }
}

function expectInvalidSnapshotManifest(string $json, string $message): void
{
    try {
        DataSnapshotManifest::fromJson($json);
    } catch (InvalidArgumentException) {
        return;
    }

    throw new RuntimeException($message);
}

class SnapshotQueryFixture
{
    public static array $boundaryValues = [];

    private array $rows;
    private array $conditions = [];

    public function __construct(array $rows)
    {
        $this->rows = $rows;
    }

    public function where(string $column, string $operator, string $value): self
    {
        $this->conditions[] = [$column, $operator, $value];
        self::$boundaryValues[] = $value;
        return $this;
    }

    public function count(): int
    {
        return count(array_filter($this->rows, function (array $row): bool {
            foreach ($this->conditions as [$column, $operator, $value]) {
                if ($operator === '>' && !($row[$column] > $value)) return false;
                if ($operator === '<=' && !($row[$column] <= $value)) return false;
            }
            return true;
        }));
    }
}

function runSnapshotEndpoint(string $method, ?array $user): array
{
    $endpoint = realpath(__DIR__ . '/../public/api/admin/data-snapshot-status.php');
    $code = '$_SERVER["REQUEST_METHOD"] = ' . var_export($method, true) . ';';
    if ($user !== null) {
        $code .= 'ini_set("session.save_path", sys_get_temp_dir());'
            . 'session_id("snapshot-' . bin2hex(random_bytes(8)) . '");'
            . 'session_start();'
            . '$_SESSION["admin"] = ' . var_export($user, true) . ';';
    }
    $code .= 'require ' . var_export($endpoint, true) . ';';

    $process = proc_open(
        [PHP_BINARY, '-d', 'display_errors=0', '-r', $code],
        [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes
    );
    if (!is_resource($process)) {
        throw new RuntimeException('Could not start endpoint test process');
    }

    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exitCode = proc_close($process);

    return [$exitCode, $stdout, $stderr];
}

$manifestFixture = [
    'format_version' => 1,
    'production_snapshot_at' => '2026-08-28T16:47:31.000Z',
    'generated_at' => '2026-08-28T16:47:31.000Z',
    'counts' => [
        'playlists' => 5,
        'shows' => 301,
        'thumbnails' => 420,
    ],
];
$manifestJson = json_encode($manifestFixture, JSON_THROW_ON_ERROR);
$parsedManifest = DataSnapshotManifest::fromJson($manifestJson);
assertSnapshotSame($manifestFixture, $parsedManifest, 'Valid manifest was not parsed as expected');

foreach ([
    '{invalid',
    json_encode(array_diff_key($manifestFixture, ['production_snapshot_at' => true]), JSON_THROW_ON_ERROR),
    json_encode(array_replace($manifestFixture, ['production_snapshot_at' => 'not-a-timestamp']), JSON_THROW_ON_ERROR),
    json_encode(array_replace($manifestFixture, ['counts' => ['playlists' => 5]]), JSON_THROW_ON_ERROR),
    json_encode(array_replace_recursive($manifestFixture, ['counts' => ['shows' => -1]]), JSON_THROW_ON_ERROR),
] as $invalidManifest) {
    expectInvalidSnapshotManifest($invalidManifest, 'Invalid manifest was accepted');
}

$databaseBoundary = PublicationTimestamp::toDatabase($parsedManifest['production_snapshot_at']);
assertSnapshotSame('2026-08-28 16:47:31', $databaseBoundary,
    'Snapshot timestamp was not converted to MariaDB format');

$rows = [
    'playlists' => [
        ['created_at' => '2026-08-01 00:00:00', 'updated_at' => '2026-08-01 00:00:00'],
        ['created_at' => '2026-08-28 16:47:32', 'updated_at' => '2026-08-28 16:48:00'],
        ['created_at' => '2026-08-01 00:00:00', 'updated_at' => '2026-08-28 16:47:32'],
        ['created_at' => '2026-08-29 00:00:00', 'updated_at' => '2026-08-29 00:00:00'],
    ],
    'playlist_shows' => [
        ['created_at' => '2026-08-28 16:47:31', 'updated_at' => '2026-08-28 16:47:32'],
        ['created_at' => '2026-08-28 16:47:32', 'updated_at' => '2026-08-28 16:47:33'],
        ['created_at' => '2026-08-01 00:00:00', 'updated_at' => '2026-08-28 16:47:31'],
    ],
];

$service = new DataSnapshotStatusService(
    static fn(): string => $manifestJson,
    static fn(string $table): SnapshotQueryFixture => new SnapshotQueryFixture($rows[$table])
);
$status = $service->status();

assertSnapshotSame(['playlists' => 4, 'shows' => 3], $status['production']['counts'],
    'Current production counts were incorrect');
assertSnapshotSame(['new' => 2, 'updated' => 1], $status['changes_since_snapshot']['playlists'],
    'Playlist changes were classified incorrectly');
assertSnapshotSame(['new' => 1, 'updated' => 1], $status['changes_since_snapshot']['shows'],
    'Show changes were classified incorrectly');
assertSnapshotSame(
    true,
    count(array_unique(SnapshotQueryFixture::$boundaryValues)) === 1
        && SnapshotQueryFixture::$boundaryValues[0] === $databaseBoundary,
    'Status queries did not consistently use the converted snapshot boundary'
);

$viewer = ['id' => 1, 'username' => 'viewer', 'role' => 'viewer'];
$admin = ['id' => 2, 'username' => 'admin', 'role' => 'admin'];

[, $unauthorizedBody] = runSnapshotEndpoint('GET', null);
assertSnapshotSame(
    ['success' => false, 'message' => 'Unauthorized'],
    json_decode($unauthorizedBody, true, 512, JSON_THROW_ON_ERROR),
    'Unauthenticated snapshot endpoint request was not rejected'
);

[, $forbiddenBody] = runSnapshotEndpoint('GET', $viewer);
assertSnapshotSame(
    ['success' => false, 'message' => 'Forbidden'],
    json_decode($forbiddenBody, true, 512, JSON_THROW_ON_ERROR),
    'Non-admin snapshot endpoint request was not rejected'
);

[, $methodBody] = runSnapshotEndpoint('POST', $admin);
assertSnapshotSame(
    ['success' => false, 'message' => 'Method not allowed'],
    json_decode($methodBody, true, 512, JSON_THROW_ON_ERROR),
    'Admin non-GET snapshot endpoint request was not rejected'
);

echo "Data Snapshot Status Service tests passed\n";
