<?php

declare(strict_types=1);

require_once __DIR__ . '/../public/api/admin/DatasetPackage.php';
require_once __DIR__ . '/../public/api/admin/PackageBootstrapContracts.php';
require_once __DIR__ . '/../public/api/admin/DatasetPackageValidator.php';
require_once __DIR__ . '/../public/api/admin/DatasetPackageProvider.php';

use FreeTV\Admin\DatasetPackage;
use FreeTV\Admin\DatasetPackageIntegrityException;
use FreeTV\Admin\DatasetPackageMetadataAvailabilityException;
use FreeTV\Admin\DatasetPackageMetadataIntegrityException;
use FreeTV\Admin\DatasetPackageProvider;
use FreeTV\Admin\DatasetPackageValidator;

function providerAssertSame(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message . "\nExpected: " . var_export($expected, true)
            . "\nActual: " . var_export($actual, true));
    }
}

function providerMetadata(array $changes = []): array
{
    $sampleBytes = 'sample archive fixture';
    $officialBytes = 'official archive fixture';
    return array_replace_recursive([
        'format_version' => 1,
        'sample' => [
            'url' => 'https://packages.example.test/freetv-sample-data.zip',
            'sha256' => hash('sha256', $sampleBytes),
        ],
        'official' => [
            'url' => 'https://packages.example.test/freetv-official-data.zip',
            'sha256' => hash('sha256', $officialBytes),
        ],
    ], $changes);
}

