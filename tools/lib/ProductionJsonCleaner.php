<?php

declare(strict_types=1);

namespace FreeTV\Tools;

use JsonException;
use RuntimeException;
use Throwable;

final class ProductionJsonCleaner
{
    public const PLAYLIST_FILES = [
        'freetv.json',
        'ftv-british.json',
        'ftv-holidays.json',
        'ftv-movies.json',
    ];

    /** @return array{playlists: array<string, array>, audit: array<string, array>, report: array<string, array>} */
    public function validate(string $sourceDirectory): array
    {
        $sourceDirectory = $this->existingDirectory($sourceDirectory);
        $playlists = [];
        $knownPairs = [];

        foreach (self::PLAYLIST_FILES as $filename) {
            $path = $sourceDirectory . DIRECTORY_SEPARATOR . $filename;
            $playlist = $this->decodeFile($path);
            if (!array_key_exists('shows', $playlist)
                || !is_array($playlist['shows'])
                || !array_is_list($playlist['shows'])) {
                throw new RuntimeException("{$filename}: shows must be an array");
            }

            foreach ($playlist['shows'] as $position => $show) {
                if (!is_array($show)) {
                    throw new RuntimeException("{$filename}: show at position {$position} must be an object");
                }
                $identifier = $show['identifier'] ?? null;
                if (!is_string($identifier) || trim($identifier) === '') {
                    throw new RuntimeException("{$filename}: show at position {$position} has no non-empty identifier");
                }
                $key = self::pairKey($filename, $identifier);
                if (isset($knownPairs[$key])) {
                    throw new RuntimeException("{$filename}: duplicate/ambiguous identifier {$identifier}");
                }
                $knownPairs[$key] = true;
            }
            $playlists[$filename] = $playlist;
        }

        $auditDocument = $this->decodeFile($sourceDirectory . DIRECTORY_SEPARATOR . 'results.json');
        if (!isset($auditDocument['results'])
            || !is_array($auditDocument['results'])
            || !array_is_list($auditDocument['results'])) {
            throw new RuntimeException('results.json: results must be an array');
        }

        $audit = [];
        foreach ($auditDocument['results'] as $position => $result) {
            if (!is_array($result)) {
                throw new RuntimeException("results.json: result at position {$position} must be an object");
            }
            $playlist = $result['playlist'] ?? null;
            $identifier = $result['identifier'] ?? null;
            $isDark = $result['is_dark'] ?? null;
            if (!is_string($playlist) || trim($playlist) === '') {
                throw new RuntimeException("results.json: result at position {$position} has no playlist");
            }
            if (!is_string($identifier) || trim($identifier) === '') {
                throw new RuntimeException("results.json: result at position {$position} has no identifier");
            }
            if (!is_bool($isDark)) {
                throw new RuntimeException("results.json: result at position {$position} has non-boolean is_dark");
            }
            $key = self::pairKey($playlist, $identifier);
            if (isset($audit[$key])) {
                throw new RuntimeException("results.json: duplicate audit pair {$playlist} / {$identifier}");
            }
            if (!isset($knownPairs[$key])) {
                throw new RuntimeException("results.json: unknown audit pair {$playlist} / {$identifier}");
            }
            $audit[$key] = $result;
        }

        foreach ($knownPairs as $key => $_) {
            if (!isset($audit[$key])) {
                [$playlist, $identifier] = explode("\0", $key, 2);
                throw new RuntimeException("Missing audit pair {$playlist} / {$identifier}");
            }
        }

        $report = [];
        foreach ($playlists as $filename => $playlist) {
            $removed = [];
            foreach ($playlist['shows'] as $show) {
                if ($audit[self::pairKey($filename, $show['identifier'])]['is_dark']) {
                    $removed[] = $show['identifier'];
                }
            }
            $report[$filename] = [
                'original' => count($playlist['shows']),
                'removed' => count($removed),
                'resulting' => count($playlist['shows']) - count($removed),
                'identifiers' => $removed,
            ];
        }

        return ['playlists' => $playlists, 'audit' => $audit, 'report' => $report];
    }

