<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../public/api/admin/ThumbnailService.php';
require_once __DIR__ . '/../../../public/api/admin/ThumbnailUploadService.php';
require_once __DIR__ . '/../../../public/api/admin/DatasetPackage.php';

use FreeTV\Admin\DatasetPackage;
use FreeTV\Admin\ThumbnailUploadException;
use FreeTV\Admin\ThumbnailUploadService;
use PHPUnit\Framework\TestCase;

final class ThumbnailUndoServiceTest extends TestCase
{
    private string $root;
    private string $source;
    private string $thumbs;
    private string $undo;
    private ThumbnailUploadService $service;

    protected function setUp(): void
    {
        if (!extension_loaded('imagick')) {
            self::markTestSkipped('Imagick is required for thumbnail mutation tests.');
        }

        $this->root = sys_get_temp_dir() . '/freetv-thumb-undo-' . bin2hex(random_bytes(8));
        $this->source = $this->root . '/source';
        $this->thumbs = $this->root . '/public/thumbs';
        $this->undo = $this->root . '/private/undo';
        foreach ([$this->source, $this->thumbs, $this->undo] as $directory) {
            self::assertTrue(mkdir($directory, 0700, true));
        }
        $this->service = new ThumbnailUploadService($this->thumbs, $this->undo);
    }

    protected function tearDown(): void
    {
        if (isset($this->root)) {
            DatasetPackage::removeTree($this->root);
        }
    }

    public function testUndoUploadRemovesOnlyCanonicalThumbnailAndConsumesToken(): void
    {
        $source = $this->image('first.jpg', '#336699');
        file_put_contents($this->thumbs . '/unrelated.jpg', 'preserve');
        $upload = $this->service->store('tt20000001', $source, filesize($source), 'upload');

        $result = $this->service->undo($upload['undo_token']);

        self::assertSame('tt20000001', $result['imdb']);
        self::assertSame('undo', $result['operation']);
        self::assertSame('upload', $result['undone_operation']);
        self::assertFalse($result['exists']);
        self::assertNull($result['thumbnail_url']);
        self::assertNull($result['undo_token']);
        self::assertFileDoesNotExist($this->thumbs . '/tt20000001.jpg');
        self::assertFileExists($this->thumbs . '/unrelated.jpg');
        self::assertFileDoesNotExist($this->undo . '/' . $upload['undo_token'] . '.json');
        $this->expectError(fn() => $this->service->undo($upload['undo_token']), 404);
    }

    public function testUndoReplacementRestoresExactBytesAndPrivateStateIsConsumed(): void
    {
        $first = $this->image('first.jpg', '#336699');
        $second = $this->image('second.jpg', '#993366');
        $initial = $this->service->store('tt20000002', $first, filesize($first), 'upload');
        $this->service->discardUndo($initial['undo_token']);
        $livePath = $this->thumbs . '/tt20000002.jpg';
        $initialHash = hash_file('sha256', $livePath);

        $replacement = $this->service->store('tt20000002', $second, filesize($second), 'replace');
        $metadata = $this->undo . '/' . $replacement['undo_token'] . '.json';
        $backup = $this->undo . '/' . $replacement['undo_token'] . '.jpg';
        self::assertSame($initialHash, hash_file('sha256', $backup));
        self::assertSame(0600, fileperms($metadata) & 0777);
        self::assertSame(0600, fileperms($backup) & 0777);
        self::assertFileDoesNotExist($this->thumbs . '/' . $replacement['undo_token'] . '.jpg');

        $result = $this->service->undo($replacement['undo_token']);

        self::assertTrue($result['exists']);
        self::assertSame('replace', $result['undone_operation']);
        self::assertSame($initialHash, hash_file('sha256', $livePath));
        self::assertSame(
            '/thumbs/tt20000002.jpg?v=' . substr((string) $initialHash, 0, 12),
            $result['thumbnail_url']
        );
        self::assertSame(0644, fileperms($livePath) & 0777);
        self::assertFileDoesNotExist($metadata);
        self::assertFileDoesNotExist($backup);
    }

    public function testStaleUndoRejectsConflictAndPreservesNewerLiveBytes(): void
    {
        $first = $this->image('first.jpg', '#336699');
        $second = $this->image('second.jpg', '#993366');
        $upload = $this->service->store('tt20000003', $first, filesize($first), 'upload');
        copy($second, $this->thumbs . '/tt20000003.jpg');
        $newerHash = hash_file('sha256', $this->thumbs . '/tt20000003.jpg');

        $this->expectError(fn() => $this->service->undo($upload['undo_token']), 409);

        self::assertSame($newerHash, hash_file('sha256', $this->thumbs . '/tt20000003.jpg'));
        self::assertFileExists($this->undo . '/' . $upload['undo_token'] . '.json');
    }

