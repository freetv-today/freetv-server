<?php

declare(strict_types=1);

namespace FreeTV\Admin;

use JsonException;
use ZipArchive;

final class DatasetPackageValidator
{
    private const MAX_ENTRIES = 10000;
    private const MAX_UNCOMPRESSED_BYTES = 1073741824;

    public function extractAndValidate(string $zipPath, string $destination, string $expectedDataset): array
    {
        if (!in_array($expectedDataset, ['sample', 'official'], true)) {
            throw new \InvalidArgumentException('Unsupported dataset package type');
        }
        if (!mkdir($destination, 0700)) {
            throw new \RuntimeException('Could not create private dataset extraction directory');
        }

        $zip = new ZipArchive();
        $openResult = $zip->open($zipPath, ZipArchive::RDONLY);
        if ($openResult !== true) {
            throw new \RuntimeException('Downloaded dataset is not a valid ZIP archive');
        }

        $seen = [];
        $seenFolded = [];
        $directories = [];
        $files = [];
        $totalSize = 0;
        try {
            if ($zip->numFiles < 1 || $zip->numFiles > self::MAX_ENTRIES) {
                throw new \RuntimeException('Dataset ZIP has an unsupported number of entries');
            }
            for ($index = 0; $index < $zip->numFiles; $index++) {
                $stat = $zip->statIndex($index, ZipArchive::FL_ENC_RAW);
                $name = $zip->getNameIndex($index, ZipArchive::FL_ENC_RAW);
                if (!is_array($stat) || !is_string($name)) {
                    throw new \RuntimeException('Dataset ZIP contains an unreadable entry');
                }
                $isDirectory = str_ends_with($name, '/');
                $path = $this->validateArchivePath($name, $isDirectory);
                $folded = strtolower($path);
                if (isset($seen[$path]) || isset($seenFolded[$folded])) {
                    throw new \RuntimeException('Dataset ZIP contains duplicate or conflicting paths');
                }
                $seen[$path] = true;
                $seenFolded[$folded] = true;
                $this->rejectUnsafeType($zip, $index, $isDirectory);

                if ($isDirectory) {
                    $directories[$path] = true;
                    if (!mkdir($destination . '/' . $path, 0700) && !is_dir($destination . '/' . $path)) {
                        throw new \RuntimeException('Could not create an extracted dataset directory');
                    }
                    continue;
                }
                $size = isset($stat['size']) ? (int) $stat['size'] : -1;
                if ($size < 0 || $size > self::MAX_UNCOMPRESSED_BYTES
                    || $totalSize + $size > self::MAX_UNCOMPRESSED_BYTES) {
                    throw new \RuntimeException('Dataset ZIP exceeds the extraction size limit');
                }
                $totalSize += $size;
                $this->extractFile($zip, $index, $destination, $path, $size);
                $files[$path] = hash_file('sha256', $destination . '/' . $path);
            }
        } finally {
            $zip->close();
        }

        foreach (['playlists', 'thumbs'] as $requiredDirectory) {
            if (!isset($directories[$requiredDirectory])) {
                throw new \RuntimeException("Dataset ZIP is missing required {$requiredDirectory}/ directory");
            }
        }
        foreach (['manifest.json', 'database.sql', 'config.json', 'playlists/index.json'] as $requiredFile) {
            if (!isset($files[$requiredFile])) {
                throw new \RuntimeException("Dataset ZIP is missing required {$requiredFile}");
            }
        }

        $manifest = $this->readManifest($destination . '/manifest.json', $expectedDataset);
        $payloadFiles = $files;
        unset($payloadFiles['manifest.json']);
        ksort($payloadFiles, SORT_STRING);
        $manifestFiles = $manifest['files'];
        ksort($manifestFiles, SORT_STRING);
        if (array_keys($payloadFiles) !== array_keys($manifestFiles)) {
            throw new \RuntimeException('Dataset manifest inventory does not exactly match ZIP payload files');
        }
        foreach ($manifestFiles as $path => $expectedHash) {
            if (!hash_equals($expectedHash, $payloadFiles[$path])) {
                throw new \RuntimeException("Dataset payload SHA-256 mismatch for {$path}");
            }
        }

        $this->validateViewerInventory($destination, array_keys($payloadFiles));
        return $manifestFiles;
    }

