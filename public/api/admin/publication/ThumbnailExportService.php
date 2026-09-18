<?php

namespace FreeTV\Admin\Publication;

require_once __DIR__ . '/../ServerPaths.php';

require_once __DIR__ . '/../ThumbnailService.php';
require_once __DIR__ . '/PublicationTimestamp.php';

use DateTimeImmutable;
use DateTimeZone;
use FreeTV\Admin\ThumbnailService;
use FreeTV\Admin\ServerPaths;
use JsonException;
use RuntimeException;
use Throwable;

class ThumbnailExportException extends RuntimeException
{
    public function __construct(string $message, private int $httpStatus = 500)
    {
        parent::__construct($message);
    }

    public function getHttpStatus(): int
    {
        return $this->httpStatus;
    }
}

class ThumbnailExportService
{
    private string $serverRoot;
    private string $thumbnailDirectory;
    private $clock;
    private $revisionResolver;
    private $fileStager;

    public function __construct(
        ?string $thumbnailDirectory = null,
        ?callable $clock = null,
        ?callable $revisionResolver = null,
        ?callable $fileStager = null
    ) {
        $serverPaths = new ServerPaths();
        $this->serverRoot = $serverPaths->appRoot();
        $this->thumbnailDirectory = rtrim(
            $thumbnailDirectory ?? $serverPaths->publicRoot() . '/thumbs',
            DIRECTORY_SEPARATOR
        );
        $this->clock = $clock ?? static fn() => new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $this->revisionResolver = $revisionResolver ?? fn(): ?string => $this->resolveServerRevision();
        $this->fileStager = $fileStager ?? function (string $source, string $destination): void {
            $this->stageFile($source, $destination);
        };
    }

    public function export(string $destination): array
    {
        $resolvedDestination = $this->validateDestination($destination);
        $filenames = $this->loadFilenames();
        $manifest = $this->buildManifest();

        if (!@mkdir($resolvedDestination, 0755)) {
            throw new ThumbnailExportException('Could not create thumbnail export destination');
        }

        try {
            $thumbnailDestination = $resolvedDestination . '/thumbs';
            if (!@mkdir($thumbnailDestination, 0755)) {
                throw new ThumbnailExportException('Could not create thumbnail export directory');
            }

            foreach ($filenames as $filename) {
                $relativePath = 'thumbs/' . $filename;
                $stagedPath = $resolvedDestination . '/' . $relativePath;
                ($this->fileStager)(
                    $this->thumbnailDirectory . '/' . $filename,
                    $stagedPath
                );
                $this->assertJpeg($stagedPath, $filename);

                clearstatcache(true, $stagedPath);
                $hash = hash_file('sha256', $stagedPath);
                $bytes = filesize($stagedPath);
                if ($hash === false || $bytes === false) {
                    throw new ThumbnailExportException('Could not describe staged file ' . $relativePath);
                }

                $manifest['files'][] = [
                    'path' => $relativePath,
                    'sha256' => $hash,
                    'bytes' => $bytes,
                ];
                $manifest['dataset']['total_bytes'] += $bytes;
            }

            $manifest['dataset']['thumbnail_count'] = count($manifest['files']);
            $this->writeManifest(
                $resolvedDestination . '/manifest.json',
                $this->encodeManifest($manifest)
            );
        } catch (Throwable $exception) {
            $this->removeOwnedDestination($resolvedDestination);
            throw $exception instanceof ThumbnailExportException
                ? $exception
                : new ThumbnailExportException('Thumbnail export failed');
        }

        return $manifest;
    }

