<?php

declare(strict_types=1);

function endpointAssertSame(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message . "\nExpected: " . var_export($expected, true)
            . "\nActual: " . var_export($actual, true));
    }
}

function runProductionSnapshotEndpoint(
    string $endpointName,
    string $method,
    ?array $user,
    array $query = []
): array {
    $endpoint = realpath(__DIR__ . '/../public/api/admin/' . $endpointName);
    $code = '$_SERVER["REQUEST_METHOD"] = ' . var_export($method, true) . ';'
        . '$_GET = ' . var_export($query, true) . ';';
    if ($user !== null) {
        $code .= 'ini_set("session.save_path", sys_get_temp_dir());'
            . 'session_id("production-snapshot-' . bin2hex(random_bytes(8)) . '");'
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
        throw new RuntimeException('Could not start production snapshot endpoint test');
    }
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exitCode = proc_close($process);
    return [$exitCode, $stdout, $stderr];
}

function endpointJson(string $body): array
{
    return json_decode($body, true, 512, JSON_THROW_ON_ERROR);
}

$viewer = ['id' => 1, 'username' => 'viewer', 'role' => 'viewer'];
$admin = ['id' => 2, 'username' => 'admin', 'role' => 'admin'];

[, $createUnauthorized] = runProductionSnapshotEndpoint('data-snapshot-create.php', 'POST', null);
endpointAssertSame(['success' => false, 'message' => 'Unauthorized'], endpointJson($createUnauthorized),
    'Unauthenticated snapshot creation was not rejected');

[, $createForbidden] = runProductionSnapshotEndpoint('data-snapshot-create.php', 'POST', $viewer);
endpointAssertSame(['success' => false, 'message' => 'Forbidden'], endpointJson($createForbidden),
    'Non-admin snapshot creation was not rejected');

[, $createMethod] = runProductionSnapshotEndpoint('data-snapshot-create.php', 'GET', $admin);
endpointAssertSame(['success' => false, 'message' => 'Method not allowed'], endpointJson($createMethod),
    'Snapshot creation accepted a non-POST request');

[, $downloadUnauthorized] = runProductionSnapshotEndpoint(
    'data-snapshot-download.php',
    'GET',
    null,
    ['snapshot' => 'freetv-content-snapshot-20260828T183426Z']
);
endpointAssertSame(['success' => false, 'message' => 'Unauthorized'], endpointJson($downloadUnauthorized),
    'Unauthenticated snapshot download was not rejected');

[, $downloadForbidden] = runProductionSnapshotEndpoint(
    'data-snapshot-download.php',
    'GET',
    $viewer,
    ['snapshot' => 'freetv-content-snapshot-20260828T183426Z']
);
endpointAssertSame(['success' => false, 'message' => 'Forbidden'], endpointJson($downloadForbidden),
    'Non-admin snapshot download was not rejected');

[, $downloadMethod] = runProductionSnapshotEndpoint('data-snapshot-download.php', 'POST', $admin);
endpointAssertSame(['success' => false, 'message' => 'Method not allowed'], endpointJson($downloadMethod),
    'Snapshot download accepted a non-GET request');

[, $invalidName] = runProductionSnapshotEndpoint(
    'data-snapshot-download.php',
    'GET',
    $admin,
    ['snapshot' => '../freetv-content-snapshot-20260828T183426Z']
);
endpointAssertSame(['success' => false, 'message' => 'Invalid snapshot name'], endpointJson($invalidName),
    'Snapshot download accepted traversal in the identifier');

[, $missingArchive] = runProductionSnapshotEndpoint(
    'data-snapshot-download.php',
    'GET',
    $admin,
    ['snapshot' => 'freetv-content-snapshot-20990101T000000Z']
);
endpointAssertSame(['success' => false, 'message' => 'Snapshot archive not found'], endpointJson($missingArchive),
    'Missing snapshot archive did not return the safe not-found response');

$downloadName = 'freetv-content-snapshot-20990101T000001Z';
$archiveRoot = __DIR__ . '/../temp/data-snapshots';
if (!is_dir($archiveRoot) && !mkdir($archiveRoot, 0700, true)) {
    throw new RuntimeException('Could not prepare endpoint download fixture');
}
$archivePath = $archiveRoot . '/' . $downloadName . '.zip';
$fixture = new ZipArchive();
if ($fixture->open($archivePath, ZipArchive::CREATE | ZipArchive::EXCL) !== true) {
    throw new RuntimeException('Could not create endpoint ZIP fixture');
}
$fixture->addEmptyDir($downloadName);
$fixture->addFromString($downloadName . '/manifest.json', '{}');
$fixture->addFromString($downloadName . '/playlists.json', '[]');
$fixture->addFromString($downloadName . '/playlist_shows.json', '[]');
$fixture->addFromString($downloadName . '/thumbs-manifest.json', '{}');
$fixture->addEmptyDir($downloadName . '/thumbs');
$fixture->close();
$fixtureBytes = file_get_contents($archivePath);
try {
    [, $downloadBody] = runProductionSnapshotEndpoint(
        'data-snapshot-download.php',
        'GET',
        $admin,
        ['snapshot' => $downloadName]
    );
    endpointAssertSame($fixtureBytes, $downloadBody, 'Authenticated endpoint did not stream exact ZIP bytes');
} finally {
    unlink($archivePath);
}

echo "ProductionContentSnapshot endpoint tests passed\n";
