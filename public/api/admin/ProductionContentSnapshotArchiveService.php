<?php

declare(strict_types=1);

namespace FreeTV\Admin;

require_once __DIR__ . '/ServerPaths.php';
require_once __DIR__ . '/publication/PublicationTimestamp.php';

use DateTimeImmutable;
use FreeTV\Admin\Publication\PublicationTimestamp;
use JsonException;
use RuntimeException;
use Throwable;
use ZipArchive;

final class ProductionContentSnapshotArchiveException extends RuntimeException
{
    public function __construct(string $message, private int $httpStatus = 500, ?Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }

    public function getHttpStatus(): int
    {
        return $this->httpStatus;
    }
}

final class ProductionContentSnapshotArchiveService
{
    private const NAME_PATTERN = '/^freetv-content-snapshot-(\d{8}T\d{6}Z)$/';
    private const ROOT_FILES = [
        'manifest.json',
        'playlist_shows.json',
        'playlists.json',
        'thumbs',
        'thumbs-manifest.json',
    ];

    private string $snapshotRoot;
    private $archiveFactory;

    public function __construct(?ServerPaths $serverPaths = null, ?callable $archiveFactory = null)
    {
        $serverPaths ??= new ServerPaths();
        $this->snapshotRoot = $serverPaths->tempRoot() . '/data-snapshots';
        $this->archiveFactory = $archiveFactory ?? static fn(): ZipArchive => new ZipArchive();
    }

    public static function isValidSnapshotName(string $name): bool
    {
        return preg_match(self::NAME_PATTERN, $name) === 1;
    }

    public function create(string $snapshotName): array
    {
        $temporaryPath = null;
        $zip = null;
        $opened = false;
        try {
            $snapshot = $this->loadCompletedSnapshot($snapshotName);
            $archivePath = $this->snapshotRoot . '/' . $snapshotName . '.zip';
            if (file_exists($archivePath) || is_link($archivePath)) {
                throw new ProductionContentSnapshotArchiveException(
                    'A snapshot archive already exists for this snapshot',
                    409
                );
            }

            $temporaryPath = $this->snapshotRoot . '/.archive-' . bin2hex(random_bytes(16)) . '.tmp';
            $zip = ($this->archiveFactory)();
            $status = $zip->open($temporaryPath, ZipArchive::CREATE | ZipArchive::EXCL);
            if ($status !== true) {
                throw new ProductionContentSnapshotArchiveException('Could not create snapshot archive');
            }
            $opened = true;

            $entries = $this->addSnapshot($zip, $snapshotName, $snapshot);
            if ($zip->close() !== true) {
                $opened = false;
                throw new ProductionContentSnapshotArchiveException('Could not finalize snapshot archive');
            }
            $opened = false;

            if (!is_file($temporaryPath) || is_link($temporaryPath) || filesize($temporaryPath) === 0) {
                throw new ProductionContentSnapshotArchiveException('Snapshot archive was not finalized');
            }
            if (!chmod($temporaryPath, 0600)) {
                throw new ProductionContentSnapshotArchiveException('Could not secure snapshot archive');
            }
            $this->verifyArchive($temporaryPath, $entries);
            clearstatcache(true, $temporaryPath);
            $bytes = filesize($temporaryPath);
            if ($bytes === false || $bytes < 1) {
                throw new ProductionContentSnapshotArchiveException('Completed snapshot archive is invalid');
            }
            if (!@link($temporaryPath, $archivePath)) {
                throw new ProductionContentSnapshotArchiveException(
                    file_exists($archivePath) || is_link($archivePath)
                        ? 'A snapshot archive already exists for this snapshot'
                        : 'Could not complete snapshot archive',
                    file_exists($archivePath) || is_link($archivePath) ? 409 : 500
                );
            }
            if (!@unlink($temporaryPath)) {
                @unlink($archivePath);
                throw new ProductionContentSnapshotArchiveException('Could not complete snapshot archive');
            }

            return ['name' => $snapshotName, 'path' => $archivePath, 'bytes' => $bytes];
        } catch (Throwable $exception) {
            if ($opened && is_object($zip)) {
                $zip->close();
            }
            if (is_string($temporaryPath) && (is_file($temporaryPath) || is_link($temporaryPath))) {
                @unlink($temporaryPath);
            }
            throw $exception instanceof ProductionContentSnapshotArchiveException
                ? $exception
                : new ProductionContentSnapshotArchiveException('Snapshot archive creation failed', 500, $exception);
        }
    }

