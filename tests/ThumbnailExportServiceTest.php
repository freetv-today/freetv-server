<?php

require_once __DIR__ . '/../public/api/admin/publication/ThumbnailExportService.php';

use FreeTV\Admin\Publication\ThumbnailExportException;
use FreeTV\Admin\Publication\ThumbnailExportService;

function assertThumbnailExportSame(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException(
            $message . "\nExpected: " . var_export($expected, true)
            . "\nActual: " . var_export($actual, true)
        );
    }
}

function expectThumbnailExportFailure(callable $operation, string $message): void
{
    try {
        $operation();
    } catch (ThumbnailExportException $exception) {
        return;
    }
    throw new RuntimeException($message);
}

function thumbnailExportTreeHashes(string $root): array
{
    if (!is_dir($root)) {
        return [];
    }

    $hashes = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($iterator as $item) {
        if ($item->isFile() && !$item->isLink()) {
            $relative = substr($item->getPathname(), strlen($root) + 1);
            $hashes[$relative] = hash_file('sha256', $item->getPathname());
        }
    }
    ksort($hashes, SORT_STRING);
    return $hashes;
}

function removeThumbnailExportTree(string $root): void
{
    if (!is_dir($root)) {
        return;
    }
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iterator as $item) {
        $item->isDir() && !$item->isLink()
            ? rmdir($item->getPathname())
            : unlink($item->getPathname());
    }
    rmdir($root);
}

function writeThumbnailExportJpeg(string $path, string $color): void
{
    $image = new Imagick();
    $image->newImage(8, 12, new ImagickPixel($color));
    $image->setImageFormat('jpeg');
    $image->writeImage($path);
    $image->clear();
    $image->destroy();
}

$testRoot = sys_get_temp_dir() . '/freetv-thumbnail-export-test-' . bin2hex(random_bytes(8));
$thumbnailRoot = $testRoot . '/thumbs';
$undoRoot = $testRoot . '/thumbnail-undo';
if (!mkdir($thumbnailRoot, 0700, true) || !mkdir($undoRoot, 0700, true)) {
    throw new RuntimeException('Could not create thumbnail export test directories');
}
file_put_contents($undoRoot . '/sentinel', 'unchanged thumbnail Undo state');

$clock = static fn(): DateTimeImmutable => new DateTimeImmutable('2026-08-24T09:10:11.123Z');
$revision = static fn(): string => str_repeat('A', 40);
$newService = static fn(?callable $revisionResolver = null, ?callable $fileStager = null) =>
    new ThumbnailExportService($thumbnailRoot, $clock, $revisionResolver ?? $revision, $fileStager);

