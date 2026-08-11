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

    public function __construct(private ?string $thumbnailDirectory = null)
    {
        $this->thumbnailDirectory ??= dirname(__DIR__, 2) . '/thumbs';
    }

    public function store(
        string $imdb,
        string $sourcePath,
        int $sourceSize,
        string $operation
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

        $this->ensureThumbnailDirectory();
        $targetPath = $this->thumbnailDirectory . '/' . $imdb . '.jpg';
        $lockPath = sys_get_temp_dir() . '/freetv-thumbnail-' . hash('sha256', $targetPath) . '.lock';
        $lock = @fopen($lockPath, 'c');
        if ($lock === false || !flock($lock, LOCK_EX)) {
            if (is_resource($lock)) {
                fclose($lock);
            }
            throw new ThumbnailUploadException('Could not lock the thumbnail for writing', 500);
        }

        $processedPath = null;
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
            $this->assertOperationAllowed($targetPath, $operation);

            if ($operation === 'upload') {
                if (!@link($processedPath, $targetPath)) {
                    if (is_file($targetPath)) {
                        throw new ThumbnailUploadException('A thumbnail already exists for this IMDb ID', 409);
                    }
                    throw new ThumbnailUploadException('Could not save the thumbnail', 500);
                }
                @unlink($processedPath);
            } elseif (!@rename($processedPath, $targetPath)) {
                throw new ThumbnailUploadException('Could not replace the thumbnail', 500);
            } else {
                $processedPath = null;
            }

            clearstatcache(true, $targetPath);
            $fingerprint = hash_file('sha256', $targetPath);
            if ($fingerprint === false) {
                throw new ThumbnailUploadException('Could not verify the saved thumbnail', 500);
            }

            return [
                'imdb' => $imdb,
                'operation' => $operation,
                'thumbnail_url' => '/thumbs/' . $imdb . '.jpg?v=' . substr($fingerprint, 0, 12),
                'width' => $dimensions['width'],
                'height' => $dimensions['height'],
                'bytes' => filesize($targetPath),
            ];
        } finally {
            if ($processedPath !== null && is_file($processedPath)) {
                @unlink($processedPath);
            }
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    private function ensureThumbnailDirectory(): void
    {
        if (!is_dir($this->thumbnailDirectory)
            && !mkdir($this->thumbnailDirectory, 0775, true)
            && !is_dir($this->thumbnailDirectory)
        ) {
            throw new ThumbnailUploadException('Could not create the thumbnail directory', 500);
        }
        if (!is_writable($this->thumbnailDirectory)) {
            throw new ThumbnailUploadException('The thumbnail directory is not writable', 500);
        }
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