    public function resolveArchive(string $snapshotName): array
    {
        $this->assertSnapshotName($snapshotName);
        $root = realpath($this->snapshotRoot);
        if ($root === false || !is_dir($root) || is_link($this->snapshotRoot)) {
            throw new ProductionContentSnapshotArchiveException('Snapshot archive not found', 404);
        }

        $path = $root . '/' . $snapshotName . '.zip';
        if (is_link($path) || !is_file($path) || !is_readable($path)) {
            throw new ProductionContentSnapshotArchiveException('Snapshot archive not found', 404);
        }
        $resolved = realpath($path);
        if ($resolved === false || dirname($resolved) !== $root) {
            throw new ProductionContentSnapshotArchiveException('Snapshot archive not found', 404);
        }
        $bytes = filesize($resolved);
        if ($bytes === false || $bytes < 1) {
            throw new ProductionContentSnapshotArchiveException('Snapshot archive not found', 404);
        }
        $this->validateDownloadArchive($resolved, $snapshotName);
        return ['name' => $snapshotName, 'path' => $resolved, 'bytes' => $bytes];
    }

    private function loadCompletedSnapshot(string $snapshotName): array
    {
        $this->assertSnapshotName($snapshotName);
        $root = realpath($this->snapshotRoot);
        if ($root === false || !is_dir($root) || is_link($this->snapshotRoot)) {
            throw new ProductionContentSnapshotArchiveException('Completed snapshot not found', 404);
        }

        $path = $root . '/' . $snapshotName;
        if (is_link($path) || !is_dir($path) || !is_readable($path)) {
            throw new ProductionContentSnapshotArchiveException('Completed snapshot not found', 404);
        }
        $resolved = realpath($path);
        if ($resolved === false || dirname($resolved) !== $root) {
            throw new ProductionContentSnapshotArchiveException('Completed snapshot not found', 404);
        }

        $entries = scandir($resolved);
        if ($entries === false) {
            throw new ProductionContentSnapshotArchiveException('Could not read completed snapshot');
        }
        $entries = array_values(array_diff($entries, ['.', '..']));
        sort($entries, SORT_STRING);
        if ($entries !== self::ROOT_FILES) {
            throw new ProductionContentSnapshotArchiveException('Completed snapshot contains unexpected files', 409);
        }

        foreach (array_diff(self::ROOT_FILES, ['thumbs']) as $filename) {
            $file = $resolved . '/' . $filename;
            if (is_link($file) || !is_file($file) || !is_readable($file)) {
                throw new ProductionContentSnapshotArchiveException('Completed snapshot is incomplete', 409);
            }
        }
        $thumbRoot = $resolved . '/thumbs';
        if (is_link($thumbRoot) || !is_dir($thumbRoot) || !is_readable($thumbRoot)) {
            throw new ProductionContentSnapshotArchiveException('Completed snapshot is incomplete', 409);
        }

        $thumbnailNames = scandir($thumbRoot);
        if ($thumbnailNames === false) {
            throw new ProductionContentSnapshotArchiveException('Could not read completed snapshot thumbnails');
        }
        $thumbnailNames = array_values(array_diff($thumbnailNames, ['.', '..']));
        sort($thumbnailNames, SORT_STRING);
        foreach ($thumbnailNames as $filename) {
            $file = $thumbRoot . '/' . $filename;
            if (preg_match('/^tt\d+\.jpg$/', $filename) !== 1
                || is_link($file)
                || !is_file($file)
                || !is_readable($file)
            ) {
                throw new ProductionContentSnapshotArchiveException('Completed snapshot contains an invalid thumbnail', 409);
            }
        }

        $manifest = $this->readJson($resolved . '/manifest.json');
        $thumbnailManifest = $this->readJson($resolved . '/thumbs-manifest.json');
        $playlists = $this->readJson($resolved . '/playlists.json');
        $shows = $this->readJson($resolved . '/playlist_shows.json');
        $this->validateSnapshotMetadata(
            $snapshotName,
            $resolved,
            $manifest,
            $thumbnailManifest,
            $playlists,
            $shows,
            $thumbnailNames
        );

        return ['path' => $resolved, 'thumbnail_names' => $thumbnailNames, 'manifest' => $manifest];
    }

