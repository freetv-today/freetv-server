<?php

declare(strict_types=1);

namespace FreeTV\Admin;

require_once __DIR__ . '/PackageBootstrapContracts.php';
require_once __DIR__ . '/DatasetPackageExceptions.php';

final class DatasetPackageProvider implements DatasetPackageSource
{
    public const METADATA_URL = 'https://freetv.today/api/admin/dataset-package-metadata.php';

    private const DATASETS = ['sample', 'official'];
    private const MAX_METADATA_BYTES = 65536;
    private const MAX_DOWNLOAD_BYTES = 268435456;

    private $downloader;
    private $metadataFetcher;
    private $packageValidator;

    public function __construct(
        private string $tempRoot,
        private DatasetPackageValidator $validator,
        ?callable $downloader = null,
        ?callable $metadataFetcher = null,
        ?callable $packageValidator = null
    ) {
        $this->downloader = $downloader ?? fn(string $url, string $path) => $this->download($url, $path);
        $this->metadataFetcher = $metadataFetcher ?? fn(): string => $this->downloadMetadata();
        $this->packageValidator = $packageValidator
            ?? fn(string $zipPath, string $root, string $dataset): array =>
                $this->validator->extractAndValidate($zipPath, $root, $dataset);
    }

    public function acquire(string $dataset): DatasetPackage
    {
        if (!in_array($dataset, self::DATASETS, true)) {
            throw new \InvalidArgumentException('Unsupported initialization mode');
        }
        $definition = $this->packageDefinition($dataset);
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
        $workspace = $base . '/package-' . bin2hex(random_bytes(8));
        if (!mkdir($workspace, 0700)) {
            throw new \RuntimeException('Could not create private bootstrap package workspace');
        }

        try {
            $zipPath = $workspace . '/download.zip';
            try {
                ($this->downloader)($definition['url'], $zipPath);
            } catch (DatasetPackageAvailabilityException $exception) {
                throw $exception;
            } catch (\Throwable $exception) {
                throw new DatasetPackageAvailabilityException(
                    'Could not retrieve the current dataset package',
                    0,
                    $exception
                );
            }
            $archiveHash = is_file($zipPath) ? hash_file('sha256', $zipPath) : false;
            if (!is_string($archiveHash)) {
                throw new DatasetPackageIntegrityException('Could not verify the downloaded dataset archive');
            }
            if (!hash_equals($definition['sha256'], $archiveHash)) {
                throw new DatasetPackageIntegrityException(
                    'Downloaded dataset archive SHA-256 does not match the metadata asset'
                );
            }
            $root = $workspace . '/extracted';
            try {
                $files = ($this->packageValidator)($zipPath, $root, $dataset);
            } catch (\Throwable $exception) {
                throw new DatasetPackageIntegrityException(
                    'Downloaded dataset package validation failed: ' . $exception->getMessage(),
                    0,
                    $exception
                );
            }
            if (!unlink($zipPath)) {
                throw new \RuntimeException('Could not remove validated dataset download');
            }
            return new DatasetPackage($workspace, $root, $dataset, $files);
        } catch (\Throwable $exception) {
            try {
                DatasetPackage::removeTree($workspace);
            } catch (\Throwable $cleanupException) {
                error_log('Dataset package failure cleanup error: ' . $cleanupException->getMessage());
            }
            throw $exception;
        }
    }

    /** @return array{url: string, sha256: string} */
    private function packageDefinition(string $dataset): array
    {
        try {
            $json = ($this->metadataFetcher)();
        } catch (DatasetPackageMetadataAvailabilityException|DatasetPackageMetadataIntegrityException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            throw new DatasetPackageMetadataAvailabilityException(
                'Could not obtain current dataset package metadata',
                0,
                $exception
            );
        }
        if (!is_string($json)) {
            throw new DatasetPackageMetadataIntegrityException('Dataset package metadata response must be JSON text');
        }
        try {
            $metadata = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new DatasetPackageMetadataIntegrityException(
                'Dataset package metadata is invalid JSON',
                0,
                $exception
            );
        }
        $this->validateMetadata($metadata);
        return $metadata[$dataset];
    }