    private function validateArchivePath(string $name, bool $isDirectory): string
    {
        if ($name === '' || str_contains($name, "\0") || str_contains($name, '\\')
            || str_starts_with($name, '/') || preg_match('/^[A-Za-z]:/', $name) === 1
            || preg_match('//u', $name) !== 1) {
            throw new \RuntimeException('Dataset ZIP contains an unsafe archive path');
        }
        $path = $isDirectory ? substr($name, 0, -1) : $name;
        $segments = explode('/', $path);
        foreach ($segments as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..'
                || preg_match('/^[A-Za-z0-9._-]+$/D', $segment) !== 1) {
                throw new \RuntimeException('Dataset ZIP contains an unsafe archive path');
            }
        }

        if ($isDirectory) {
            if (!in_array($path, ['playlists', 'thumbs'], true)) {
                throw new \RuntimeException('Dataset ZIP contains an unexpected directory');
            }
        } elseif (!in_array($path, ['manifest.json', 'database.sql', 'config.json'], true)
            && preg_match('/^playlists\/[A-Za-z0-9_-]+\.json$/D', $path) !== 1
            && preg_match('/^thumbs\/[A-Za-z0-9_-]+\.(?:jpg|jpeg|png|webp)$/Di', $path) !== 1) {
            throw new \RuntimeException('Dataset ZIP contains an unexpected payload path');
        }

