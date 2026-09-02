<?php

declare(strict_types=1);

require_once __DIR__ . '/../public/api/admin/DatasetPackage.php';
require_once __DIR__ . '/../public/api/admin/PackageBootstrapContracts.php';
require_once __DIR__ . '/../public/api/admin/DatasetPackageValidator.php';
require_once __DIR__ . '/../public/api/admin/DatasetPackageProvider.php';

use FreeTV\Admin\DatasetPackage;
use FreeTV\Admin\DatasetPackageProvider;
use FreeTV\Admin\DatasetPackageValidator;

function datasetFixturePayload(): array
{
    return [
        'database.sql' => '-- fixture SQL',
        'config.json' => json_encode([
            'lastupdated' => '2026-09-02T12:00:00.000Z', 'show_ads' => false,
        ], JSON_THROW_ON_ERROR),
        'playlists/index.json' => json_encode([
            'default' => 'fixture.json',
            'playlists' => [[
                'filename' => 'fixture.json', 'dbtitle' => 'Fixture',
                'lastupdated' => '2026-09-02T12:00:00.000Z',
            ]],
        ], JSON_THROW_ON_ERROR),
        'playlists/fixture.json' => json_encode([
            'lastupdated' => '2026-09-02T12:00:00.000Z', 'dbtitle' => 'Fixture',
            'dbversion' => null, 'author' => null, 'email' => null, 'link' => null, 'shows' => [],
        ], JSON_THROW_ON_ERROR),
        'thumbs/tt0000001.jpg' => 'fixture thumbnail',
    ];
}

function datasetWriteZip(
    string $path,
    string $dataset = 'sample',
    int $format = 1,
    ?callable $manifestChange = null,
    array $extraEntries = [],
    array $omit = []
): void {
    $payload = array_diff_key(datasetFixturePayload(), array_fill_keys($omit, true));
    $manifestFiles = [];
    foreach ($payload as $name => $contents) {
        $manifestFiles[$name] = hash('sha256', $contents);
    }
    $manifest = [
        'format_version' => $format,
        'dataset' => $dataset,
        'generated_at' => '2026-09-02T12:00:00.000Z',
        'files' => $manifestFiles,
    ];
    if ($manifestChange !== null) {
        $manifestChange($manifest);
    }

    $zip = new ZipArchive();
    if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        throw new RuntimeException('Could not create dataset fixture ZIP');
    }
    $zip->addFromString('manifest.json', json_encode($manifest, JSON_THROW_ON_ERROR));
    $zip->addEmptyDir('playlists');
    $zip->addEmptyDir('thumbs');
    foreach ($payload as $name => $contents) {
        $zip->addFromString($name, $contents);
    }
    foreach ($extraEntries as $name => $contents) {
        $zip->addFromString($name, $contents);
    }
    $zip->close();
}

function datasetExpectFailure(string $label, callable $fixture, string $expectedMessage): void
{
    $root = sys_get_temp_dir() . '/freetv-dataset-validator-' . bin2hex(random_bytes(6));
    mkdir($root, 0700);
    $zipPath = $root . '/fixture.zip';
    try {
        $fixture($zipPath);
        try {
            (new DatasetPackageValidator())->extractAndValidate($zipPath, $root . '/extract', 'sample');
            throw new RuntimeException("{$label} was accepted");
        } catch (RuntimeException $exception) {
            if (!str_contains($exception->getMessage(), $expectedMessage)) {
                throw new RuntimeException("{$label} produced unexpected error: " . $exception->getMessage());
            }
        }
    } finally {
        DatasetPackage::removeTree($root);
    }
}

function datasetProviderDefinitions(string $sampleHash, ?string $officialHash = null): array
{
    return [
        'sample' => [
            'url' => 'https://fixtures.invalid/freetv-sample-data.zip',
            'sha256' => $sampleHash,
        ],
        'official' => [
            'url' => 'https://fixtures.invalid/freetv-official-data.zip',
            'sha256' => $officialHash ?? $sampleHash,
        ],
    ];
}

$root = sys_get_temp_dir() . '/freetv-dataset-valid-' . bin2hex(random_bytes(6));
mkdir($root, 0700);
try {
    datasetWriteZip($root . '/fixture.zip');
    $files = (new DatasetPackageValidator())->extractAndValidate(
        $root . '/fixture.zip',
        $root . '/extract',
        'sample'
    );
    if (count($files) !== 5 || !is_dir($root . '/extract/thumbs')) {
        throw new RuntimeException('Valid dataset package did not extract completely');
    }
} finally {
    DatasetPackage::removeTree($root);
}

datasetExpectFailure('wrong dataset', fn($zip) => datasetWriteZip($zip, 'official'), 'required format');
datasetExpectFailure('unsupported format', fn($zip) => datasetWriteZip($zip, 'sample', 2), 'required format');
datasetExpectFailure('invalid generated_at',
    fn($zip) => datasetWriteZip($zip, manifestChange: static function (&$manifest): void {
        $manifest['generated_at'] = '2026-02-30T25:00:00Z';
    }), 'required format');
datasetExpectFailure('missing database.sql',
    fn($zip) => datasetWriteZip($zip, omit: ['database.sql']), 'missing required database.sql');
