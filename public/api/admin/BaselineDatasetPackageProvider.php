<?php

declare(strict_types=1);

namespace FreeTV\Admin;

require_once __DIR__ . '/PackageBootstrapContracts.php';
require_once __DIR__ . '/DatasetPackageExceptions.php';

final class BaselineDatasetPackageProvider implements DatasetPackageSource
{
    public const PACKAGE_RELATIVE_PATH = 'resources/freetv-baseline-sample-data.zip';

    private $packageValidator;

    public function __construct(
        private string $appRoot,
        private string $tempRoot,
        private DatasetPackageValidator $validator,
        ?callable $packageValidator = null
    ) {
        $this->packageValidator = $packageValidator
            ?? fn(string $zipPath, string $root, string $dataset): array =>
                $this->validator->extractAndValidate($zipPath, $root, $dataset);
    }

    public function acquire(string $dataset): DatasetPackage
    {
        if ($dataset !== 'sample') {
            throw new \InvalidArgumentException('Baseline must use the Sample dataset contract');
        }

        $packagePath = rtrim($this->appRoot, DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR
            . str_replace('/', DIRECTORY_SEPARATOR, self::PACKAGE_RELATIVE_PATH);
        if (is_link($packagePath)) {
            throw new DatasetPackageIntegrityException('Bundled Baseline package has an unsafe filesystem type');
        }
        if (!file_exists($packagePath)) {
            throw new \RuntimeException('Bundled Baseline Sample package is missing');
        }
        if (!is_file($packagePath)) {
            throw new DatasetPackageIntegrityException('Bundled Baseline package has an unsafe filesystem type');
        }

        $base = rtrim($this->tempRoot, DIRECTORY_SEPARATOR) . '/bootstrap-packages';
        if (is_link($base) || (file_exists($base) && !is_dir($base))) {
            throw new \RuntimeException('Bootstrap package storage has an unsafe type');
        }
        if (!is_dir($base) && !mkdir($base, 0700, true) && !is_dir($base)) {
            throw new \RuntimeException('Could not create private bootstrap package storage');
        }
        if (!chmod($base, 0700)) {
            throw new \RuntimeException('Could not secure private bootstrap package storage');
        }
        $workspace = $base . '/baseline-' . bin2hex(random_bytes(8));
        if (!mkdir($workspace, 0700)) {
            throw new \RuntimeException('Could not create private Baseline package workspace');
        }

        try {
            $root = $workspace . '/extracted';
            try {
                $files = ($this->packageValidator)($packagePath, $root, 'sample');
            } catch (\Throwable $exception) {
                throw new DatasetPackageIntegrityException(
                    'Bundled Baseline Sample package validation failed: ' . $exception->getMessage(),
                    0,
                    $exception
                );
            }
            return new DatasetPackage($workspace, $root, 'sample', $files);
        } catch (\Throwable $exception) {
            try {
                DatasetPackage::removeTree($workspace);
            } catch (\Throwable $cleanupException) {
                error_log('Baseline package failure cleanup error: ' . $cleanupException->getMessage());
            }
            throw $exception;
        }
    }
}
