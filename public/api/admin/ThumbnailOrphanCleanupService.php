<?php

declare(strict_types=1);

namespace FreeTV\Admin;

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/ServerPaths.php';
require_once __DIR__ . '/ThumbnailIntegrityService.php';
require_once __DIR__ . '/ThumbnailService.php';

use DateTimeImmutable;
use DateTimeZone;
use JsonException;
use RuntimeException;
use Throwable;

final class ThumbnailOrphanCleanupException extends RuntimeException
{
}

final class ThumbnailOrphanCleanupService
{
    private string $publicRoot;
    private string $thumbnailDirectory;
    private string $quarantineRoot;
    private $auditLoader;
    private $referenceChecker;
    private $clock;
    private $fileMover;
    private $fileHasher;
    private $manifestWriter;

    public function __construct(
        ?ServerPaths $serverPaths = null,
        ?callable $auditLoader = null,
        ?callable $referenceChecker = null,
        ?callable $clock = null,
        ?callable $fileMover = null,
        ?callable $fileHasher = null,
        ?callable $manifestWriter = null
    ) {
        $serverPaths ??= new ServerPaths();
        $this->publicRoot = rtrim($serverPaths->publicRoot(), DIRECTORY_SEPARATOR);
        $this->thumbnailDirectory = $this->publicRoot . DIRECTORY_SEPARATOR . 'thumbs';
        $this->quarantineRoot = rtrim($serverPaths->tempRoot(), DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR . 'thumbnail-quarantine';
        $this->auditLoader = $auditLoader
            ?? fn(): array => (new ThumbnailIntegrityService($this->thumbnailDirectory))->audit();
        $this->referenceChecker = $referenceChecker ?? static fn(string $imdb): bool =>
            Database::table('playlist_shows')->whereRaw('TRIM(imdb) = ?', [$imdb])->exists();
        $this->clock = $clock ?? static fn(): DateTimeImmutable =>
            new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $this->fileMover = $fileMover ?? static fn(string $source, string $destination): bool =>
            rename($source, $destination);
        $this->fileHasher = $fileHasher ?? static fn(string $path): string|false =>
            hash_file('sha256', $path);
        $this->manifestWriter = $manifestWriter ?? static function (string $path, string $contents): void {
            $written = file_put_contents($path, $contents, LOCK_EX);
            if ($written !== strlen($contents) || !chmod($path, 0600)) {
                throw new ThumbnailOrphanCleanupException('Could not write quarantine manifest');
            }
        };
    }

    public function run(bool $apply = false): array
    {
        $audit = ($this->auditLoader)();
        if (!is_array($audit)
            || !isset($audit['summary'], $audit['orphaned'])
            || !is_array($audit['summary'])
            || !is_array($audit['orphaned'])
        ) {
            throw new ThumbnailOrphanCleanupException('Thumbnail integrity audit result is invalid');
        }

        $candidates = $this->normalizeCandidates($audit['orphaned']);
        $createdAt = ($this->clock)()->setTimezone(new DateTimeZone('UTC'));
        $batchId = $createdAt->format('Ymd\THis\Z');
        $quarantineDirectory = $this->quarantineRoot . DIRECTORY_SEPARATOR . $batchId;
        $result = [
            'mode' => $apply ? 'apply' : 'dry-run',
            'audit_summary' => $audit['summary'],
            'orphan_count' => count($candidates),
            'quarantine_directory' => $quarantineDirectory,
            'candidates' => array_map(
                fn(array $candidate): array => $this->plannedFile($candidate),
                $candidates
            ),
            'moved' => [],
            'skipped' => [],
            'failed' => [],
            'manifest' => null,
        ];
        if (!$apply) {
            return $result;
        }

        $this->prepareBatchDirectory($quarantineDirectory);
        foreach ($candidates as $candidate) {
            $imdb = $candidate['imdb'];
            $filename = $candidate['filename'];
            try {
                if (($this->referenceChecker)($imdb)) {
                    $result['skipped'][] = [
                        'imdb' => $imdb,
                        'filename' => $filename,
                        'reason' => 'currently_referenced',
                    ];
                    continue;
                }
            } catch (Throwable $exception) {
                $result['failed'][] = [
                    'imdb' => $imdb,
                    'filename' => $filename,
                    'reason' => 'reference_check_failed',
                ];
                continue;
            }

            $source = $this->thumbnailDirectory . DIRECTORY_SEPARATOR . $filename;
            $destination = $quarantineDirectory . DIRECTORY_SEPARATOR . $filename;
            if (is_link($source) || !is_file($source)) {
                $result['failed'][] = [
                    'imdb' => $imdb,
                    'filename' => $filename,
                    'reason' => 'unsafe_or_missing_source',
                ];
                continue;
            }
            if (file_exists($destination) || is_link($destination)) {
                $result['failed'][] = [
                    'imdb' => $imdb,
                    'filename' => $filename,
                    'reason' => 'destination_exists',
                ];
                continue;
            }

            $sha256 = ($this->fileHasher)($source);
            if (!is_string($sha256) || preg_match('/^[a-f0-9]{64}$/', $sha256) !== 1) {
                $result['failed'][] = [
                    'imdb' => $imdb,
                    'filename' => $filename,
                    'reason' => 'hash_failed',
                ];
                continue;
            }

            try {
                $moved = ($this->fileMover)($source, $destination);
            } catch (Throwable $exception) {
                $moved = false;
            }
            if ($moved !== true) {
                $result['failed'][] = [
                    'imdb' => $imdb,
                    'filename' => $filename,
                    'reason' => 'move_failed',
                ];
                continue;
            }

            $result['moved'][] = [
                'imdb' => $imdb,
                'filename' => $filename,
                'original_relative_path' => 'thumbs/' . $filename,
                'quarantine_filename' => $filename,
                'sha256' => $sha256,
            ];
        }

        $manifest = [
            'format_version' => 1,
            'created_at' => $createdAt->format('Y-m-d\TH:i:s.v\Z'),
            'mode' => 'apply',
            'original_thumbnail_directory' => $this->thumbnailDirectory,
            'quarantine_directory' => $quarantineDirectory,
            'audit_summary' => $audit['summary'],
            'moved_count' => count($result['moved']),
            'skipped_count' => count($result['skipped']),
            'failed_count' => count($result['failed']),
            'files' => $result['moved'],
            'skipped' => $result['skipped'],
            'failed' => $result['failed'],
        ];
        $manifestPath = $quarantineDirectory . DIRECTORY_SEPARATOR . 'manifest.json';
        if (file_exists($manifestPath) || is_link($manifestPath)) {
            throw new ThumbnailOrphanCleanupException('Quarantine manifest already exists');
        }
        try {
            $json = json_encode(
                $manifest,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
            ) . "\n";
        } catch (JsonException $exception) {
            throw new ThumbnailOrphanCleanupException('Could not encode quarantine manifest', 0, $exception);
        }
        ($this->manifestWriter)($manifestPath, $json);
        $result['manifest'] = $manifestPath;
        return $result;
    }

    private function normalizeCandidates(array $orphans): array
    {
        $candidates = [];
        foreach ($orphans as $item) {
            if (!is_array($item)
                || !isset($item['imdb'], $item['filename'])
                || !ThumbnailService::isValidImdb($item['imdb'])
                || $item['filename'] !== $item['imdb'] . '.jpg'
            ) {
                throw new ThumbnailOrphanCleanupException('Audit contains an invalid orphan item');
            }
            $candidates[$item['imdb']] = [
                'imdb' => $item['imdb'],
                'filename' => $item['filename'],
            ];
        }
        ksort($candidates, SORT_STRING);
        return array_values($candidates);
    }

    private function plannedFile(array $candidate): array
    {
        return [
            'imdb' => $candidate['imdb'],
            'filename' => $candidate['filename'],
            'original_relative_path' => 'thumbs/' . $candidate['filename'],
            'quarantine_filename' => $candidate['filename'],
        ];
    }

    private function prepareBatchDirectory(string $quarantineDirectory): void
    {
        if ($this->isInside($this->quarantineRoot, $this->publicRoot)) {
            throw new ThumbnailOrphanCleanupException('Quarantine root must be outside the public root');
        }
        if (file_exists($quarantineDirectory) || is_link($quarantineDirectory)) {
            throw new ThumbnailOrphanCleanupException('Quarantine batch directory already exists');
        }
        if (!is_dir($this->quarantineRoot)) {
            if (!mkdir($this->quarantineRoot, 0700, true)) {
                throw new ThumbnailOrphanCleanupException('Could not create quarantine root');
            }
        } elseif (is_link($this->quarantineRoot)) {
            throw new ThumbnailOrphanCleanupException('Quarantine root must not be a symbolic link');
        }
        if ((fileperms($this->quarantineRoot) & 0077) !== 0) {
            throw new ThumbnailOrphanCleanupException('Quarantine root permissions are not private');
        }
        if (!mkdir($quarantineDirectory, 0700)) {
            throw new ThumbnailOrphanCleanupException('Could not create quarantine batch directory');
        }
    }

    private function isInside(string $candidate, string $root): bool
    {
        $candidate = rtrim(str_replace('\\', '/', $candidate), '/');
        $root = rtrim(str_replace('\\', '/', $root), '/');
        return $candidate === $root || str_starts_with($candidate, $root . '/');
    }
}