    public function testInvalidMissingAndMalformedUndoStateAreRejected(): void
    {
        $this->expectError(fn() => $this->service->undo('../not-a-token'), 400);
        $this->expectError(fn() => $this->service->undo(str_repeat('a', 64)), 404);

        $source = $this->image('first.jpg', '#336699');
        $upload = $this->service->store('tt20000006', $source, filesize($source), 'upload');
        $token = $upload['undo_token'];
        $livePath = $this->thumbs . '/tt20000006.jpg';
        $liveHash = hash_file('sha256', $livePath);
        file_put_contents($this->undo . '/' . $token . '.json', '{not-json');
        $this->expectError(fn() => $this->service->undo($token), 404);
        self::assertSame($liveHash, hash_file('sha256', $livePath));

        file_put_contents($this->undo . '/' . $token . '.json', json_encode([
            'imdb' => '../tt1',
            'operation' => 'upload',
            'expected_hash' => str_repeat('d', 64),
        ], JSON_THROW_ON_ERROR));
        $this->expectError(fn() => $this->service->undo($token), 404);
        self::assertSame($liveHash, hash_file('sha256', $livePath));
    }

    public function testMissingOrChangedReplacementBackupCannotAlterLiveThumbnail(): void
    {
        $first = $this->image('first.jpg', '#336699');
        $second = $this->image('second.jpg', '#993366');
        $third = $this->image('third.jpg', '#339966');
        $initial = $this->service->store('tt20000005', $first, filesize($first), 'upload');
        $this->service->discardUndo($initial['undo_token']);
        $replacement = $this->service->store('tt20000005', $second, filesize($second), 'replace');
        $liveHash = hash_file('sha256', $this->thumbs . '/tt20000005.jpg');
        unlink($this->undo . '/' . $replacement['undo_token'] . '.jpg');
        $this->expectError(fn() => $this->service->undo($replacement['undo_token']), 404);
        self::assertSame($liveHash, hash_file('sha256', $this->thumbs . '/tt20000005.jpg'));

        $replacement = $this->service->store('tt20000005', $third, filesize($third), 'replace');
        file_put_contents($this->undo . '/' . $replacement['undo_token'] . '.jpg', 'corrupt');
        $liveHash = hash_file('sha256', $this->thumbs . '/tt20000005.jpg');
        $this->expectError(fn() => $this->service->undo($replacement['undo_token']), 404);
        self::assertSame($liveHash, hash_file('sha256', $this->thumbs . '/tt20000005.jpg'));
    }

    public function testNewMutationInvalidatesPreviousTokenAndDiscardIsIdempotent(): void
    {
        $first = $this->image('first.jpg', '#336699');
        $second = $this->image('second.jpg', '#993366');
        $upload = $this->service->store('tt20000004', $first, filesize($first), 'upload');
        $replace = $this->service->store(
            'tt20000004',
            $second,
            filesize($second),
            'replace',
            $upload['undo_token']
        );

        self::assertFileDoesNotExist($this->undo . '/' . $upload['undo_token'] . '.json');
        self::assertFileExists($this->undo . '/' . $replace['undo_token'] . '.json');
        $this->service->discardUndo($replace['undo_token']);
        $this->service->discardUndo($replace['undo_token']);
        $this->service->discardUndo('../invalid');
        self::assertFileDoesNotExist($this->undo . '/' . $replace['undo_token'] . '.json');
        self::assertFileDoesNotExist($this->undo . '/' . $replace['undo_token'] . '.jpg');
    }

    private function image(string $name, string $color): string
    {
        $path = $this->source . '/' . $name;
        $image = new Imagick();
        $image->newImage(467, 668, new ImagickPixel($color));
        $image->setImageFormat('jpeg');
        $image->writeImage($path);
        $image->clear();
        $image->destroy();
        return $path;
    }

    private function expectError(callable $operation, int $status): void
    {
        try {
            $operation();
            self::fail('Expected ThumbnailUploadException');
        } catch (ThumbnailUploadException $exception) {
            self::assertSame($status, $exception->getHttpStatus());
        }
    }
}
