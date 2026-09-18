<?php

require_once __DIR__ . '/../public/api/admin/ThumbnailService.php';
require_once __DIR__ . '/../public/api/admin/ThumbnailUploadService.php';

use FreeTV\Admin\ThumbnailUploadException;
use FreeTV\Admin\ThumbnailUploadService;

function assertUploadSame($expected, $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException(
            $message . "\nExpected: " . var_export($expected, true)
            . "\nActual: " . var_export($actual, true)
        );
    }
}

function makeTestImage(string $path, int $width, int $height, string $format): void
{
    $image = new Imagick();
    $image->newImage($width, $height, new ImagickPixel('#336699'));
    $image->setImageFormat($format);
    $image->writeImage($path);
    $image->clear();
    $image->destroy();
}

function expectUploadError(callable $callback, int $status, string $message): void
{
    try {
        $callback();
    } catch (ThumbnailUploadException $e) {
        assertUploadSame($status, $e->getHttpStatus(), $message);
        return;
    }

    throw new RuntimeException($message . ': expected ThumbnailUploadException');
}

$testDirectory = sys_get_temp_dir() . '/freetv-thumbnail-test-' . bin2hex(random_bytes(8));
if (!mkdir($testDirectory, 0775, true)) {
    throw new RuntimeException('Could not create thumbnail test directory');
}

$sourceDirectory = $testDirectory . '/source';
$thumbnailDirectory = $testDirectory . '/thumbs';
$undoDirectory = $testDirectory . '/undo';
mkdir($sourceDirectory, 0775, true);
mkdir($thumbnailDirectory, 0775, true);
mkdir($undoDirectory, 0700, true);

try {
    $service = new ThumbnailUploadService($thumbnailDirectory, $undoDirectory);
    $largeJpeg = $sourceDirectory . '/large.jpg';
    makeTestImage($largeJpeg, 1200, 1600, 'jpeg');

    $uploaded = $service->store('tt10000001', $largeJpeg, filesize($largeJpeg), 'upload');
    assertUploadSame(1000, $uploaded['width'], 'Oversized JPEG width was not normalized');
    assertUploadSame(1333, $uploaded['height'], 'Oversized JPEG aspect ratio was not preserved');
    assertUploadSame(
        $thumbnailDirectory . '/tt10000001.jpg',
        realpath($thumbnailDirectory . '/tt10000001.jpg'),
        'Upload did not write the canonical IMDb filename'
    );
    assertUploadSame(
        0644,
        fileperms($thumbnailDirectory . '/tt10000001.jpg') & 0777,
        'Canonical thumbnail permissions should allow static serving'
    );

    $originalHash = hash_file('sha256', $thumbnailDirectory . '/tt10000001.jpg');
    expectUploadError(
        fn() => $service->store('tt10000001', $largeJpeg, filesize($largeJpeg), 'upload'),
        409,
        'Normal upload should reject an existing thumbnail'
    );
    assertUploadSame(
        $originalHash,
        hash_file('sha256', $thumbnailDirectory . '/tt10000001.jpg'),
        'Rejected upload changed the existing thumbnail'
    );

    $pngSource = $sourceDirectory . '/source.png';
    $png = $sourceDirectory . '/renamed.jpg';
    makeTestImage($pngSource, 400, 600, 'png');
    rename($pngSource, $png);
    expectUploadError(
        fn() => $service->store('tt10000001', $png, filesize($png), 'replace'),
        400,
        'A renamed PNG should be rejected'
    );
    assertUploadSame(
        $originalHash,
        hash_file('sha256', $thumbnailDirectory . '/tt10000001.jpg'),
        'Invalid replacement changed the existing thumbnail'
    );

    $corrupt = $sourceDirectory . '/corrupt.jpg';
    file_put_contents($corrupt, "\xff\xd8not-a-decodable-jpeg");
    expectUploadError(
        fn() => $service->store('tt10000001', $corrupt, filesize($corrupt), 'replace'),
        400,
        'A corrupt JPEG should be rejected'
    );
    assertUploadSame(
        $originalHash,
        hash_file('sha256', $thumbnailDirectory . '/tt10000001.jpg'),
        'Corrupt replacement changed the existing thumbnail'
    );

    $smallJpeg = $sourceDirectory . '/small.jpg';
    makeTestImage($smallJpeg, 467, 668, 'jpeg');
    $replaced = $service->store('tt10000001', $smallJpeg, filesize($smallJpeg), 'replace');
    assertUploadSame(467, $replaced['width'], 'Small JPEG was upscaled');
    assertUploadSame(668, $replaced['height'], 'Small JPEG aspect ratio changed');

    expectUploadError(
        fn() => $service->store('tt10000002', $smallJpeg, filesize($smallJpeg), 'replace'),
        409,
        'Replace should require an existing thumbnail'
    );
    expectUploadError(
        fn() => $service->store(
            'tt10000002',
            $smallJpeg,
            ThumbnailUploadService::MAX_UPLOAD_BYTES + 1,
            'upload'
        ),
        413,
        'Uploads above 10 MB should be rejected before processing'
    );
} finally {
    foreach (glob($sourceDirectory . '/*') ?: [] as $path) {
        unlink($path);
    }
    foreach (glob($thumbnailDirectory . '/*') ?: [] as $path) {
        unlink($path);
    }
    foreach (glob($undoDirectory . '/*') ?: [] as $path) {
        unlink($path);
    }
    foreach (glob($undoDirectory . '/.*') ?: [] as $path) {
        if (is_file($path)) unlink($path);
    }
    rmdir($sourceDirectory);
    rmdir($thumbnailDirectory);
    rmdir($undoDirectory);
    rmdir($testDirectory);
}

echo "ThumbnailUploadService tests passed\n";