try {
    $sourceBefore = thumbnailExportTreeHashes($thumbnailRoot);
    $undoBefore = thumbnailExportTreeHashes($undoRoot);
    $emptyDestination = $testRoot . '/empty-export';
    $emptyManifest = $newService()->export($emptyDestination);
    assertThumbnailExportSame([
        'contract_version' => 1,
        'created_at' => '2026-08-24T09:10:11.123Z',
        'server_revision' => str_repeat('a', 40),
        'dataset' => ['thumbnail_count' => 0, 'total_bytes' => 0],
        'files' => [],
    ], $emptyManifest, 'Empty thumbnail export manifest is incorrect');
    assertThumbnailExportSame(true, is_dir($emptyDestination . '/thumbs'),
        'Empty thumbnail export did not create the thumbs directory');
    assertThumbnailExportSame($emptyManifest, json_decode(
        (string) file_get_contents($emptyDestination . '/manifest.json'),
        true,
        512,
        JSON_THROW_ON_ERROR
    ), 'Empty staged thumbnail manifest differs from the returned manifest');
    assertThumbnailExportSame($sourceBefore, thumbnailExportTreeHashes($thumbnailRoot),
        'Empty export modified the thumbnail source');
    assertThumbnailExportSame($undoBefore, thumbnailExportTreeHashes($undoRoot),
        'Empty export modified thumbnail Undo state');

    writeThumbnailExportJpeg($thumbnailRoot . '/tt7654321.jpg', '#336699');
    writeThumbnailExportJpeg($thumbnailRoot . '/tt0052520.jpg', '#993366');
    $thumbnailBytes = [
        'tt7654321.jpg' => file_get_contents($thumbnailRoot . '/tt7654321.jpg'),
        'tt0052520.jpg' => file_get_contents($thumbnailRoot . '/tt0052520.jpg'),
    ];
    $sourceBefore = thumbnailExportTreeHashes($thumbnailRoot);
    $undoBefore = thumbnailExportTreeHashes($undoRoot);

    $destination = $testRoot . '/export';
    $manifest = $newService()->export($destination);
    $expectedFiles = [];
    foreach (['tt0052520.jpg', 'tt7654321.jpg'] as $filename) {
        $expectedFiles[] = [
            'path' => 'thumbs/' . $filename,
            'sha256' => hash('sha256', $thumbnailBytes[$filename]),
            'bytes' => strlen($thumbnailBytes[$filename]),
        ];
    }
    assertThumbnailExportSame($expectedFiles, $manifest['files'],
        'Thumbnail manifest files are not deterministic or content-addressed');
    assertThumbnailExportSame([
        'thumbnail_count' => 2,
        'total_bytes' => array_sum(array_map('strlen', $thumbnailBytes)),
    ], $manifest['dataset'], 'Thumbnail dataset summary is incorrect');
    assertThumbnailExportSame($manifest, json_decode(
        (string) file_get_contents($destination . '/manifest.json'),
        true,
        512,
        JSON_THROW_ON_ERROR
    ), 'Returned and staged thumbnail manifests differ');
    foreach ($thumbnailBytes as $filename => $contents) {
        assertThumbnailExportSame($contents, file_get_contents($destination . '/thumbs/' . $filename),
            'Thumbnail export did not preserve exact source bytes');
    }
    assertThumbnailExportSame($sourceBefore, thumbnailExportTreeHashes($thumbnailRoot),
        'Thumbnail export modified source bytes');
    assertThumbnailExportSame($undoBefore, thumbnailExportTreeHashes($undoRoot),
        'Thumbnail export modified thumbnail Undo state');

    touch($thumbnailRoot . '/tt0052520.jpg', 1_800_000_000);
    $touchedManifest = $newService()->export($testRoot . '/touched-export');
    assertThumbnailExportSame($manifest['files'], $touchedManifest['files'],
        'Filesystem mtime changed thumbnail content identity');

    $nullRevisionManifest = $newService(static fn(): ?string => null)
        ->export($testRoot . '/null-revision');
    assertThumbnailExportSame(null, $nullRevisionManifest['server_revision'],
        'Unavailable server revision did not use null');

    $invalidEntries = [
        'poster.jpg' => 'invalid IMDb filename',
        'notes.txt' => 'non-JPEG file',
        '.thumbnail-temporary' => 'hidden temporary file',
    ];
    foreach ($invalidEntries as $filename => $contents) {
        file_put_contents($thumbnailRoot . '/' . $filename, $contents);
        $invalidDestination = $testRoot . '/invalid-' . md5($filename);
        expectThumbnailExportFailure(
            fn() => $newService()->export($invalidDestination),
            'Unexpected thumbnail source entry was accepted: ' . $filename
        );
        assertThumbnailExportSame(false, file_exists($invalidDestination),
            'Invalid source entry left an export destination');
        unlink($thumbnailRoot . '/' . $filename);
    }

    file_put_contents($thumbnailRoot . '/tt9999997.jpg', 'not actually a JPEG');
    expectThumbnailExportFailure(
        fn() => $newService()->export($testRoot . '/invalid-jpeg-bytes'),
        'Non-JPEG bytes with a valid thumbnail filename were accepted'
    );
    assertThumbnailExportSame(false, file_exists($testRoot . '/invalid-jpeg-bytes'),
        'Invalid JPEG bytes left an export destination');
    unlink($thumbnailRoot . '/tt9999997.jpg');

    mkdir($thumbnailRoot . '/tt9999998.jpg', 0700);
    expectThumbnailExportFailure(
        fn() => $newService()->export($testRoot . '/directory-entry'),
        'Thumbnail directory entry was accepted as a regular JPEG'
    );
    assertThumbnailExportSame(false, file_exists($testRoot . '/directory-entry'),
        'Thumbnail directory entry left an export destination');
    rmdir($thumbnailRoot . '/tt9999998.jpg');

    file_put_contents($testRoot . '/outside.jpg', 'outside');
    symlink($testRoot . '/outside.jpg', $thumbnailRoot . '/tt9999999.jpg');
    expectThumbnailExportFailure(
        fn() => $newService()->export($testRoot . '/symlink-entry'),
        'Thumbnail symlink was followed or exported'
    );
    assertThumbnailExportSame(false, file_exists($testRoot . '/symlink-entry'),
        'Thumbnail symlink left an export destination');
    unlink($thumbnailRoot . '/tt9999999.jpg');

    mkdir($testRoot . '/existing-destination', 0700);
    file_put_contents($testRoot . '/existing-destination/keep', 'keep');
    expectThumbnailExportFailure(
        fn() => $newService()->export($testRoot . '/existing-destination'),
        'Existing thumbnail export destination was accepted'
    );
    assertThumbnailExportSame('keep', file_get_contents($testRoot . '/existing-destination/keep'),
        'Rejected existing destination was modified');
    expectThumbnailExportFailure(
        fn() => $newService()->export($thumbnailRoot . '/nested-export'),
        'Destination inside the thumbnail source was accepted'
    );
    assertThumbnailExportSame(false, file_exists($thumbnailRoot . '/nested-export'),
        'Unsafe source-contained destination was created');

    $failingService = $newService(
        null,
        static function (string $source, string $destination): void {
            file_put_contents($destination, 'partial');
            throw new RuntimeException('Injected staging failure');
        }
    );
    $failedDestination = $testRoot . '/failed-export';
    expectThumbnailExportFailure(
        fn() => $failingService->export($failedDestination),
        'Staging failure did not fail thumbnail export'
    );
    assertThumbnailExportSame(false, file_exists($failedDestination),
        'Staging failure left a successful-looking partial export');
    assertThumbnailExportSame($sourceBefore, thumbnailExportTreeHashes($thumbnailRoot),
        'Failure handling modified source thumbnail bytes');
    assertThumbnailExportSame($undoBefore, thumbnailExportTreeHashes($undoRoot),
        'Failure handling modified thumbnail Undo state');
} finally {
    removeThumbnailExportTree($testRoot);
}

echo "ThumbnailExportService tests passed\n";
