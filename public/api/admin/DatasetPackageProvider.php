<?php

declare(strict_types=1);

namespace FreeTV\Admin;

require_once __DIR__ . '/PackageBootstrapContracts.php';

final class DatasetPackageProvider implements DatasetPackageSource
{
    private const PACKAGES = [
        'sample' => [
            'url' => 'https://github.com/freetv-today/freetv-data/releases/download/'
                . 'v3.0.0-data-preview/freetv-sample-data.zip',
            'sha256' => 'f7faca5c456b417ba643c8dfcf3b5d56a322557586fe00409c1d7512b67e9026',
        ],
        'official' => [
            'url' => 'https://github.com/freetv-today/freetv-data/releases/download/'
                . 'v3.0.0-data-preview/freetv-official-data.zip',
            'sha256' => '82c8bb5e05d6325c249948db667ed553b3fe9cad803f0fe044fddef7fdca3f45',
        ],
    ];
    private const MAX_DOWNLOAD_BYTES = 268435456;

    private $downloader;
    private $packageValidator;
    private array $packages;

    public function __construct(
        private string $tempRoot,
        private DatasetPackageValidator $validator,
        ?callable $downloader = null,
        ?array $packages = null,
        ?callable $packageValidator = null
    ) {
        $this->downloader = $downloader ?? fn(string $url, string $path) => $this->download($url, $path);
        $this->packages = $packages ?? self::PACKAGES;
        $this->packageValidator = $packageValidator
            ?? fn(string $zipPath, string $root, string $dataset): array =>
                $this->validator->extractAndValidate($zipPath, $root, $dataset);
    }

    public function acquire(string $dataset): DatasetPackage
    {
        if (!isset($this->packages[$dataset])) {
            throw new \InvalidArgumentException('Unsupported initialization mode');
        }
        $definition = $this->packages[$dataset];
        if (!is_array($definition)
            || array_keys($definition) !== ['url', 'sha256']
            || !is_string($definition['url'])
            || !str_starts_with($definition['url'], 'https://')
            || !is_string($definition['sha256'])
            || preg_match('/^[a-f0-9]{64}$/D', $definition['sha256']) !== 1) {
            throw new \LogicException('Dataset package definition is invalid');
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
        $workspace = $base . '/package-' . bin2hex(random_bytes(8));
        if (!mkdir($workspace, 0700)) {
            throw new \RuntimeException('Could not create private bootstrap package workspace');
        }

        try {
            $zipPath = $workspace . '/download.zip';
            ($this->downloader)($definition['url'], $zipPath);
            $archiveHash = is_file($zipPath) ? hash_file('sha256', $zipPath) : false;
            if (!is_string($archiveHash)) {
                throw new \RuntimeException('Could not verify the downloaded dataset archive');
            }
            if (!hash_equals($definition['sha256'], $archiveHash)) {
                throw new \RuntimeException('Downloaded dataset archive SHA-256 does not match the pinned asset');
            }
            $root = $workspace . '/extracted';
            $files = ($this->packageValidator)($zipPath, $root, $dataset);
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

    private function download(string $url, string $destination): void
    {
        if (!function_exists('curl_init')) {
            throw new \RuntimeException('PHP cURL support is required to download initialization data');
        }
        $target = fopen($destination, 'x+b');
        if (!is_resource($target)) {
            throw new \RuntimeException('Could not create the private dataset download file');
        }
        $bytes = 0;
        $curl = curl_init($url);
        if ($curl === false) {
            fclose($target);
            throw new \RuntimeException('Could not initialize the dataset download');
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
                throw new \RuntimeException('Dataset download failed or returned an incomplete response');
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
