<?php

declare(strict_types=1);

namespace FreeTV\Admin;

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/ServerPaths.php';
require_once __DIR__ . '/publication/PublicationTimestamp.php';

use DateTimeImmutable;
use DateTimeZone;
use FreeTV\Admin\Publication\PublicationTimestamp;
use JsonException;
use RuntimeException;
use Throwable;

final class ProductionContentSnapshotException extends RuntimeException
{
}

final class ProductionContentSnapshotService
{
    private string $snapshotRoot;
    private string $thumbnailRoot;
    private $databaseLoader;
    private $clock;
    private $fileStager;
    private $fileHasher;
    private $fileWriter;

    public function __construct(
        ?ServerPaths $serverPaths = null,
        ?callable $databaseLoader = null,
        ?callable $clock = null,
        ?callable $fileStager = null,
        ?callable $fileHasher = null,
        ?callable $fileWriter = null
    ) {
        $serverPaths ??= new ServerPaths();
        $this->snapshotRoot = $serverPaths->tempRoot() . '/data-snapshots';
        $this->thumbnailRoot = $serverPaths->publicRoot() . '/thumbs';
        $this->databaseLoader = $databaseLoader ?? static function (): array {
            return [
                'playlists' => Database::table('playlists')
                    ->orderBy('sort_order')
                    ->orderBy('id')
                    ->get()
                    ->map(static fn(object $row): array => (array) $row)
                    ->all(),
                'playlist_shows' => Database::table('playlist_shows')
                    ->orderBy('playlist_id')
                    ->orderBy('sort_order')
                    ->orderBy('id')
                    ->get()
                    ->map(static fn(object $row): array => (array) $row)
                    ->all(),
            ];
        };
        $this->clock = $clock ?? static fn(): DateTimeImmutable =>
            new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $this->fileStager = $fileStager ?? function (string $source, string $destination): void {
            $this->stageFile($source, $destination);
        };
        $this->fileHasher = $fileHasher ?? static fn(string $path): string|false =>
            hash_file('sha256', $path);
        $this->fileWriter = $fileWriter ?? static function (string $path, string $contents): void {
            $written = file_put_contents($path, $contents, LOCK_EX);
            if ($written !== strlen($contents) || !chmod($path, 0600)) {
                throw new ProductionContentSnapshotException('Could not write snapshot file ' . basename($path));
            }
        };
    }

    public function create(): array
    {
        $productionSnapshotAt = PublicationTimestamp::forOperation(($this->clock)());
        $snapshotName = 'freetv-content-snapshot-' . $this->filenameTimestamp($productionSnapshotAt);

        $this->ensureSnapshotRoot();
        $lock = fopen($this->snapshotRoot . '/.lock', 'c');
        if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) {
            if (is_resource($lock)) {
                fclose($lock);
            }
            throw new ProductionContentSnapshotException('Another production content snapshot is already running');
        }

