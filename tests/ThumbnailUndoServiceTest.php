<?php

require_once __DIR__ . '/../public/api/admin/ThumbnailService.php';
require_once __DIR__ . '/../public/api/admin/ThumbnailUploadService.php';

use FreeTV\Admin\ThumbnailUploadException;
use FreeTV\Admin\ThumbnailUploadService;

function assertUndoSame($expected, $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException(
            $message . "\nExpected: " . var_export($expected, true)
            . "\nActual: " . var_export($actual, true)
        );
    }
}

function makeUndoTestImage(string $path, string $color): void
{
    $image = new Imagick();
    $image->newImage(467, 668, new ImagickPixel($color));
    $image->setImageFormat('jpeg');
    $image->writeImage($path);
    $image->clear();
    $image->destroy();
}

function expectUndoError(callable $callback, int $status, string $message): void
{
    try {
        $callback();
    } catch (ThumbnailUploadException $e) {
        assertUndoSame($status, $e->getHttpStatus(), $message);
        return;
    }

    throw new RuntimeException($message . ': expected ThumbnailUploadException');
}

$testDirectory = sys_get_temp_dir() . '/freetv-thumbnail-undo-test-' . bin2hex(random_bytes(8));
$sourceDirectory = $testDirectory . '/source';
$thumbnailDirectory = $testDirectory . '/thumbs';
$undoDirectory = $testDirectory . '/undo';

foreach ([$sourceDirectory, $thumbnailDirectory, $undoDirectory] as $directory) {
    if (!mkdir($directory, 0700, true)) {
        throw new RuntimeException('Could not create thumbnail undo test directory');
    }
}

try {
    $firstImage = $sourceDirectory . '/first.jpg';
    $secondImage = $sourceDirectory . '/second.jpg';
    makeUndoTestImage($firstImage, '#336699');
    makeUndoTestImage($secondImage, '#993366');
    $service = new ThumbnailUploadService($thumbnailDirectory, $undoDirectory);

    $upload = $service->store('tt20000001', $firstImage, filesize($firstImage), 'upload');
    assertUndoSame(
        1,
        preg_match('/^[a-f0-9]{64}$/', $upload['undo_token']),
        'Upload did not return an opaque undo token'
    );
    $uploadUndo = $service->undo($upload['undo_token']);
    assertUndoSame(false, $uploadUndo['exists'], 'Undo upload should report a missing thumbnail');
    assertUndoSame(
        false,
        is_file($thumbnailDirectory . '/tt20000001.jpg'),
        'Undo upload did not remove the exact canonical thumbnail'
    );
    expectUndoError(
        fn() => $service->undo($upload['undo_token']),
        404,
        'An undo token should not be reusable'
    );

    $initial = $service->store('tt20000002', $firstImage, filesize($firstImage), 'upload');
    $service->discardUndo($initial['undo_token']);
    $initialHash = hash_file('sha256', $thumbnailDirectory . '/tt20000002.jpg');
    $replacement = $service->store(
        'tt20000002',
        $secondImage,
        filesize($secondImage),
        'replace'
    );
    assertUndoSame(
        true,
        is_file($undoDirectory . '/' . $replacement['undo_token'] . '.jpg'),
        'Replace did not preserve the prior thumbnail outside public storage'
    );
    $replaceUndo = $service->undo($replacement['undo_token']);
    assertUndoSame(true, $replaceUndo['exists'], 'Undo replace should report an existing thumbnail');
    assertUndoSame(
        $initialHash,
        hash_file('sha256', $thumbnailDirectory . '/tt20000002.jpg'),
        'Undo replace did not restore the prior thumbnail'
    );
    assertUndoSame(
        '/thumbs/tt20000002.jpg?v=' . substr($initialHash, 0, 12),
        $replaceUndo['thumbnail_url'],
        'Undo replace did not return the restored cache-busted URL'
    );

    $staleUpload = $service->store('tt20000003', $firstImage, filesize($firstImage), 'upload');
    copy($secondImage, $thumbnailDirectory . '/tt20000003.jpg');
    $newerHash = hash_file('sha256', $thumbnailDirectory . '/tt20000003.jpg');
    expectUndoError(
        fn() => $service->undo($staleUpload['undo_token']),
        409,
        'Undo should reject a live thumbnail changed after the mutation'
    );
    assertUndoSame(
        $newerHash,
        hash_file('sha256', $thumbnailDirectory . '/tt20000003.jpg'),
        'Conflicting undo changed the newer live thumbnail'
    );

    expectUndoError(
        fn() => $service->undo('../not-a-token'),
        400,
        'Invalid undo tokens should be rejected'
    );

    $latestUpload = $service->store('tt20000004', $firstImage, filesize($firstImage), 'upload');
    $latestReplace = $service->store(
        'tt20000004',
        $secondImage,
        filesize($secondImage),
        'replace',
        $latestUpload['undo_token']
    );
    assertUndoSame(
        false,
        is_file($undoDirectory . '/' . $latestUpload['undo_token'] . '.json'),
        'A newer mutation did not invalidate the component previous undo token'
    );
    assertUndoSame(
        true,
        is_file($undoDirectory . '/' . $latestReplace['undo_token'] . '.json'),
        'The latest mutation should remain undoable'
    );
} finally {
    foreach ([$sourceDirectory, $thumbnailDirectory, $undoDirectory] as $directory) {
        foreach (glob($directory . '/*') ?: [] as $path) {
            unlink($path);
        }
        foreach (glob($directory . '/.*') ?: [] as $path) {
            if (is_file($path)) unlink($path);
        }
        rmdir($directory);
    }
    rmdir($testDirectory);
}

echo "ThumbnailUndoService tests passed\n";