function providerMetadataJson(array $metadata): string
{
    return json_encode($metadata, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
}

function expectProviderException(string $class, callable $operation, string $message): Throwable
{
    try {
        $operation();
    } catch (Throwable $exception) {
        if ($exception instanceof $class) {
            return $exception;
        }
        throw new RuntimeException(
            $message . ': expected ' . $class . ', got ' . $exception::class . ': ' . $exception->getMessage()
        );
    }
    throw new RuntimeException($message . ': no exception was thrown');
}

function providerWorkspaceDirectories(string $tempRoot): array
{
    return glob($tempRoot . '/bootstrap-packages/package-*') ?: [];
}

$testRoot = sys_get_temp_dir() . '/freetv-package-provider-' . bin2hex(random_bytes(6));
mkdir($testRoot, 0700);
try {
    $downloads = [];
    $validationCalls = [];
    $bytesByUrl = [
        'https://packages.example.test/freetv-sample-data.zip' => 'sample archive fixture',
        'https://packages.example.test/freetv-official-data.zip' => 'official archive fixture',
    ];
    $provider = new DatasetPackageProvider(
        $testRoot . '/success',
        new DatasetPackageValidator(),
        static function (string $url, string $destination) use (&$downloads, $bytesByUrl): void {
            $downloads[] = $url;
            file_put_contents($destination, $bytesByUrl[$url]);
        },
        static fn(): string => providerMetadataJson(providerMetadata()),
        static function (string $zipPath, string $root, string $dataset) use (&$validationCalls): array {
            $validationCalls[] = [$dataset, is_file($zipPath)];
            mkdir($root, 0700, true);
            file_put_contents($root . '/config.json', '{}');
            return ['config.json' => hash_file('sha256', $root . '/config.json')];
        }
    );

    foreach (['sample', 'official'] as $dataset) {
        $package = $provider->acquire($dataset);
        $expectedUrl = providerMetadata()[$dataset]['url'];
        providerAssertSame($expectedUrl, array_shift($downloads), "{$dataset} used the wrong metadata URL");
        providerAssertSame([$dataset, true], array_shift($validationCalls),
            "{$dataset} did not reach validation after its download");
        $workspace = dirname($package->root());
        providerAssertSame(false, is_file($workspace . '/download.zip'),
            "{$dataset} successful acquisition retained its downloaded ZIP");
        $package->cleanup();
        providerAssertSame(false, file_exists($workspace), "{$dataset} package cleanup retained its workspace");
    }

    $networkCalls = 0;
    $downloadCalls = 0;
    $unsupported = new DatasetPackageProvider(
        $testRoot . '/unsupported',
        new DatasetPackageValidator(),
        static function () use (&$downloadCalls): void {
            $downloadCalls++;
        },
        static function () use (&$networkCalls): string {
            $networkCalls++;
            return providerMetadataJson(providerMetadata());
        }
    );
    expectProviderException(InvalidArgumentException::class, static fn() => $unsupported->acquire('baseline'),
        'Unsupported mode was accepted');
    providerAssertSame(0, $networkCalls, 'Unsupported mode fetched metadata');
    providerAssertSame(0, $downloadCalls, 'Unsupported mode downloaded a package');

    $invalidDocuments = [
        'malformed JSON' => '{invalid',
        'unsupported format' => providerMetadataJson(providerMetadata(['format_version' => 2])),
        'unexpected root field' => providerMetadataJson(providerMetadata(['extra' => true])),
        'missing Sample' => providerMetadataJson(array_diff_key(providerMetadata(), ['sample' => true])),
        'missing Official' => providerMetadataJson(array_diff_key(providerMetadata(), ['official' => true])),
        'unexpected dataset field' => providerMetadataJson(providerMetadata([
            'sample' => ['extra' => true],
        ])),
        'malformed URL' => providerMetadataJson(providerMetadata([
            'sample' => ['url' => 'not a URL'],
        ])),
        'HTTP URL' => providerMetadataJson(providerMetadata([
            'sample' => ['url' => 'http://packages.example.test/sample.zip'],
        ])),
        'credential URL' => providerMetadataJson(providerMetadata([
            'official' => ['url' => 'https://secret@packages.example.test/official.zip'],
        ])),
        'malformed SHA' => providerMetadataJson(providerMetadata([
            'official' => ['sha256' => str_repeat('A', 64)],
        ])),
    ];
    foreach ($invalidDocuments as $label => $document) {
        $downloadCalls = 0;
        $invalidProvider = new DatasetPackageProvider(
            $testRoot . '/invalid-' . md5($label),
            new DatasetPackageValidator(),
            static function () use (&$downloadCalls): void {
                $downloadCalls++;
            },
            static fn(): string => $document
        );
        $requested = $label === 'missing Official' ? 'official' : 'sample';
        expectProviderException(
            DatasetPackageMetadataIntegrityException::class,
            static fn() => $invalidProvider->acquire($requested),
            "{$label} metadata was accepted"
        );
        providerAssertSame(0, $downloadCalls, "{$label} metadata reached package download");
    }

    $availabilityProvider = new DatasetPackageProvider(
        $testRoot . '/availability',
        new DatasetPackageValidator(),
        static function (): void {
            throw new RuntimeException('Package download must not run');
        },
        static function (): string {
            throw new RuntimeException('injected DNS failure');
        }
    );
    $availability = expectProviderException(
        DatasetPackageMetadataAvailabilityException::class,
        static fn() => $availabilityProvider->acquire('sample'),
        'Metadata availability failure was not classified'
    );
    providerAssertSame(true, $availability->getPrevious() instanceof RuntimeException,
        'Metadata availability failure did not retain its cause');
    providerAssertSame([], providerWorkspaceDirectories($testRoot . '/availability'),
        'Metadata availability failure created a private workspace');

    $integrityProvider = new DatasetPackageProvider(
        $testRoot . '/integrity',
        new DatasetPackageValidator(),
        static function (): void {
            throw new RuntimeException('Package download must not run');
        },
        static fn(): string => '{invalid'
    );
    $metadataIntegrity = expectProviderException(
        DatasetPackageMetadataIntegrityException::class,
        static fn() => $integrityProvider->acquire('sample'),
        'Metadata integrity failure was not classified'
    );
    providerAssertSame(false, $metadataIntegrity instanceof DatasetPackageMetadataAvailabilityException,
        'Metadata integrity failure was misclassified as availability');
    providerAssertSame([], providerWorkspaceDirectories($testRoot . '/integrity'),
        'Metadata integrity failure created a private workspace');

    $hashMismatchRoot = $testRoot . '/hash-mismatch';
    $validatorCalls = 0;
    $hashMismatch = new DatasetPackageProvider(
        $hashMismatchRoot,
        new DatasetPackageValidator(),
        static fn(string $url, string $destination): int => file_put_contents($destination, 'wrong bytes'),
        static fn(): string => providerMetadataJson(providerMetadata()),
        static function () use (&$validatorCalls): array {
            $validatorCalls++;
            return [];
        }
    );
    expectProviderException(DatasetPackageIntegrityException::class,
        static fn() => $hashMismatch->acquire('sample'), 'ZIP hash mismatch was not a hard integrity failure');
    providerAssertSame(0, $validatorCalls, 'ZIP hash mismatch reached package extraction/validation');
    providerAssertSame([], providerWorkspaceDirectories($hashMismatchRoot),
        'ZIP hash mismatch retained a private workspace');

    $validationFailureRoot = $testRoot . '/validation-failure';
    $validationFailure = new DatasetPackageProvider(
        $validationFailureRoot,
        new DatasetPackageValidator(),
        static fn(string $url, string $destination): int =>
            file_put_contents($destination, 'sample archive fixture'),
        static fn(): string => providerMetadataJson(providerMetadata()),
        static function (): array {
            throw new RuntimeException('injected package manifest failure');
        }
    );
    $packageIntegrity = expectProviderException(
        DatasetPackageIntegrityException::class,
        static fn() => $validationFailure->acquire('sample'),
        'Package validation failure was not a hard integrity failure'
    );
    providerAssertSame(true, $packageIntegrity->getPrevious() instanceof RuntimeException,
        'Package validation failure did not retain its cause');
    providerAssertSame([], providerWorkspaceDirectories($validationFailureRoot),
        'Package validation failure retained a private workspace');

    $providerSource = file_get_contents(__DIR__ . '/../public/api/admin/DatasetPackageProvider.php');
    providerAssertSame(false, str_contains($providerSource, 'v3.0.0-data-preview'),
        'Provider still contains the preview release URL');
    providerAssertSame(false, str_contains(
        $providerSource,
        'f7faca5c456b417ba643c8dfcf3b5d56a322557586fe00409c1d7512b67e9026'
    ), 'Provider still contains the preview Sample archive hash');
    providerAssertSame(false, str_contains(
        $providerSource,
        '82c8bb5e05d6325c249948db667ed553b3fe9cad803f0fe044fddef7fdca3f45'
    ), 'Provider still contains the preview Official archive hash');
    providerAssertSame(
        'https://freetv.today/api/admin/dataset-package-metadata.php',
        DatasetPackageProvider::METADATA_URL,
        'Provider metadata trust-source URL is incorrect'
    );
} finally {
    DatasetPackage::removeTree($testRoot);
}

fwrite(STDOUT, "DatasetPackageProviderTest passed\n");