    private function validateSnapshotMetadata(
        string $snapshotName,
        string $root,
        array $manifest,
        array $thumbnailManifest,
        array $playlists,
        array $shows,
        array $thumbnailNames
    ): void {
        $productionAt = $manifest['production_snapshot_at'] ?? null;
        if (($manifest['format_version'] ?? null) !== 1
            || !$this->isCanonicalTimestamp($productionAt)
            || $this->nameForTimestamp($productionAt) !== $snapshotName
            || !$this->isCanonicalTimestamp($manifest['capture_completed_at'] ?? null)
            || !is_array($manifest['counts'] ?? null)
            || !is_array($manifest['files'] ?? null)
            || ($thumbnailManifest['format_version'] ?? null) !== 1
            || !is_array($thumbnailManifest['files'] ?? null)
        ) {
            throw new ProductionContentSnapshotArchiveException('Completed snapshot metadata is invalid', 409);
        }

        $counts = $manifest['counts'];
        if ($counts !== [
            'playlists' => count($playlists),
            'shows' => count($shows),
            'thumbnails' => count($thumbnailNames),
        ]) {
            throw new ProductionContentSnapshotArchiveException('Completed snapshot counts are inconsistent', 409);
        }

        $this->validateFileMetadata($root, $manifest['files'], [
            'playlists.json',
            'playlist_shows.json',
            'thumbs-manifest.json',
        ]);
        $this->validateFileMetadata(
            $root,
            $thumbnailManifest['files'],
            array_map(static fn(string $name): string => 'thumbs/' . $name, $thumbnailNames)
        );
    }

    private function validateFileMetadata(string $root, array $files, array $expectedPaths): void
    {
        if (array_column($files, 'path') !== $expectedPaths) {
            throw new ProductionContentSnapshotArchiveException('Completed snapshot file metadata is inconsistent', 409);
        }
        foreach ($files as $file) {
            $relative = $file['path'] ?? null;
            $hash = $file['sha256'] ?? null;
            $bytes = $file['bytes'] ?? null;
            if (!is_string($relative)
                || !is_string($hash)
                || preg_match('/^[a-f0-9]{64}$/', $hash) !== 1
                || !is_int($bytes)
                || $bytes < 0
            ) {
                throw new ProductionContentSnapshotArchiveException('Completed snapshot file metadata is invalid', 409);
            }
            $path = $root . '/' . $relative;
            if (is_link($path)
                || !is_file($path)
                || filesize($path) !== $bytes
                || hash_file('sha256', $path) !== $hash
            ) {
                throw new ProductionContentSnapshotArchiveException('Completed snapshot file verification failed', 409);
            }
        }
    }