datasetExpectFailure('missing manifest', static function ($zipPath): void {
    $zip = new ZipArchive();
    $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $zip->addEmptyDir('playlists');
    $zip->addEmptyDir('thumbs');
    foreach (datasetFixturePayload() as $name => $contents) {
        $zip->addFromString($name, $contents);
    }
    $zip->close();
}, 'missing required manifest.json');
datasetExpectFailure('missing thumbs directory', static function ($zipPath): void {
    datasetWriteZip($zipPath);
    $zip = new ZipArchive();
    $zip->open($zipPath);
    $zip->deleteName('thumbs/');
    $zip->close();
}, 'missing required thumbs/ directory');
datasetExpectFailure('manifest missing payload',
    fn($zip) => datasetWriteZip($zip, manifestChange: static function (&$manifest): void {
        unset($manifest['files']['playlists/fixture.json']);
    }), 'inventory does not exactly match');
datasetExpectFailure('unmanifested extra file',
    fn($zip) => datasetWriteZip($zip, extraEntries: ['playlists/extra.json' => '{}']),
    'inventory does not exactly match');
datasetExpectFailure('bad SHA-256',
    fn($zip) => datasetWriteZip($zip, manifestChange: static function (&$manifest): void {
        $manifest['files']['config.json'] = str_repeat('0', 64);
    }), 'SHA-256 mismatch');
datasetExpectFailure('path traversal',
    fn($zip) => datasetWriteZip($zip, extraEntries: ['../outside.json' => '{}']), 'unsafe archive path');
datasetExpectFailure('absolute path',
    fn($zip) => datasetWriteZip($zip, extraEntries: ['/outside.json' => '{}']), 'unsafe archive path');
datasetExpectFailure('case-conflicting paths',
    fn($zip) => datasetWriteZip($zip, extraEntries: [
        'thumbs/CONFLICT.jpg' => 'one',
        'thumbs/conflict.jpg' => 'two',
    ]), 'duplicate or conflicting paths');

datasetExpectFailure('symlink entry', static function ($zipPath): void {
    datasetWriteZip($zipPath);
    $zip = new ZipArchive();
    $zip->open($zipPath);
    $zip->addFromString('thumbs/link.jpg', 'target');
    $zip->setExternalAttributesName('thumbs/link.jpg', ZipArchive::OPSYS_UNIX, 0120777 << 16);
    $zip->close();
}, 'unsafe entry type');

$root = sys_get_temp_dir() . '/freetv-dataset-provider-' . bin2hex(random_bytes(6));
mkdir($root, 0700);
$fixtureZip = $root . '/fixture.zip';
datasetWriteZip($fixtureZip);
$fixtureHash = hash_file('sha256', $fixtureZip);
$provider = new DatasetPackageProvider(
    $root . '/temp',
    new DatasetPackageValidator(),
    static fn(string $url, string $destination): bool => copy($fixtureZip, $destination),
    datasetProviderDefinitions($fixtureHash)
);
$package = $provider->acquire('sample');
$workspace = dirname($package->root());
if (!is_file($package->path('config.json'))) {
    throw new RuntimeException('Provider did not return validated package');
}
$package->cleanup();
if (file_exists($workspace)) {
    throw new RuntimeException('Validated package workspace was not cleaned');
}

$validationCalls = 0;
$mismatchedProvider = new DatasetPackageProvider(
    $root . '/temp',
    new DatasetPackageValidator(),
    static fn(string $url, string $destination): bool => copy($fixtureZip, $destination),
    datasetProviderDefinitions(str_repeat('0', 64)),
    static function (string $zipPath, string $extractionRoot, string $dataset) use (&$validationCalls): array {
        $validationCalls++;
        throw new RuntimeException('Package validation must not run after an archive hash mismatch');
    }
);
try {
    $mismatchedProvider->acquire('sample');
    throw new RuntimeException('Mismatched dataset archive SHA-256 was accepted');
} catch (RuntimeException $exception) {
    if (!str_contains($exception->getMessage(), 'does not match the pinned asset')) {
        throw $exception;
    }
}
if ($validationCalls !== 0) {
    throw new RuntimeException('Archive mismatch proceeded to package extraction');
}
$leftovers = glob($root . '/temp/bootstrap-packages/package-*');
if ($leftovers !== []) {
    throw new RuntimeException('Archive hash mismatch left a temporary workspace');
}

$invalidZip = $root . '/invalid.zip';
file_put_contents($invalidZip, 'not a zip');
$invalidZipHash = hash_file('sha256', $invalidZip);
$failingProvider = new DatasetPackageProvider(
    $root . '/temp',
    new DatasetPackageValidator(),
    static fn(string $url, string $destination): bool => copy($invalidZip, $destination),
    datasetProviderDefinitions($invalidZipHash)
);
try {
    $failingProvider->acquire('official');
    throw new RuntimeException('Invalid downloaded ZIP was accepted');
} catch (RuntimeException $exception) {
    if (!str_contains($exception->getMessage(), 'valid ZIP')) {
        throw $exception;
    }
}
$leftovers = glob($root . '/temp/bootstrap-packages/package-*');
if ($leftovers !== []) {
    throw new RuntimeException('Failed package acquisition left a temporary workspace');
}
DatasetPackage::removeTree($root);

fwrite(STDOUT, "DatasetPackageValidatorTest passed\n");
