<?php

namespace FreeTV\Admin;

class ThumbnailUploadException extends \RuntimeException
{
    public function __construct(string $message, private int $httpStatus = 400)
    {
        parent::__construct($message);
    }

    public function getHttpStatus(): int
    {
        return $this->httpStatus;
    }
}

class ThumbnailUploadService
{
    public const MAX_UPLOAD_BYTES = 10 * 1024 * 1024;
    public const MAX_WIDTH = 1000;
    private const MAX_PIXELS = 40_000_000;
    private const JPEG_QUALITY = 85;
    private const TOKEN_PATTERN = '/^[a-f0-9]{64}$/';

    public function __construct(
        private ?string $thumbnailDirectory = null,
        private ?string $undoDirectory = null
    ) {
        $this->thumbnailDirectory ??= dirname(__DIR__, 2) . '/thumbs';
        $this->undoDirectory ??= dirname(__DIR__, 3) . '/temp/thumbnail-undo';
    }

    public function store(
        string $imdb,
        string $sourcePath,
        int $sourceSize,
        string $operation,
        ?string $previousUndoToken = null
    ): array {
        if (!ThumbnailService::isValidImdb($imdb)) {
            throw new ThumbnailUploadException('Invalid IMDb ID');
        }
        if (!in_array($operation, ['upload', 'replace'], true)) {
            throw new ThumbnailUploadException('Operation must be upload or replace');
        }
        if ($sourceSize < 1) {
            throw new ThumbnailUploadException('The uploaded JPEG is empty');
        }
        if ($sourceSize > self::MAX_UPLOAD_BYTES) {
            throw new ThumbnailUploadException('The uploaded JPEG exceeds the 10 MB limit', 413);
        }
        if (!is_file($sourcePath) || !is_readable($sourcePath)) {
            throw new ThumbnailUploadException('The uploaded file could not be read');
        }

        $this->ensureDirectories();
        $targetPath = $this->thumbnailPath($imdb);
        $lock = $this->acquireLock($imdb);
        $processedPath = null;
        $undoToken = null;

        try {
            $this->assertOperationAllowed($targetPath, $operation);
            $processedPath = tempnam($this->thumbnailDirectory, '.thumbnail-');
            if ($processedPath === false) {
                throw new ThumbnailUploadException('Could not create a temporary thumbnail', 500);
            }

            $dimensions = $this->normalizeJpeg($sourcePath, $processedPath);
            if (!@chmod($processedPath, 0644)) {
                throw new ThumbnailUploadException('Could not set thumbnail permissions', 500);
            }

            $expectedHash = hash_file('sha256', $processedPath);
            if ($expectedHash === false) {
                throw new ThumbnailUploadException('Could not verify the processed thumbnail', 500);
            }

            $this->assertOperationAllowed($targetPath, $operation);
            $undoToken = $this->createUndoState(
                $imdb,
                $operation,
                $expectedHash,
                $operation === 'replace' ? $targetPath : null
            );

            try {
                if ($operation === 'upload') {
                    if (!@link($processedPath, $targetPath)) {
                        if (is_file($targetPath)) {
                            throw new ThumbnailUploadException(
                                'A thumbnail already exists for this IMDb ID',
                                409
                            );
                        }
                        throw new ThumbnailUploadException('Could not save the thumbnail', 500);
                    }
                    @unlink($processedPath);
                } elseif (!@rename($processedPath, $targetPath)) {
                    throw new ThumbnailUploadException('Could not replace the thumbnail', 500);
                } else {
                    $processedPath = null;
                }
            } catch (\Throwable $e) {
                $this->discardUndo($undoToken);
                throw $e;
            }

            clearstatcache(true, $targetPath);
            $fingerprint = hash_file('sha256', $targetPath);
            if ($fingerprint === false || !hash_equals($expectedHash, $fingerprint)) {
                throw new ThumbnailUploadException('Could not verify the saved thumbnail', 500);
            }

            if ($previousUndoToken !== null && $previousUndoToken !== $undoToken) {
                $this->discardUndo($previousUndoToken);
            }

            return [
                'imdb' => $imdb,
                'operation' => $operation,
                'thumbnail_url' => $this->thumbnailUrl($imdb, $fingerprint),
                'width' => $dimensions['width'],
                'height' => $dimensions['height'],
                'bytes' => filesize($targetPath),
                'undo_token' => $undoToken,
            ];
        } finally {
            if ($processedPath !== null && is_file($processedPath)) {
                @unlink($processedPath);
            }
            $this->releaseLock($lock);
        }
    }