    private function addSnapshot(object $zip, string $snapshotName, array $snapshot): array
    {
        $root = $snapshot['path'];
        $timestamp = (new DateTimeImmutable($snapshot['manifest']['production_snapshot_at']))->getTimestamp();
        $entries = [$snapshotName . '/'];
        if (!$zip->addEmptyDir($snapshotName)) {
            throw new ProductionContentSnapshotArchiveException('Could not add snapshot directory to archive');
        }

        foreach (['manifest.json', 'playlists.json', 'playlist_shows.json', 'thumbs-manifest.json'] as $filename) {
            $entry = $snapshotName . '/' . $filename;
            if (!$zip->addFile($root . '/' . $filename, $entry)) {
                throw new ProductionContentSnapshotArchiveException('Could not add snapshot file to archive');
            }
            $entries[] = $entry;
        }

        $thumbDirectory = $snapshotName . '/thumbs/';
        if (!$zip->addEmptyDir(rtrim($thumbDirectory, '/'))) {
            throw new ProductionContentSnapshotArchiveException('Could not add thumbnail directory to archive');
        }
        $entries[] = $thumbDirectory;
        foreach ($snapshot['thumbnail_names'] as $filename) {
            $entry = $snapshotName . '/thumbs/' . $filename;
            if (!$zip->addFile($root . '/thumbs/' . $filename, $entry)) {
                throw new ProductionContentSnapshotArchiveException('Could not add snapshot thumbnail to archive');
            }
            $entries[] = $entry;
        }

        if (method_exists($zip, 'setMtimeName')) {
            foreach ($entries as $entry) {
                if (!$zip->setMtimeName($entry, $timestamp)) {
                    throw new ProductionContentSnapshotArchiveException('Could not normalize snapshot archive metadata');
                }
            }
        }
        return $entries;
    }

    private function verifyArchive(string $path, array $expectedEntries): void
    {
        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::RDONLY) !== true) {
            throw new ProductionContentSnapshotArchiveException('Could not verify snapshot archive');
        }
        try {
            $entries = [];
            for ($index = 0; $index < $zip->numFiles; $index++) {
                $name = $zip->getNameIndex($index);
                if (!is_string($name)) {
                    throw new ProductionContentSnapshotArchiveException('Snapshot archive contains an invalid entry');
                }
                $entries[] = $name;
            }
            if ($entries !== $expectedEntries) {
                throw new ProductionContentSnapshotArchiveException('Snapshot archive contents are inconsistent');
            }
        } finally {
            $zip->close();
        }
    }

    private function validateDownloadArchive(string $path, string $snapshotName): void
    {
        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::RDONLY) !== true) {
            throw new ProductionContentSnapshotArchiveException('Snapshot archive not found', 404);
        }
        try {
            $required = [
                $snapshotName . '/',
                $snapshotName . '/manifest.json',
                $snapshotName . '/playlists.json',
                $snapshotName . '/playlist_shows.json',
                $snapshotName . '/thumbs-manifest.json',
                $snapshotName . '/thumbs/',
            ];
            foreach ($required as $entry) {
                if ($zip->locateName($entry, ZipArchive::FL_ENC_RAW) === false) {
                    throw new ProductionContentSnapshotArchiveException('Snapshot archive not found', 404);
                }
            }
            $prefix = $snapshotName . '/';
            for ($index = 0; $index < $zip->numFiles; $index++) {
                $entry = $zip->getNameIndex($index);
                if (!is_string($entry)
                    || str_contains($entry, "\0")
                    || str_contains($entry, '\\')
                    || !str_starts_with($entry, $prefix)
                    || in_array('..', explode('/', $entry), true)
                ) {
                    throw new ProductionContentSnapshotArchiveException('Snapshot archive not found', 404);
                }
            }
        } finally {
            $zip->close();
        }
    }

    private function readJson(string $path): array
    {
        try {
            $value = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new ProductionContentSnapshotArchiveException('Completed snapshot contains invalid JSON', 409, $exception);
        }
        if (!is_array($value)) {
            throw new ProductionContentSnapshotArchiveException('Completed snapshot contains invalid JSON', 409);
        }
        return $value;
    }

    private function assertSnapshotName(string $snapshotName): void
    {
        if (!self::isValidSnapshotName($snapshotName)) {
            throw new ProductionContentSnapshotArchiveException('Invalid snapshot name', 400);
        }
    }

    private function nameForTimestamp(string $timestamp): string
    {
        return 'freetv-content-snapshot-'
            . (new DateTimeImmutable($timestamp))->format('Ymd\THis\Z');
    }

    private function isCanonicalTimestamp(mixed $timestamp): bool
    {
        if (!is_string($timestamp)) {
            return false;
        }
        try {
            return PublicationTimestamp::format($timestamp) === $timestamp;
        } catch (\InvalidArgumentException) {
            return false;
        }
    }
}