    private function validateMetadata(mixed $metadata): void
    {
        if (!is_array($metadata)
            || !$this->hasExactKeys($metadata, ['format_version', 'sample', 'official'])
            || $metadata['format_version'] !== 1) {
            throw new DatasetPackageMetadataIntegrityException(
                'Dataset package metadata root contract is invalid or unsupported'
            );
        }
        foreach (self::DATASETS as $dataset) {
            $definition = $metadata[$dataset];
            if (!is_array($definition) || !$this->hasExactKeys($definition, ['url', 'sha256'])) {
                throw new DatasetPackageMetadataIntegrityException(
                    "Dataset package metadata {$dataset} contract is invalid"
                );
            }
            if (!$this->isHttpsUrl($definition['url'] ?? null)) {
                throw new DatasetPackageMetadataIntegrityException(
                    "Dataset package metadata {$dataset} URL is invalid"
                );
            }
            if (!is_string($definition['sha256'])
                || preg_match('/^[a-f0-9]{64}$/D', $definition['sha256']) !== 1) {
                throw new DatasetPackageMetadataIntegrityException(
                    "Dataset package metadata {$dataset} SHA-256 is invalid"
                );
            }
        }
    }

    private function hasExactKeys(array $value, array $expected): bool
    {
        $keys = array_keys($value);
        sort($keys, SORT_STRING);
        sort($expected, SORT_STRING);
        return $keys === $expected;
    }

    private function isHttpsUrl(mixed $url): bool
    {
        if (!is_string($url) || filter_var($url, FILTER_VALIDATE_URL) === false) {
            return false;
        }
        $parts = parse_url($url);
        return is_array($parts)
            && strtolower((string) ($parts['scheme'] ?? '')) === 'https'
            && is_string($parts['host'] ?? null)
            && $parts['host'] !== ''
            && !isset($parts['user'])
            && !isset($parts['pass']);
    }

    private function downloadMetadata(): string
    {
        if (!function_exists('curl_init')) {
            throw new DatasetPackageMetadataAvailabilityException(
                'PHP cURL support is required to obtain dataset package metadata'
            );
        }
        $contents = '';
        $tooLarge = false;
        $curl = curl_init(self::METADATA_URL);
        if ($curl === false) {
            throw new DatasetPackageMetadataAvailabilityException(
                'Could not initialize the dataset package metadata request'
            );
        }
        curl_setopt_array($curl, [
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_USERAGENT => 'FreeTV-First-Run-Metadata/3.0',
            CURLOPT_FAILONERROR => false,
            CURLOPT_WRITEFUNCTION => static function ($handle, string $chunk) use (&$contents, &$tooLarge): int {
                if (strlen($contents) + strlen($chunk) > self::MAX_METADATA_BYTES) {
                    $tooLarge = true;
                    return 0;
                }
                $contents .= $chunk;
                return strlen($chunk);
            },
        ]);
        try {
            $success = curl_exec($curl);
            $httpStatus = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
            if ($tooLarge) {
                throw new DatasetPackageMetadataIntegrityException(
                    'Dataset package metadata response exceeds the size limit'
                );
            }
            if ($success !== true || $httpStatus !== 200 || $contents === '') {
                throw new DatasetPackageMetadataAvailabilityException(
                    'Dataset package metadata endpoint is unavailable'
                );
            }
        } finally {
            curl_close($curl);
        }
        return $contents;
    }

    private function download(string $url, string $destination): void
    {
        if (!function_exists('curl_init')) {
            throw new DatasetPackageAvailabilityException(
                'PHP cURL support is required to download initialization data'
            );
        }
        $target = fopen($destination, 'x+b');
        if (!is_resource($target)) {
            throw new \RuntimeException('Could not create the private dataset download file');
        }
        $bytes = 0;
        $curl = curl_init($url);
        if ($curl === false) {
            fclose($target);
            throw new DatasetPackageAvailabilityException('Could not initialize the dataset download');
        }
        curl_setopt_array($curl, [
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_CONNECTTIMEOUT => 20,
            CURLOPT_TIMEOUT => 600,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_USERAGENT => 'FreeTV-First-Run/3.0',
            CURLOPT_FAILONERROR => false,
            CURLOPT_WRITEFUNCTION => static function ($handle, string $chunk) use ($target, &$bytes): int {
                $length = strlen($chunk);
                if ($bytes + $length > self::MAX_DOWNLOAD_BYTES) {
                    return 0;
                }
                $written = fwrite($target, $chunk);
                if ($written === false) {
                    return 0;
                }
                $bytes += $written;
                return $written;
            },
        ]);

        try {
            $success = curl_exec($curl);
            $httpStatus = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
            if ($success !== true || $httpStatus !== 200 || $bytes === 0) {
                throw new DatasetPackageAvailabilityException(
                    'Dataset download failed or returned an incomplete response'
                );
            }
        } finally {
            curl_close($curl);
            fclose($target);
        }
        if (!chmod($destination, 0600)) {
            throw new \RuntimeException('Could not secure the downloaded dataset package');
        }
    }
}