        return $path;
    }

    private function rejectUnsafeType(ZipArchive $zip, int $index, bool $isDirectory): void
    {
        $operatingSystem = 0;
        $attributes = 0;
        if (!$zip->getExternalAttributesIndex($index, $operatingSystem, $attributes)) {
            return;
        }
        if ($operatingSystem !== ZipArchive::OPSYS_UNIX) {
            return;
        }
        $type = ($attributes >> 16) & 0170000;
        $expected = $isDirectory ? 0040000 : 0100000;
        if ($type !== 0 && $type !== $expected) {
            throw new \RuntimeException('Dataset ZIP contains an unsafe entry type');
        }
    }

    private function extractFile(ZipArchive $zip, int $index, string $destination, string $path, int $size): void
    {
        $source = $zip->getStreamIndex($index, ZipArchive::FL_ENC_RAW);
        if (!is_resource($source)) {
            throw new \RuntimeException('Could not read a dataset ZIP entry');
        }
        $targetPath = $destination . '/' . $path;
        $parent = dirname($targetPath);
        if (!is_dir($parent) && !mkdir($parent, 0700, true)) {
            fclose($source);
            throw new \RuntimeException('Could not create a dataset extraction directory');
        }
        $target = fopen($targetPath, 'x+b');
        if (!is_resource($target)) {
            fclose($source);
            throw new \RuntimeException('Could not create an extracted dataset file');
        }
        try {
            $copied = stream_copy_to_stream($source, $target, self::MAX_UNCOMPRESSED_BYTES + 1);
            if ($copied !== $size) {
                throw new \RuntimeException('Dataset ZIP entry was not extracted completely');
            }
        } finally {
            fclose($target);
            fclose($source);
        }
        if (!chmod($targetPath, 0600)) {
            throw new \RuntimeException('Could not secure an extracted dataset file');
        }
    }

    private function readManifest(string $path, string $expectedDataset): array
    {
        try {
            $manifest = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new \RuntimeException('Dataset manifest is invalid JSON', 0, $exception);
        }
        if (!is_array($manifest)
            || !$this->hasExactKeys($manifest, ['format_version', 'dataset', 'generated_at', 'files'])
            || $manifest['format_version'] !== 1
            || $manifest['dataset'] !== $expectedDataset
            || !is_string($manifest['generated_at'])
            || !$this->isUtcTimestamp($manifest['generated_at'])
            || !is_array($manifest['files'])
            || $manifest['files'] === []) {
            throw new \RuntimeException('Dataset manifest does not match the required format');
        }
        foreach ($manifest['files'] as $relativePath => $hash) {
            if (!is_string($relativePath) || $relativePath === 'manifest.json'
                || $this->validateArchivePath($relativePath, false) !== $relativePath
                || !is_string($hash) || preg_match('/^[a-f0-9]{64}$/D', $hash) !== 1) {
                throw new \RuntimeException('Dataset manifest contains an invalid file entry');
            }
        }
        return $manifest;
    }

    private function isUtcTimestamp(string $value): bool
    {
        if (preg_match(
            '/^(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2}):(\d{2})(?:\.\d+)?Z$/D',
            $value,
            $parts
        ) !== 1) {
            return false;
        }

        return checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1])
            && (int) $parts[4] <= 23
            && (int) $parts[5] <= 59
            && (int) $parts[6] <= 59;
    }

    private function validateViewerInventory(string $root, array $payloadPaths): void
    {
        try {
            $config = json_decode((string) file_get_contents($root . '/config.json'), true, 512, JSON_THROW_ON_ERROR);
            $index = json_decode(
                (string) file_get_contents($root . '/playlists/index.json'),
                true,
                512,
                JSON_THROW_ON_ERROR
            );
        } catch (JsonException $exception) {
            throw new \RuntimeException('Dataset contains invalid Viewer JSON', 0, $exception);
        }
        if (!is_array($config) || !$this->hasExactKeys($config, ['lastupdated', 'show_ads'])
            || !is_string($config['lastupdated']) || !is_bool($config['show_ads'])
            || !is_array($index) || !is_string($index['default'] ?? null)
            || !is_array($index['playlists'] ?? null)) {
            throw new \RuntimeException('Dataset Viewer artifacts do not match the current contract');
        }
        $indexed = [];
        foreach ($index['playlists'] as $entry) {
            if (!is_array($entry) || !is_string($entry['filename'] ?? null)) {
                throw new \RuntimeException('Dataset playlist index contains an invalid entry');
            }
            $playlistPath = 'playlists/' . $entry['filename'];
            if (isset($indexed[$playlistPath]) || !in_array($playlistPath, $payloadPaths, true)) {
                throw new \RuntimeException('Dataset playlist index does not match packaged playlists');
            }
            $indexed[$playlistPath] = true;
            try {
                $playlist = json_decode(
                    (string) file_get_contents($root . '/' . $playlistPath),
                    true,
                    512,
                    JSON_THROW_ON_ERROR
                );
            } catch (JsonException $exception) {
                throw new \RuntimeException('Dataset contains invalid playlist JSON', 0, $exception);
            }
            if (!is_array($playlist) || !is_array($playlist['shows'] ?? null)) {
                throw new \RuntimeException('Dataset playlist does not match the current Viewer contract');
            }
        }
        $packaged = array_fill_keys(array_filter(
            $payloadPaths,
            static fn(string $path): bool => str_starts_with($path, 'playlists/') && $path !== 'playlists/index.json'
        ), true);
        ksort($indexed, SORT_STRING);
        ksort($packaged, SORT_STRING);
        if (array_keys($indexed) !== array_keys($packaged)
            || !isset($indexed['playlists/' . $index['default']])) {
            throw new \RuntimeException('Dataset playlist inventory does not match its index');
        }
    }

    private function hasExactKeys(array $value, array $expected): bool
    {
        $keys = array_keys($value);
        sort($keys, SORT_STRING);
        sort($expected, SORT_STRING);
        return $keys === $expected;
    }
}