    public function undo(string $token): array
    {
        if (!$this->isValidToken($token)) {
            throw new ThumbnailUploadException('Invalid undo token');
        }

        $this->ensureDirectories();
        $state = $this->readUndoState($token);
        $imdb = $state['imdb'];
        $lock = $this->acquireLock($imdb);
        $restorePath = null;
        $consumingPath = null;

        try {
            $state = $this->readUndoState($token);
            $targetPath = $this->thumbnailPath($imdb);
            if (!is_file($targetPath)) {
                throw new ThumbnailUploadException(
                    'The live thumbnail no longer matches this undo operation',
                    409
                );
            }

            $currentHash = hash_file('sha256', $targetPath);
            if ($currentHash === false || !hash_equals($state['expected_hash'], $currentHash)) {
                throw new ThumbnailUploadException(
                    'The live thumbnail no longer matches this undo operation',
                    409
                );
            }

            if ($state['operation'] === 'replace') {
                $backupPath = $this->backupPath($token);
                if (!is_file($backupPath)) {
                    throw new ThumbnailUploadException('Undo state is missing or expired', 404);
                }
                $backupHash = hash_file('sha256', $backupPath);
                if ($backupHash === false || !hash_equals($state['backup_hash'], $backupHash)) {
                    throw new ThumbnailUploadException('Undo state is missing or expired', 404);
                }

                $restorePath = tempnam($this->thumbnailDirectory, '.thumbnail-restore-');
                if ($restorePath === false || !@copy($backupPath, $restorePath)) {
                    throw new ThumbnailUploadException('Could not prepare the prior thumbnail', 500);
                }
                if (!@chmod($restorePath, 0644)) {
                    throw new ThumbnailUploadException('Could not set thumbnail permissions', 500);
                }
            }

            $metadataPath = $this->metadataPath($token);
            $consumingPath = $this->undoDirectory . '/.' . $token . '.consuming';
            if (!@rename($metadataPath, $consumingPath)) {
                throw new ThumbnailUploadException('Undo state is missing or expired', 404);
            }

            if ($state['operation'] === 'upload') {
                if (!@unlink($targetPath)) {
                    @rename($consumingPath, $metadataPath);
                    $consumingPath = null;
                    throw new ThumbnailUploadException('Could not remove the uploaded thumbnail', 500);
                }
                $exists = false;
                $thumbnailUrl = null;
            } else {
                if (!@rename($restorePath, $targetPath)) {
                    @rename($consumingPath, $metadataPath);
                    $consumingPath = null;
                    throw new ThumbnailUploadException('Could not restore the prior thumbnail', 500);
                }
                $restorePath = null;
                clearstatcache(true, $targetPath);
                $restoredHash = hash_file('sha256', $targetPath);
                if ($restoredHash === false || !hash_equals($state['backup_hash'], $restoredHash)) {
                    throw new ThumbnailUploadException('Could not verify the restored thumbnail', 500);
                }
                $exists = true;
                $thumbnailUrl = $this->thumbnailUrl($imdb, $restoredHash);
            }

            @unlink($consumingPath);
            $consumingPath = null;
            @unlink($this->backupPath($token));

            return [
                'imdb' => $imdb,
                'operation' => 'undo',
                'undone_operation' => $state['operation'],
                'exists' => $exists,
                'thumbnail_url' => $thumbnailUrl,
                'undo_token' => null,
            ];
        } finally {
            if ($restorePath !== null && is_file($restorePath)) {
                @unlink($restorePath);
            }
            $this->releaseLock($lock);
        }
    }

    public function discardUndo(string $token): void
    {
        if (!$this->isValidToken($token)) {
            return;
        }

        @unlink($this->metadataPath($token));
        @unlink($this->backupPath($token));
    }

