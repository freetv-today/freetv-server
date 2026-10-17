<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../public/api/admin/DatasetPackage.php';
require_once __DIR__ . '/../../../public/api/admin/publication/PublicationUndoService.php';

use FreeTV\Admin\DatasetPackage;
use FreeTV\Admin\ServerPaths;
use FreeTV\Admin\Publication\PublicationException;
use FreeTV\Admin\Publication\PublicationUndoService;
use PHPUnit\Framework\TestCase;

final class SharedHostPublicationUndoTest extends TestCase
{
    private string $root;
    private string $public;
    private string $playlists;
    private string $undoRoot;
    private array $updates = [];
    private PublicationUndoService $service;
    private string $oldPlaylist;
    private string $oldIndex;
    private string $newPlaylist;
    private string $newIndex;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/freetv-shared-host-phpunit-' . bin2hex(random_bytes(8));
        $this->public = $this->root . '/public_html';
        $this->playlists = $this->public . '/playlists';
        self::assertTrue(mkdir($this->playlists, 0700, true));
        self::assertTrue(mkdir($this->root . '/temp', 0700, true));
        $paths = new ServerPaths($this->root, 'public_html');
        self::assertSame($this->public, $paths->publicRoot());
        $this->undoRoot = $paths->tempRoot() . '/publication-undo';
        $this->service = new PublicationUndoService(
            null,
            null,
            function (string $filename, string $timestamp): void {
                $this->updates[] = [$filename, $timestamp];
            },
            $paths
        );
        $oldTimestamp = '2026-08-12T09:00:00.000Z';
        $newTimestamp = '2026-08-12T10:00:00.000Z';
        $this->oldPlaylist = json_encode(['lastupdated' => $oldTimestamp, 'dbtitle' => 'Old', 'shows' => []], JSON_THROW_ON_ERROR);
        $this->newPlaylist = json_encode(['lastupdated' => $newTimestamp, 'dbtitle' => 'New', 'shows' => []], JSON_THROW_ON_ERROR);
        $this->oldIndex = json_encode(['default' => 'freetv.json', 'playlists' => [[
            'filename' => 'freetv.json', 'lastupdated' => $oldTimestamp,
        ]]], JSON_THROW_ON_ERROR);
        $this->newIndex = json_encode(['default' => 'freetv.json', 'playlists' => [[
            'filename' => 'freetv.json', 'lastupdated' => $newTimestamp,
        ]]], JSON_THROW_ON_ERROR);
        $this->replace($this->playlists . '/freetv.json', $this->oldPlaylist);
        $this->replace($this->playlists . '/index.json', $this->oldIndex);
        file_put_contents($this->public . '/unrelated.txt', 'preserve');
    }

    protected function tearDown(): void
    {
        DatasetPackage::removeTree($this->root);
    }

    public function testConcreteSharedHostPublicationAndUndoRestoresExactArtifacts(): void
    {
        $oldHashes = [
            hash('sha256', $this->oldPlaylist),
            hash('sha256', $this->oldIndex),
        ];
        $this->publish();

        self::assertDirectoryExists($this->undoRoot . '/active');
        self::assertFileExists($this->undoRoot . '/.lock');
        self::assertDirectoryDoesNotExist($this->root . '/public');
        self::assertFalse(str_starts_with($this->undoRoot, $this->public . '/'));
        $metadata = json_decode(
            (string) file_get_contents($this->undoRoot . '/active/operation.json'),
            true,
            32,
            JSON_THROW_ON_ERROR
        );
        self::assertSame('playlist', $metadata['operation']);
        self::assertSame('freetv.json', $metadata['target']);
        self::assertSame(['playlists/freetv.json', 'playlists/index.json'], array_column($metadata['files'], 'path'));
        foreach ($metadata['files'] as $file) {
            self::assertSame(
                hash_file('sha256', $this->public . '/' . $file['path']),
                $file['published_hash']
            );
        }

        $result = $this->service->undo();

        self::assertSame($this->oldPlaylist, file_get_contents($this->playlists . '/freetv.json'));
        self::assertSame($this->oldIndex, file_get_contents($this->playlists . '/index.json'));
        self::assertSame($oldHashes, [
            hash_file('sha256', $this->playlists . '/freetv.json'),
            hash_file('sha256', $this->playlists . '/index.json'),
        ]);
        self::assertSame([['freetv.json', '2026-08-12 09:00:00']], $this->updates);
        self::assertSame(['operation' => 'playlist', 'target' => 'freetv.json', 'restored' => [
            'playlists/freetv.json', 'playlists/index.json',
        ]], $result);
        self::assertSame('preserve', file_get_contents($this->public . '/unrelated.txt'));
        self::assertDirectoryDoesNotExist($this->undoRoot . '/active');
        self::assertFileExists($this->undoRoot . '/.lock');
        self::assertDirectoryDoesNotExist($this->root . '/public');
    }

    public function testStaleUndoIsRejectedWithoutChangingLiveBytesOrConsumingState(): void
    {
        $this->publish();
        $changed = '{"unexpected":true}';
        $this->replace($this->playlists . '/freetv.json', $changed);

        $exception = $this->captureFailure(fn() => $this->service->undo());

        self::assertSame(409, $exception->getHttpStatus());
        self::assertSame('Live publication no longer matches the available Undo', $exception->getMessage());
        self::assertSame($changed, file_get_contents($this->playlists . '/freetv.json'));
        self::assertSame($this->newIndex, file_get_contents($this->playlists . '/index.json'));
        self::assertSame(['available' => true, 'operation' => 'playlist', 'target' => 'freetv.json'], $this->service->status());
        self::assertSame([], $this->updates);
    }

    public function testMissingLiveArtifactIsRejectedAndUndoRemainsAvailable(): void
    {
        $this->publish();
        unlink($this->playlists . '/index.json');

        $exception = $this->captureFailure(fn() => $this->service->undo());

        self::assertSame(409, $exception->getHttpStatus());
        self::assertSame('Live publication no longer matches the available Undo', $exception->getMessage());
        self::assertFileDoesNotExist($this->playlists . '/index.json');
        self::assertTrue($this->service->status()['available']);
    }

    private function publish(): void
    {
        $this->service->withLock(function (PublicationUndoService $locked): void {
            $prepared = $locked->prepare('playlist', 'freetv.json', [
                'playlists/freetv.json', 'playlists/index.json',
            ]);
            $this->replace($this->playlists . '/freetv.json', $this->newPlaylist);
            $this->replace($this->playlists . '/index.json', $this->newIndex);
            $locked->promote($prepared);
        });
    }

    private function replace(string $path, string $contents): void
    {
        $temporary = tempnam(dirname($path), '.shared-host-');
        self::assertNotFalse($temporary);
        self::assertSame(strlen($contents), file_put_contents($temporary, $contents));
        self::assertTrue(rename($temporary, $path));
    }

    private function captureFailure(callable $operation): PublicationException
    {
        try {
            $operation();
            self::fail('Expected PublicationException');
        } catch (PublicationException $exception) {
            return $exception;
        }
    }
}
