<?php

declare(strict_types=1);

require_once __DIR__ . '/../public/api/admin/DatasetPackageMetadata.php';

use FreeTV\Admin\DatasetPackageMetadata;

const METADATA_SAMPLE_URL = 'https://downloads.example.test/current/freetv-sample-data.zip';
const METADATA_OFFICIAL_URL = 'https://downloads.example.test/current/freetv-official-data.zip';
define('METADATA_SAMPLE_SHA', '1a0bcdef' . str_repeat('2', 56));
define('METADATA_OFFICIAL_SHA', str_repeat('3', 64));

function metadataAssertSame(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message . "\nExpected: " . var_export($expected, true)
            . "\nActual: " . var_export($actual, true));
    }
}

function metadataValues(array $changes = []): array
{
    return array_replace([
        'FREETV_SAMPLE_DATA_URL' => METADATA_SAMPLE_URL,
        'FREETV_SAMPLE_DATA_SHA256' => METADATA_SAMPLE_SHA,
        'FREETV_OFFICIAL_DATA_URL' => METADATA_OFFICIAL_URL,
        'FREETV_OFFICIAL_DATA_SHA256' => METADATA_OFFICIAL_SHA,
    ], $changes);
}

function metadataFrom(array $values): DatasetPackageMetadata
{
    return new DatasetPackageMetadata(static function (string $name) use ($values): array {
        if (!array_key_exists($name, $values)) {
            return ['configured' => false, 'value' => ''];
        }
        return ['configured' => true, 'value' => $values[$name]];
    });
}

function expectInvalidMetadata(array $values, string $message): void
{
    try {
        metadataFrom($values)->value();
    } catch (InvalidArgumentException) {
        return;
    }
    throw new RuntimeException($message);
}

function runMetadataEndpoint(string $method, array $values): array
{
    $endpoint = realpath(__DIR__ . '/../public/api/admin/dataset-package-metadata.php');
    $names = [
        'FREETV_SAMPLE_DATA_URL',
        'FREETV_SAMPLE_DATA_SHA256',
        'FREETV_OFFICIAL_DATA_URL',
        'FREETV_OFFICIAL_DATA_SHA256',
    ];
    $code = '$_SERVER["REQUEST_METHOD"] = ' . var_export($method, true) . ';'
        . 'putenv("DB_HOST=metadata-test-invalid-database-host");'
        . 'register_shutdown_function(static function (): void {'
        . 'fwrite(STDERR, "HTTP_STATUS=" . http_response_code());'
        . '});';
    foreach ($names as $name) {
        $value = $values[$name] ?? '';
        $code .= 'putenv(' . var_export($name . '=' . $value, true) . ');'
            . '$_ENV[' . var_export($name, true) . '] = ' . var_export($value, true) . ';';
    }
    $code .= 'require ' . var_export($endpoint, true) . ';';

    $process = proc_open(
        [PHP_BINARY, '-d', 'display_errors=0', '-r', $code],
        [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes
    );
    if (!is_resource($process)) {
        throw new RuntimeException('Could not start metadata endpoint test process');
    }
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exitCode = proc_close($process);
    preg_match('/HTTP_STATUS=(\d+)/', $stderr, $statusMatch);
    return [$exitCode, (int) ($statusMatch[1] ?? 0), $stdout, $stderr];
}

$expected = [
    'format_version' => 1,
    'sample' => ['url' => METADATA_SAMPLE_URL, 'sha256' => METADATA_SAMPLE_SHA],
    'official' => ['url' => METADATA_OFFICIAL_URL, 'sha256' => METADATA_OFFICIAL_SHA],
];
metadataAssertSame($expected, metadataFrom(metadataValues())->value(), 'Valid metadata contract was incorrect');
metadataAssertSame(1, $expected['format_version'], 'Metadata format version was not exactly integer 1');
metadataAssertSame(METADATA_SAMPLE_URL, $expected['sample']['url'], 'Sample URL mapping was incorrect');
metadataAssertSame(METADATA_OFFICIAL_URL, $expected['official']['url'], 'Official URL mapping was incorrect');

foreach ([
    'missing Sample URL' => array_diff_key(metadataValues(), ['FREETV_SAMPLE_DATA_URL' => true]),
    'missing Sample SHA' => array_diff_key(metadataValues(), ['FREETV_SAMPLE_DATA_SHA256' => true]),
    'missing Official URL' => array_diff_key(metadataValues(), ['FREETV_OFFICIAL_DATA_URL' => true]),
    'missing Official SHA' => array_diff_key(metadataValues(), ['FREETV_OFFICIAL_DATA_SHA256' => true]),
    'empty value' => metadataValues(['FREETV_SAMPLE_DATA_URL' => '']),
    'HTTP Sample URL' => metadataValues(['FREETV_SAMPLE_DATA_URL' => 'http://downloads.example.test/sample.zip']),
    'HTTP Official URL' => metadataValues(['FREETV_OFFICIAL_DATA_URL' => 'http://downloads.example.test/official.zip']),
    'malformed URL' => metadataValues(['FREETV_SAMPLE_DATA_URL' => 'not a URL']),
    'credential URL' => metadataValues(['FREETV_SAMPLE_DATA_URL' => 'https://secret@example.test/sample.zip']),
    'short SHA' => metadataValues(['FREETV_SAMPLE_DATA_SHA256' => str_repeat('a', 63)]),
    'long SHA' => metadataValues(['FREETV_SAMPLE_DATA_SHA256' => str_repeat('a', 65)]),
    'uppercase SHA' => metadataValues(['FREETV_SAMPLE_DATA_SHA256' => str_repeat('A', 64)]),
    'non-hex SHA' => metadataValues(['FREETV_OFFICIAL_DATA_SHA256' => str_repeat('z', 64)]),
] as $label => $values) {
    expectInvalidMetadata($values, "{$label} was accepted");
}

[, $validStatus, $validBody] = runMetadataEndpoint('GET', metadataValues());
metadataAssertSame(200, $validStatus, 'Public metadata endpoint did not return HTTP 200');
metadataAssertSame($expected, json_decode($validBody, true, 512, JSON_THROW_ON_ERROR),
    'Public unauthenticated metadata endpoint returned the wrong contract');

$invalidValue = 'http://do-not-expose.invalid/private-package.zip';
[, $invalidStatus, $invalidBody] = runMetadataEndpoint(
    'GET',
    metadataValues(['FREETV_SAMPLE_DATA_URL' => $invalidValue])
);
metadataAssertSame(503, $invalidStatus, 'Invalid runtime metadata did not return HTTP 503');
metadataAssertSame(
    ['success' => false, 'message' => 'Dataset package metadata is unavailable'],
    json_decode($invalidBody, true, 512, JSON_THROW_ON_ERROR),
    'Invalid runtime metadata did not return the stable failure response'
);
metadataAssertSame(false, str_contains($invalidBody, $invalidValue),
    'Failure response exposed the invalid runtime configuration value');

[, $methodStatus, $methodBody] = runMetadataEndpoint('POST', metadataValues());
metadataAssertSame(405, $methodStatus, 'Unsupported metadata method did not return HTTP 405');
metadataAssertSame(
    ['success' => false, 'message' => 'Method not allowed'],
    json_decode($methodBody, true, 512, JSON_THROW_ON_ERROR),
    'Unsupported metadata method returned the wrong response'
);

fwrite(STDOUT, "DatasetPackageMetadataTest passed\n");