    private function ensureDirectories(): void
    {
        $this->ensureDirectory($this->thumbnailDirectory, 0775, 'thumbnail');
        $this->ensureDirectory($this->undoDirectory, 0700, 'thumbnail undo');
    }

    private function ensureDirectory(string $directory, int $permissions, string $label): void
    {
        if (!is_dir($directory)
            && !mkdir($directory, $permissions, true)
            && !is_dir($directory)
        ) {
            throw new ThumbnailUploadException("Could not create the {$label} directory", 500);
        }
        if (!is_writable($directory)) {
            throw new ThumbnailUploadException("The {$label} directory is not writable", 500);
        }
    }

    private function acquireLock(string $imdb)
    {
        $lockPath = $this->undoDirectory . '/.lock-' . hash('sha256', $imdb);
        $lock = @fopen($lockPath, 'c');
        if ($lock === false || !flock($lock, LOCK_EX)) {
            if (is_resource($lock)) {
                fclose($lock);
            }
            throw new ThumbnailUploadException('Could not lock the thumbnail for writing', 500);
        }

        return $lock;
    }

    private function releaseLock($lock): void
    {
        flock($lock, LOCK_UN);
        fclose($lock);
    }

    private function createUndoState(
        string $imdb,
        string $operation,
        string $expectedHash,
        ?string $previousThumbnailPath
    ): string {
        do {
            $token = bin2hex(random_bytes(32));
        } while (is_file($this->metadataPath($token)) || is_file($this->backupPath($token)));

        $backupHash = null;
        $backupPath = $this->backupPath($token);
        if ($previousThumbnailPath !== null) {
            $temporaryBackup = tempnam($this->undoDirectory, '.backup-');
            if ($temporaryBackup === false || !@copy($previousThumbnailPath, $temporaryBackup)) {
                throw new ThumbnailUploadException('Could not preserve the prior thumbnail', 500);
            }

            try {
                if (!@chmod($temporaryBackup, 0600)) {
                    throw new ThumbnailUploadException('Could not protect the thumbnail backup', 500);
                }
                $sourceHash = hash_file('sha256', $previousThumbnailPath);
                $backupHash = hash_file('sha256', $temporaryBackup);
                if ($sourceHash === false || $backupHash === false || !hash_equals($sourceHash, $backupHash)) {
                    throw new ThumbnailUploadException('Could not verify the thumbnail backup', 500);
                }
                if (!@rename($temporaryBackup, $backupPath)) {
                    throw new ThumbnailUploadException('Could not preserve the prior thumbnail', 500);
                }
                $temporaryBackup = null;
            } finally {
                if ($temporaryBackup !== null && is_file($temporaryBackup)) {
                    @unlink($temporaryBackup);
                }
            }
        }

        $state = [
            'imdb' => $imdb,
            'operation' => $operation,
            'expected_hash' => $expectedHash,
            'backup_hash' => $backupHash,
            'created_at' => gmdate('c'),
        ];
        $temporaryMetadata = tempnam($this->undoDirectory, '.undo-');

        try {
            $encoded = json_encode($state, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
            if ($temporaryMetadata === false
                || file_put_contents($temporaryMetadata, $encoded, LOCK_EX) === false
                || !@chmod($temporaryMetadata, 0600)
                || !@rename($temporaryMetadata, $this->metadataPath($token))
            ) {
                throw new ThumbnailUploadException('Could not create thumbnail undo state', 500);
            }
            $temporaryMetadata = null;
        } catch (\JsonException $e) {
            throw new ThumbnailUploadException('Could not create thumbnail undo state', 500);
        } finally {
            if ($temporaryMetadata !== null && is_file($temporaryMetadata)) {
                @unlink($temporaryMetadata);
            }
            if (!is_file($this->metadataPath($token))) {
                @unlink($backupPath);
            }
        }

        return $token;
    }

    private function readUndoState(string $token): array
    {
        $contents = @file_get_contents($this->metadataPath($token));
        if ($contents === false) {
            throw new ThumbnailUploadException('Undo state is missing or expired', 404);
        }

        try {
            $state = json_decode($contents, true, 16, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new ThumbnailUploadException('Undo state is missing or expired', 404);
        }

        if (!is_array($state)
            || !ThumbnailService::isValidImdb($state['imdb'] ?? null)
            || !in_array($state['operation'] ?? null, ['upload', 'replace'], true)
            || !is_string($state['expected_hash'] ?? null)
            || preg_match('/^[a-f0-9]{64}$/', $state['expected_hash']) !== 1
            || ($state['operation'] === 'replace'
                && (!is_string($state['backup_hash'] ?? null)
                    || preg_match('/^[a-f0-9]{64}$/', $state['backup_hash']) !== 1))
        ) {
            throw new ThumbnailUploadException('Undo state is missing or expired', 404);
        }

        return $state;
    }

    private function isValidToken(string $token): bool
    {
        return preg_match(self::TOKEN_PATTERN, $token) === 1;
    }

    private function metadataPath(string $token): string
    {
        return $this->undoDirectory . '/' . $token . '.json';
    }

    private function backupPath(string $token): string
    {
        return $this->undoDirectory . '/' . $token . '.jpg';
    }

    private function thumbnailPath(string $imdb): string
    {
        return $this->thumbnailDirectory . '/' . $imdb . '.jpg';
    }

    private function thumbnailUrl(string $imdb, string $hash): string
    {
        return '/thumbs/' . $imdb . '.jpg?v=' . substr($hash, 0, 12);
    }

    private function assertOperationAllowed(string $targetPath, string $operation): void
    {
        $exists = is_file($targetPath);
        if ($operation === 'upload' && $exists) {
            throw new ThumbnailUploadException('A thumbnail already exists for this IMDb ID', 409);
        }
        if ($operation === 'replace' && !$exists) {
            throw new ThumbnailUploadException('No thumbnail exists to replace for this IMDb ID', 409);
        }
    }

    private function normalizeJpeg(string $sourcePath, string $outputPath): array
    {
        $sourceInfo = @getimagesize($sourcePath);
        if ($sourceInfo === false || ($sourceInfo[2] ?? null) !== IMAGETYPE_JPEG) {
            throw new ThumbnailUploadException('The uploaded file must be a valid JPEG image');
        }
        if ($sourceInfo[0] < 1 || $sourceInfo[1] < 1
            || $sourceInfo[0] * $sourceInfo[1] > self::MAX_PIXELS
        ) {
            throw new ThumbnailUploadException('The uploaded JPEG dimensions are not supported');
        }

        $image = new \Imagick();
        try {
            $image->readImage($sourcePath);
            if (strtoupper($image->getImageFormat()) !== 'JPEG') {
                throw new ThumbnailUploadException('The uploaded file must be a valid JPEG image');
            }
        } catch (ThumbnailUploadException $e) {
            throw $e;
        } catch (\ImagickException $e) {
            throw new ThumbnailUploadException('The uploaded file is not a decodable JPEG image');
        }

        try {
            $image->setIteratorIndex(0);
            if (method_exists($image, 'autoOrient')) {
                $image->autoOrient();
            } else {
                $image->autoOrientImage();
            }

            if ($image->getImageWidth() > self::MAX_WIDTH) {
                $image->resizeImage(self::MAX_WIDTH, 0, \Imagick::FILTER_LANCZOS, 1);
            }

            $image->stripImage();
            $image->setImagePage(0, 0, 0, 0);
            $image->setImageFormat('jpeg');
            $image->setImageCompression(\Imagick::COMPRESSION_JPEG);
            $image->setImageCompressionQuality(self::JPEG_QUALITY);

            $width = $image->getImageWidth();
            $height = $image->getImageHeight();
            if (!$image->writeImage($outputPath)) {
                throw new ThumbnailUploadException('Could not process the uploaded JPEG', 500);
            }

            return ['width' => $width, 'height' => $height];
        } catch (ThumbnailUploadException $e) {
            throw $e;
        } catch (\ImagickException $e) {
            throw new ThumbnailUploadException('Could not process the uploaded JPEG', 500);
        } finally {
            $image->clear();
            $image->destroy();
        }
    }
}