    public function write(string $sourceDirectory, ?string $outputDirectory = null): string
    {
        $sourceDirectory = $this->existingDirectory($sourceDirectory);
        $validated = $this->validate($sourceDirectory);
        $outputDirectory = $outputDirectory ?? $sourceDirectory . DIRECTORY_SEPARATOR . 'cleaned';
        $this->assertSafeOutput($sourceDirectory, $outputDirectory);

        if (file_exists($outputDirectory)) {
            throw new RuntimeException("Refusing to overwrite existing output: {$outputDirectory}");
        }

        $stage = dirname($outputDirectory) . DIRECTORY_SEPARATOR . '.' . basename($outputDirectory)
            . '.tmp-' . getmypid() . '-' . bin2hex(random_bytes(4));
        if (!mkdir($stage, 0755)) {
            throw new RuntimeException("Could not create staging directory: {$stage}");
        }

        try {
            foreach ($validated['playlists'] as $filename => $playlist) {
                $playlist['shows'] = array_values(array_filter(
                    $playlist['shows'],
                    fn(array $show): bool => !$validated['audit'][self::pairKey($filename, $show['identifier'])]['is_dark']
                ));
                $this->writeJson($stage . DIRECTORY_SEPARATOR . $filename, $playlist);
            }
            if (!copy($sourceDirectory . DIRECTORY_SEPARATOR . 'results.json', $stage . DIRECTORY_SEPARATOR . 'results.json')) {
                throw new RuntimeException('Could not copy results.json into staged output');
            }
            if (!rename($stage, $outputDirectory)) {
                throw new RuntimeException("Could not publish cleaned directory: {$outputDirectory}");
            }
        } catch (Throwable $exception) {
            $this->removeDirectory($stage);
            throw $exception;
        }

        return $outputDirectory;
    }

    private function existingDirectory(string $directory): string
    {
        $resolved = realpath($directory);
        if ($resolved === false || !is_dir($resolved)) {
            throw new RuntimeException("Data directory does not exist: {$directory}");
        }
        return rtrim($resolved, DIRECTORY_SEPARATOR);
    }

    /** @return array<string, mixed> */
    private function decodeFile(string $path): array
    {
        if (!is_file($path)) {
            throw new RuntimeException("Missing required file: {$path}");
        }
        $contents = file_get_contents($path);
        if ($contents === false) {
            throw new RuntimeException("Could not read file: {$path}");
        }
        try {
            $decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException("Invalid JSON in {$path}: {$exception->getMessage()}");
        }
        if (!is_array($decoded)) {
            throw new RuntimeException("JSON root must be an object: {$path}");
        }
        return $decoded;
    }

    private function assertSafeOutput(string $sourceDirectory, string $outputDirectory): void
    {
        if ($outputDirectory === '' || !str_starts_with($outputDirectory, DIRECTORY_SEPARATOR)) {
            throw new RuntimeException('Output directory must be an absolute path');
        }
        $parent = realpath(dirname($outputDirectory));
        if ($parent !== $sourceDirectory || in_array(basename($outputDirectory), ['', '.', '..'], true)) {
            throw new RuntimeException('Output directory must be a new direct child of the source directory');
        }
    }

    /** @param array<string, mixed> $value */
    private function writeJson(string $path, array $value): void
    {
        $json = json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        if (file_put_contents($path, $json . "\n", LOCK_EX) === false) {
            throw new RuntimeException("Could not write {$path}");
        }
    }

    private function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }
        foreach (scandir($directory) ?: [] as $entry) {
            if ($entry !== '.' && $entry !== '..') {
                @unlink($directory . DIRECTORY_SEPARATOR . $entry);
            }
        }
        @rmdir($directory);
    }

    private static function pairKey(string $playlist, string $identifier): string
    {
        return $playlist . "\0" . $identifier;
    }
}