    private function loadFilenames(): array
    {
        if (!is_dir($this->thumbnailDirectory)
            || is_link($this->thumbnailDirectory)
            || !is_readable($this->thumbnailDirectory)
        ) {
            throw new ThumbnailExportException('Thumbnail source directory is unavailable', 409);
        }

        $entries = scandir($this->thumbnailDirectory);
        if ($entries === false) {
            throw new ThumbnailExportException('Could not read thumbnail source directory', 409);
        }

        $filenames = [];
        foreach ($entries as $filename) {
            if ($filename === '.' || $filename === '..') {
                continue;
            }

            $path = $this->thumbnailDirectory . '/' . $filename;
            $imdb = str_ends_with($filename, '.jpg') ? substr($filename, 0, -4) : null;
            if (!is_string($imdb) || !ThumbnailService::isValidImdb($imdb)) {
                throw new ThumbnailExportException(
                    'Unexpected thumbnail source entry: ' . $filename,
                    409
                );
            }
            if (is_link($path)) {
                throw new ThumbnailExportException('Thumbnail symlinks are not supported: ' . $filename, 409);
            }

            $stat = lstat($path);
            if ($stat === false
                || ($stat['mode'] & 0170000) !== 0100000
                || !is_readable($path)
            ) {
                throw new ThumbnailExportException(
                    'Thumbnail source entry is not a readable regular file: ' . $filename,
                    409
                );
            }
            $filenames[] = $filename;
        }

        sort($filenames, SORT_STRING);
        return $filenames;
    }

    private function stageFile(string $sourcePath, string $destinationPath): void
    {
        if (is_link($sourcePath)) {
            throw new ThumbnailExportException('Thumbnail source changed during export', 409);
        }

        $source = @fopen($sourcePath, 'rb');
        if ($source === false) {
            throw new ThumbnailExportException('Could not read thumbnail source during export', 409);
        }

        $destination = null;
        try {
            $openedStat = fstat($source);
            $pathStat = lstat($sourcePath);
            if ($openedStat === false
                || $pathStat === false
                || ($openedStat['mode'] & 0170000) !== 0100000
                || ($pathStat['mode'] & 0170000) !== 0100000
                || $openedStat['dev'] !== $pathStat['dev']
                || $openedStat['ino'] !== $pathStat['ino']
            ) {
                throw new ThumbnailExportException('Thumbnail source changed during export', 409);
            }

            $destination = @fopen($destinationPath, 'xb');
            if ($destination === false) {
                throw new ThumbnailExportException('Could not create staged thumbnail');
            }
            $copied = stream_copy_to_stream($source, $destination);
            if ($copied === false || $copied !== $openedStat['size'] || !fflush($destination)) {
                throw new ThumbnailExportException('Could not copy complete thumbnail bytes');
            }
        } finally {
            if (is_resource($destination)) {
                fclose($destination);
            }
            fclose($source);
        }

        if (!@chmod($destinationPath, 0644)) {
            throw new ThumbnailExportException('Could not set staged thumbnail permissions');
        }
    }

    private function buildManifest(): array
    {
        $revision = ($this->revisionResolver)();
        if (!is_string($revision)
            || preg_match('/^[a-fA-F0-9]{40}([a-fA-F0-9]{24})?$/', $revision) !== 1
        ) {
            $revision = null;
        } else {
            $revision = strtolower($revision);
        }

        return [
            'contract_version' => 1,
            'created_at' => PublicationTimestamp::format(($this->clock)()),
            'server_revision' => $revision,
            'dataset' => [
                'thumbnail_count' => 0,
                'total_bytes' => 0,
            ],
            'files' => [],
        ];
    }

    private function assertJpeg(string $path, string $filename): void
    {
        $imageInfo = @getimagesize($path);
        if ($imageInfo === false || ($imageInfo[2] ?? null) !== IMAGETYPE_JPEG) {
            throw new ThumbnailExportException(
                'Thumbnail source is not a valid JPEG: ' . $filename,
                409
            );
        }

        $image = new \Imagick();
        try {
            $image->readImage($path);
            if (strtoupper($image->getImageFormat()) !== 'JPEG') {
                throw new ThumbnailExportException(
                    'Thumbnail source is not a valid JPEG: ' . $filename,
                    409
                );
            }
        } catch (ThumbnailExportException $exception) {
            throw $exception;
        } catch (\ImagickException $exception) {
            throw new ThumbnailExportException(
                'Thumbnail source is not a decodable JPEG: ' . $filename,
                409
            );
        } finally {
            $image->clear();
            $image->destroy();
        }
    }

