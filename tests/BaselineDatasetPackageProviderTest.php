<?php

declare(strict_types=1);

require_once __DIR__ . '/../public/api/admin/DatasetPackage.php';
require_once __DIR__ . '/../public/api/admin/PackageBootstrapContracts.php';
require_once __DIR__ . '/../public/api/admin/DatasetPackageValidator.php';
require_once __DIR__ . '/../public/api/admin/BaselineDatasetPackageProvider.php';

use FreeTV\Admin\BaselineDatasetPackageProvider;
use FreeTV\Admin\DatasetPackage;
use FreeTV\Admin\DatasetPackageIntegrityException;
use FreeTV\Admin\DatasetPackageValidator;

function baselineProviderAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function expectBaselineProviderException(string $class, callable $operation, string $message): Throwable
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

function baselineWorkspaces(string $tempRoot): array
{
    return glob($tempRoot . '/bootstrap-packages/baseline-*') ?: [];
}

$testRoot = sys_get_temp_dir() . '/freetv-baseline-provider-' . bin2hex(random_bytes(6));
$appRoot = $testRoot . '/app';
$tempRoot = $testRoot . '/temp';
mkdir($appRoot . '/resources', 0700, true);
mkdir($appRoot . '/public', 0700, true);
$packagePath = $appRoot . '/resources/freetv-baseline-sample-data.zip';

try {
    file_put_contents($packagePath, 'baseline package fixture');
    $validationCalls = [];
    $provider = new BaselineDatasetPackageProvider(
        $appRoot,
        $tempRoot,
        new DatasetPackageValidator(),
        static function (string $zipPath, string $root, string $dataset) use (&$validationCalls): array {
            $validationCalls[] = [$zipPath, $dataset];
            mkdir($root, 0700, true);
            file_put_contents($root . '/config.json', '{}');
            return ['config.json' => hash_file('sha256', $root . '/config.json')];
        }
    );
    $package = $provider->acquire('sample');
    baselineProviderAssert($validationCalls === [[$packagePath, 'sample']],
        'Baseline did not resolve resources/freetv-baseline-sample-data.zip with Sample validation semantics');
    baselineProviderAssert(!str_contains($validationCalls[0][0], '/public/'),
        'Baseline package resolved under the public web root');
    baselineProviderAssert($package->dataset() === 'sample',
        'Baseline acquisition invented a non-Sample package dataset');
    $workspace = dirname($package->root());
    baselineProviderAssert(is_dir($workspace), 'Baseline did not use a private extraction workspace');
    $package->cleanup();
    baselineProviderAssert(!file_exists($workspace), 'Baseline package cleanup retained its workspace');
    baselineProviderAssert(is_file($packagePath), 'Baseline cleanup removed the bundled package');

    expectBaselineProviderException(
        InvalidArgumentException::class,
        static fn() => $provider->acquire('baseline'),
        'Baseline provider accepted a non-Sample package contract'
    );
    baselineProviderAssert(count($validationCalls) === 1,
        'Unsupported Baseline dataset reached package validation');

    unlink($packagePath);
    expectBaselineProviderException(
        RuntimeException::class,
        static fn() => $provider->acquire('sample'),
        'Missing bundled Baseline package was accepted'
    );
    baselineProviderAssert(baselineWorkspaces($tempRoot) === [],
        'Missing bundled Baseline package created a workspace');

    $target = $testRoot . '/outside.zip';
    file_put_contents($target, 'unsafe symlink target');
    symlink($target, $packagePath);
    expectBaselineProviderException(
        DatasetPackageIntegrityException::class,
        static fn() => $provider->acquire('sample'),
        'Symlinked bundled Baseline package was accepted'
    );
    unlink($packagePath);

    mkdir($packagePath, 0700);
    expectBaselineProviderException(
        DatasetPackageIntegrityException::class,
        static fn() => $provider->acquire('sample'),
        'Non-file bundled Baseline package was accepted'
    );
    rmdir($packagePath);

    file_put_contents($packagePath, 'corrupt package fixture');
    $invalidProvider = new BaselineDatasetPackageProvider(
        $appRoot,
        $tempRoot,
        new DatasetPackageValidator(),
        static function (string $zipPath, string $root): array {
            mkdir($root, 0700, true);
            file_put_contents($root . '/partial', 'partial');
            throw new RuntimeException('injected invalid package');
        }
    );
    $invalid = expectBaselineProviderException(
        DatasetPackageIntegrityException::class,
        static fn() => $invalidProvider->acquire('sample'),
        'Invalid bundled Baseline package was not an integrity failure'
    );
    baselineProviderAssert($invalid->getPrevious() instanceof RuntimeException,
        'Invalid bundled Baseline package did not retain its validation cause');
    baselineProviderAssert(baselineWorkspaces($tempRoot) === [],
        'Invalid bundled Baseline package retained a partial workspace');

    $repositoryRoot = dirname(__DIR__);
    $realProvider = new BaselineDatasetPackageProvider(
        $repositoryRoot,
        $testRoot . '/real-temp',
        new DatasetPackageValidator()
    );
    $realPackage = $realProvider->acquire('sample');
    baselineProviderAssert($realPackage->dataset() === 'sample',
        'Committed Baseline ZIP did not validate as a Sample package');
    baselineProviderAssert(count($realPackage->files()) > 0,
        'Committed Baseline ZIP contained no validated payload files');
    $realWorkspace = dirname($realPackage->root());
    $realPackage->cleanup();
    baselineProviderAssert(!file_exists($realWorkspace),
        'Committed Baseline package workspace was not cleaned');

    $source = file_get_contents(__DIR__ . '/../public/api/admin/BaselineDatasetPackageProvider.php');
    baselineProviderAssert(!str_contains($source, 'curl') && !str_contains($source, 'METADATA_URL'),
        'Baseline provider contains a remote metadata or download path');
} finally {
    DatasetPackage::removeTree($testRoot);
}

fwrite(STDOUT, "BaselineDatasetPackageProviderTest passed\n");