        try {
            return $this->createLocked($snapshotName, $productionSnapshotAt);
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    private function createLocked(string $snapshotName, string $productionSnapshotAt): array
    {
        $finalRoot = $this->snapshotRoot . '/' . $snapshotName;
        if (file_exists($finalRoot) || is_link($finalRoot)) {
            throw new ProductionContentSnapshotException('A production content snapshot already exists for this timestamp');
        }

        $stagingRoot = $this->snapshotRoot . '/.staging-' . bin2hex(random_bytes(16));
        if (!mkdir($stagingRoot . '/thumbs', 0700, true)) {
            throw new ProductionContentSnapshotException('Could not create snapshot staging directory');
        }

        try {
            $data = ($this->databaseLoader)();
            $playlists = $this->normalizeRows($data['playlists'] ?? null, 'playlists');
            $shows = $this->normalizeRows($data['playlist_shows'] ?? null, 'playlist_shows');
            $this->sortRows($playlists, ['sort_order', 'id']);
            $this->sortRows($shows, ['playlist_id', 'sort_order', 'id']);

            $files = [];
            $files[] = $this->writeJsonArtifact($stagingRoot, 'playlists.json', $playlists);
            $files[] = $this->writeJsonArtifact($stagingRoot, 'playlist_shows.json', $shows);

            $thumbnailFiles = $this->captureThumbnails($stagingRoot . '/thumbs');
            $thumbnailManifest = [
                'format_version' => 1,
                'files' => $thumbnailFiles,
            ];
            $files[] = $this->writeJsonArtifact($stagingRoot, 'thumbs-manifest.json', $thumbnailManifest);

            $counts = [
                'playlists' => count($playlists),
                'shows' => count($shows),
                'thumbnails' => count($thumbnailFiles),
            ];
            $this->assertCounts($stagingRoot, $playlists, $shows, $thumbnailFiles, $counts);

            $captureCompletedAt = PublicationTimestamp::forOperation(($this->clock)());
            $manifest = [
                'format_version' => 1,
                'production_snapshot_at' => $productionSnapshotAt,
                'capture_completed_at' => $captureCompletedAt,
                'counts' => $counts,
                'files' => $files,
            ];
            $this->writeJson($stagingRoot . '/manifest.json', $manifest);

            if (!rename($stagingRoot, $finalRoot)) {
                throw new ProductionContentSnapshotException('Could not complete production content snapshot');
            }

            return [
                'path' => $finalRoot,
                'production_snapshot_at' => $productionSnapshotAt,
                'capture_completed_at' => $captureCompletedAt,
                'counts' => $counts,
            ];
        } catch (Throwable $exception) {
            $this->removeOwnedTree($stagingRoot);
            throw $exception instanceof ProductionContentSnapshotException
                ? $exception
                : new ProductionContentSnapshotException('Production content snapshot failed', 0, $exception);
        }
    }

    private function captureThumbnails(string $destinationRoot): array
    {
        if (!is_dir($this->thumbnailRoot)
            || is_link($this->thumbnailRoot)
            || !is_readable($this->thumbnailRoot)
        ) {
            throw new ProductionContentSnapshotException('Thumbnail source directory is unavailable');
        }

        $entries = scandir($this->thumbnailRoot);
        if ($entries === false) {
            throw new ProductionContentSnapshotException('Could not read thumbnail source directory');
        }

        $filenames = array_values(array_filter(
            $entries,
            static fn(string $filename): bool => preg_match('/^tt\d+\.jpg$/', $filename) === 1
        ));
        sort($filenames, SORT_STRING);

        $files = [];
        foreach ($filenames as $filename) {
            $source = $this->thumbnailRoot . '/' . $filename;
            if (is_link($source) || !is_file($source) || !is_readable($source)) {
                throw new ProductionContentSnapshotException('Thumbnail is not a readable regular file: ' . $filename);
            }

            $destination = $destinationRoot . '/' . $filename;
            ($this->fileStager)($source, $destination);
            clearstatcache(true, $destination);
            $hash = ($this->fileHasher)($destination);
            $bytes = filesize($destination);
            if (!is_string($hash) || preg_match('/^[a-f0-9]{64}$/', $hash) !== 1 || $bytes === false) {
                throw new ProductionContentSnapshotException('Could not describe snapshot thumbnail ' . $filename);
            }
            $files[] = [
                'path' => 'thumbs/' . $filename,
                'sha256' => $hash,
                'bytes' => $bytes,
            ];
        }
        return $files;
    }

    private function stageFile(string $sourcePath, string $destinationPath): void
    {
        $source = @fopen($sourcePath, 'rb');
        if ($source === false) {
            throw new ProductionContentSnapshotException('Could not read thumbnail source');
        }
        $destination = null;
        try {
            $sourceStat = fstat($source);
            $pathStat = lstat($sourcePath);
            if ($sourceStat === false || $pathStat === false
                || ($sourceStat['mode'] & 0170000) !== 0100000
                || ($pathStat['mode'] & 0170000) !== 0100000
                || $sourceStat['dev'] !== $pathStat['dev']
                || $sourceStat['ino'] !== $pathStat['ino']
            ) {
                throw new ProductionContentSnapshotException('Thumbnail source changed during capture');
            }
            $destination = @fopen($destinationPath, 'xb');
            if ($destination === false) {
                throw new ProductionContentSnapshotException('Could not create snapshot thumbnail');
            }
            $copied = stream_copy_to_stream($source, $destination);
            if ($copied === false || $copied !== $sourceStat['size'] || !fflush($destination)) {
                throw new ProductionContentSnapshotException('Could not copy complete thumbnail bytes');
            }
        } finally {
            if (is_resource($destination)) {
                fclose($destination);
            }
            fclose($source);
        }
        if (!chmod($destinationPath, 0600)) {
            throw new ProductionContentSnapshotException('Could not set snapshot thumbnail permissions');
        }
    }

    private function writeJsonArtifact(string $root, string $relativePath, array $value): array
    {
        $path = $root . '/' . $relativePath;
        $this->writeJson($path, $value);
        clearstatcache(true, $path);
        $hash = ($this->fileHasher)($path);
        $bytes = filesize($path);
        if (!is_string($hash) || preg_match('/^[a-f0-9]{64}$/', $hash) !== 1 || $bytes === false) {
            throw new ProductionContentSnapshotException('Could not describe snapshot file ' . $relativePath);
        }
        return ['path' => $relativePath, 'sha256' => $hash, 'bytes' => $bytes];
    }

    private function writeJson(string $path, array $value): void
    {
        try {
            $json = json_encode(
                $value,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
            ) . "\n";
        } catch (JsonException $exception) {
            throw new ProductionContentSnapshotException('Could not encode snapshot JSON', 0, $exception);
        }
        ($this->fileWriter)($path, $json);
    }

    private function normalizeRows(mixed $rows, string $label): array
    {
        if (!is_iterable($rows)) {
            throw new ProductionContentSnapshotException('Snapshot database loader returned invalid ' . $label);
        }
        $normalized = [];
        foreach ($rows as $row) {
            if (is_object($row)) {
                $row = (array) $row;
            }
            if (!is_array($row)) {
                throw new ProductionContentSnapshotException('Snapshot database row is invalid in ' . $label);
            }
            $normalized[] = $row;
        }
        return $normalized;
    }

    private function sortRows(array &$rows, array $keys): void
    {
        usort($rows, static function (array $left, array $right) use ($keys): int {
            foreach ($keys as $key) {
                if (!array_key_exists($key, $left) || !array_key_exists($key, $right)) {
                    throw new ProductionContentSnapshotException('Snapshot row is missing ordering field ' . $key);
                }
                $comparison = ((int) $left[$key]) <=> ((int) $right[$key]);
                if ($comparison !== 0) {
                    return $comparison;
                }
            }
            return 0;
        });
    }

    private function assertCounts(
        string $root,
        array $playlists,
        array $shows,
        array $thumbnailFiles,
        array $counts
    ): void {
        $copied = glob($root . '/thumbs/tt*.jpg');
        if ($counts['playlists'] !== count($playlists)
            || $counts['shows'] !== count($shows)
            || $counts['thumbnails'] !== count($thumbnailFiles)
            || $copied === false
            || $counts['thumbnails'] !== count($copied)
        ) {
            throw new ProductionContentSnapshotException('Snapshot counts are inconsistent');
        }
    }

    private function ensureSnapshotRoot(): void
    {
        if (!is_dir($this->snapshotRoot) && !mkdir($this->snapshotRoot, 0700, true)) {
            throw new ProductionContentSnapshotException('Could not create private snapshot directory');
        }
        if (is_link($this->snapshotRoot) || !is_writable($this->snapshotRoot)) {
            throw new ProductionContentSnapshotException('Private snapshot directory is unavailable');
        }
        if (!chmod($this->snapshotRoot, 0700)) {
            throw new ProductionContentSnapshotException('Could not secure private snapshot directory');
        }
    }

    private function filenameTimestamp(string $canonicalTimestamp): string
    {
        return (new DateTimeImmutable($canonicalTimestamp))
            ->setTimezone(new DateTimeZone('UTC'))
            ->format('Ymd\THis\Z');
    }

    private function removeOwnedTree(string $root): void
    {
        if (!str_starts_with($root, $this->snapshotRoot . '/.staging-') || !is_dir($root)) {
            return;
        }
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $item) {
            $item->isDir() && !$item->isLink()
                ? @rmdir($item->getPathname())
                : @unlink($item->getPathname());
        }
        @rmdir($root);
    }
}