    private function validateDestination(string $destination): string
    {
        if ($destination === '' || str_contains($destination, "\0")) {
            throw new ThumbnailExportException('Thumbnail export destination is required', 400);
        }
        $trimmed = rtrim($destination, DIRECTORY_SEPARATOR);
        if ($trimmed === '' || basename($trimmed) === '.' || basename($trimmed) === '..') {
            throw new ThumbnailExportException('Thumbnail export destination is unsafe', 400);
        }
        if (file_exists($trimmed) || is_link($trimmed)) {
            throw new ThumbnailExportException(
                'Thumbnail export destination must not already exist',
                409
            );
        }

        $parent = realpath(dirname($trimmed));
        if ($parent === false || !is_dir($parent) || !is_writable($parent)) {
            throw new ThumbnailExportException(
                'Thumbnail export destination parent is not writable',
                400
            );
        }
        $resolved = $parent . DIRECTORY_SEPARATOR . basename($trimmed);
        $sourceRoot = realpath($this->thumbnailDirectory);
        if ($sourceRoot !== false
            && ($resolved === $sourceRoot
                || str_starts_with($resolved, $sourceRoot . DIRECTORY_SEPARATOR))
        ) {
            throw new ThumbnailExportException(
                'Thumbnail export destination cannot be inside the thumbnail source',
                400
            );
        }

        return $resolved;
    }

    private function writeManifest(string $path, string $contents): void
    {
        $bytes = file_put_contents($path, $contents, LOCK_EX);
        if ($bytes !== strlen($contents) || !chmod($path, 0644)) {
            throw new ThumbnailExportException('Could not write complete thumbnail export manifest');
        }
    }

    private function encodeManifest(array $manifest): string
    {
        try {
            return json_encode(
                $manifest,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
            ) . "\n";
        } catch (JsonException $exception) {
            throw new ThumbnailExportException('Could not encode thumbnail export manifest');
        }
    }

    private function resolveServerRevision(): ?string
    {
        $gitPath = $this->serverRoot . '/.git';
        if (is_file($gitPath)) {
            $gitFile = file_get_contents($gitPath);
            if ($gitFile === false || preg_match('/^gitdir: (.+)\s*$/', $gitFile, $matches) !== 1) {
                return null;
            }
            $gitPath = $matches[1];
            if (!str_starts_with($gitPath, DIRECTORY_SEPARATOR)) {
                $gitPath = $this->serverRoot . '/' . $gitPath;
            }
        }
        if (!is_dir($gitPath)) {
            return null;
        }

        $head = $this->readTrimmedFile($gitPath . '/HEAD');
        if ($head === null) {
            return null;
        }
        if (preg_match('/^[a-fA-F0-9]{40}([a-fA-F0-9]{24})?$/', $head) === 1) {
            return strtolower($head);
        }
        if (preg_match('#^ref: (refs/[A-Za-z0-9._/-]+)$#', $head, $matches) !== 1
            || str_contains($matches[1], '..')
        ) {
            return null;
        }

        $revision = $this->readTrimmedFile($gitPath . '/' . $matches[1]);
        if ($revision === null) {
            $revision = $this->revisionFromPackedRefs($gitPath, $matches[1]);
        }
        return is_string($revision)
            && preg_match('/^[a-fA-F0-9]{40}([a-fA-F0-9]{24})?$/', $revision) === 1
                ? strtolower($revision)
                : null;
    }

    private function readTrimmedFile(string $path): ?string
    {
        if (!is_file($path) || !is_readable($path)) {
            return null;
        }
        $contents = file_get_contents($path);
        return $contents === false ? null : trim($contents);
    }

    private function revisionFromPackedRefs(string $gitPath, string $reference): ?string
    {
        $packedRefs = $this->readTrimmedFile($gitPath . '/packed-refs');
        if ($packedRefs === null) {
            return null;
        }
        foreach (preg_split('/\R/', $packedRefs) ?: [] as $line) {
            if ($line === '' || $line[0] === '#' || $line[0] === '^') {
                continue;
            }
            [$revision, $name] = array_pad(explode(' ', $line, 2), 2, null);
            if ($name === $reference) {
                return $revision;
            }
        }
        return null;
    }

    private function removeOwnedDestination(string $destination): void
    {
        if (!is_dir($destination) || is_link($destination)) {
            return;
        }
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($destination, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($destination);
    }
}
