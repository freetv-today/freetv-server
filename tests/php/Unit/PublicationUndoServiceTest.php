<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../public/api/admin/publication/AllPlaylistsPublicationService.php';

use FreeTV\Admin\Publication\AllPlaylistsPublicationService;
use FreeTV\Admin\Publication\PlaylistPublicationSerializer;
use FreeTV\Admin\Publication\PublicationException;
use FreeTV\Admin\Publication\PublicationUndoService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PublicationUndoServiceTest extends TestCase
{
    private string $root;
    private string $publicationRoot;
    private string $undoRoot;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/freetv-publication-undo-' . bin2hex(random_bytes(8));
        $this->publicationRoot = $this->root . '/published';
        $this->undoRoot = $this->root . '/undo';
        if (!mkdir($this->publicationRoot . '/playlists', 0700, true)) {
            self::fail('Could not create publication fixture directory');
        }
    }

    protected function tearDown(): void
    {
        if (!isset($this->root) || !is_dir($this->root)) {
            return;
        }
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->root, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($this->root);
    }

    public function testSinglePlaylistUndoRestoresCoupledArtifactsAndTimestamp(): void
    {
        $oldTimestamp = '2026-08-12T09:00:00.000Z';
        $oldPlaylist = $this->playlistJson($oldTimestamp, 'Old Playlist');
        $oldIndex = $this->indexJson('freetv.json', ['freetv.json' => $oldTimestamp]);
        $newPlaylist = $this->playlistJson('2026-08-12T10:00:00.000Z', 'New Playlist');
        $newIndex = $this->indexJson(
            'freetv.json',
            ['freetv.json' => '2026-08-12T10:00:00.000Z']
        );
        $this->write('playlists/freetv.json', $oldPlaylist);
        $this->write('playlists/index.json', $oldIndex);
        $updates = [];
        $undo = $this->undoService($updates);
        $this->activate($undo, 'playlist', 'freetv.json', [
            'playlists/freetv.json' => $newPlaylist,
            'playlists/index.json' => $newIndex,
        ]);

        self::assertSame(
            ['available' => true, 'operation' => 'playlist', 'target' => 'freetv.json'],
            $undo->status()
        );
        self::assertSame(
            ['playlists/freetv.json', 'playlists/index.json'],
            array_column($this->metadata()['files'], 'path')
        );

        $result = $undo->undo();

        self::assertSame($oldPlaylist, $this->read('playlists/freetv.json'));
        self::assertSame($oldIndex, $this->read('playlists/index.json'));
        self::assertSame([['freetv.json', '2026-08-12 09:00:00']], $updates);
        self::assertSame([
            'operation' => 'playlist',
            'target' => 'freetv.json',
            'restored' => ['playlists/freetv.json', 'playlists/index.json'],
        ], $result);
        self::assertSame(
            ['available' => false, 'operation' => null, 'target' => null],
            $undo->status()
        );
        self::assertSame([], $this->temporaryArtifacts());
        $exception = $this->capturePublicationFailure(fn() => $undo->undo());
        self::assertSame(404, $exception->getHttpStatus());
    }

    public function testConfigAndPlaylistPublicationsReplaceUndoSlotWithoutCrossRestoration(): void
    {
        $oldConfig = "{\n    \"show_ads\": false\n}\n";
        $newConfig = "{\n    \"show_ads\": true\n}\n";
        $newerConfig = "{\n    \"show_ads\": false,\n    \"theme\": \"dark\"\n}\n";
        $playlist = $this->playlistJson('2026-08-12T09:00:00.000Z', 'Old Playlist');
        $index = $this->indexJson('freetv.json', ['freetv.json' => '2026-08-12T09:00:00.000Z']);
        $this->write('config.json', $oldConfig);
        $this->write('playlists/freetv.json', $playlist);
        $this->write('playlists/index.json', $index);
        $updates = [];
        $undo = $this->undoService($updates);

        $this->activate($undo, 'config', 'config.json', ['config.json' => $newConfig]);
        self::assertSame('config', $undo->status()['operation']);
        $this->activate($undo, 'playlist', 'freetv.json', [
            'playlists/freetv.json' => $this->playlistJson(
                '2026-08-12T10:00:00.000Z',
                'New Playlist'
            ),
            'playlists/index.json' => $this->indexJson(
                'freetv.json',
                ['freetv.json' => '2026-08-12T10:00:00.000Z']
            ),
        ]);
        self::assertSame('playlist', $undo->status()['operation']);
        $playlistBeforeConfigUndo = $this->read('playlists/freetv.json');
        $indexBeforeConfigUndo = $this->read('playlists/index.json');
        $this->activate($undo, 'config', 'config.json', ['config.json' => $newerConfig]);
        self::assertSame('config', $undo->status()['operation']);

        $result = $undo->undo();

        self::assertSame($newConfig, $this->read('config.json'));
        self::assertSame($playlistBeforeConfigUndo, $this->read('playlists/freetv.json'));
        self::assertSame($indexBeforeConfigUndo, $this->read('playlists/index.json'));
        self::assertSame([], $updates);
        self::assertSame(['config.json'], $result['restored']);

        $this->activate($undo, 'config', 'config.json', ['config.json' => $newerConfig]);
        $activeBefore = $this->treeHashes($this->undoRoot . '/active');
        $undo->withLock(function (PublicationUndoService $locked): void {
            $prepared = $locked->prepare('config', 'config.json', ['config.json']);
            $this->write('config.json', "{\n    \"failed\": true\n}\n");
            $locked->rollbackPrepared($prepared);
        });
        self::assertSame($newerConfig, $this->read('config.json'));
        self::assertSame($activeBefore, $this->treeHashes($this->undoRoot . '/active'));
        self::assertSame('config', $undo->status()['operation']);
        self::assertSame([], $this->temporaryArtifacts());
    }

    public function testPublishAllUndoRestoresChangedPlaylistsIndexAndPublishedTimestamps(): void
    {
        [$playlists, $shows, $publishedPlaylists, $publishedShows] = $this->publicationFixture();
        $this->writePublished($publishedPlaylists, $publishedShows, 'freetv.json');
        $old = $this->publicationBytes();
        $publicationUpdates = [];
        $undoUpdates = [];
        $undo = $this->undoService($undoUpdates);
        $this->publishAll($playlists, $shows, $publicationUpdates, $undo, '2026-08-12T16:30:00Z');
        $britishPublished = $this->read('playlists/british.json');
        $configPublished = $this->read('config.json');

        $result = $undo->undo();

        self::assertSame($old['freetv.json'], $this->read('playlists/freetv.json'));
        self::assertSame($old['holidays.json'], $this->read('playlists/holidays.json'));
        self::assertSame($old['index.json'], $this->read('playlists/index.json'));
        self::assertSame($britishPublished, $this->read('playlists/british.json'));
        self::assertSame($configPublished, $this->read('config.json'));
        self::assertSame([
            ['freetv.json', '2026-08-12 08:00:00'],
            ['holidays.json', '2026-08-12 10:00:00'],
        ], $undoUpdates);
        self::assertSame('playlist_all', $result['operation']);
        self::assertSame([
            'playlists/freetv.json',
            'playlists/holidays.json',
            'playlists/index.json',
        ], $result['restored']);
        self::assertFalse($undo->status()['available']);
        self::assertSame([], $this->temporaryArtifacts());
    }

    public function testDefaultOnlyPublishAllUndoRestoresOnlyIndex(): void
    {
        [$playlists, $shows] = $this->publicationFixture();
        $this->writePublished($playlists, $shows, 'freetv.json');
        $playlistBefore = $this->playlistHashes();
        $indexBefore = $this->read('playlists/index.json');
        $configBefore = $this->read('config.json');
        $publicationUpdates = [];
        $undoUpdates = [];
        $undo = $this->undoService($undoUpdates);
        $this->publishAll($playlists, $shows, $publicationUpdates, $undo, '2026-08-12T17:00:00Z');

        self::assertSame('british.json', $this->decode('playlists/index.json')['default']);
        $result = $undo->undo();

        self::assertSame($indexBefore, $this->read('playlists/index.json'));
        self::assertSame('freetv.json', $this->decode('playlists/index.json')['default']);
        self::assertSame($playlistBefore, $this->playlistHashes());
        self::assertSame($configBefore, $this->read('config.json'));
        self::assertSame([], $publicationUpdates);
        self::assertSame([], $undoUpdates);
        self::assertSame(['playlists/index.json'], $result['restored']);
        self::assertFalse($undo->status()['available']);
        self::assertSame([], $this->temporaryArtifacts());
    }

    public function testPublishAllUndoRemovesNewArtifactWithoutInventingPreviousTimestamp(): void
    {
        [$playlists, $shows, $publishedPlaylists, $publishedShows] = $this->publicationFixture();
        $this->writePublished(array_slice($publishedPlaylists, 0, 2), $publishedShows, 'british.json');
        $freetvBefore = $this->read('playlists/freetv.json');
        $britishBefore = $this->read('playlists/british.json');
        $indexBefore = $this->read('playlists/index.json');
        $publicationUpdates = [];
        $undoUpdates = [];
        $undo = $this->undoService($undoUpdates);
        $this->publishAll($playlists, $shows, $publicationUpdates, $undo, '2026-08-12T17:50:00Z');
        $files = array_column($this->metadata()['files'], null, 'path');

        self::assertFileExists($this->publicationRoot . '/playlists/holidays.json');
        self::assertFalse($files['playlists/holidays.json']['existed']);
        $result = $undo->undo();

        self::assertFileDoesNotExist($this->publicationRoot . '/playlists/holidays.json');
        self::assertSame($freetvBefore, $this->read('playlists/freetv.json'));
        self::assertSame($britishBefore, $this->read('playlists/british.json'));
        self::assertSame($indexBefore, $this->read('playlists/index.json'));
        self::assertSame([['freetv.json', '2026-08-12 08:00:00']], $undoUpdates);
        self::assertNotContains('holidays.json', array_column($undoUpdates, 0));
        self::assertSame([
            'playlists/freetv.json',
            'playlists/holidays.json',
            'playlists/index.json',
        ], $result['restored']);
        self::assertFalse($undo->status()['available']);
        self::assertSame([], $this->temporaryArtifacts());
    }

    public function testCorruptedBackupIsRejectedWithoutMutation(): void
    {
        [$undo, $updates] = $this->activeConfigUndo();
        $liveBefore = $this->read('config.json');
        $this->writeUndo('active/files/config.json', 'corrupted backup');

        $exception = $this->capturePublicationFailure(fn() => $undo->undo());

        self::assertSame(409, $exception->getHttpStatus());
        self::assertSame('Publication Undo backup is corrupted', $exception->getMessage());
        self::assertSame($liveBefore, $this->read('config.json'));
        self::assertSame([], $updates);
        self::assertTrue($undo->status()['available']);
        self::assertDirectoryExists($this->undoRoot . '/active');
        self::assertSame([], $this->temporaryArtifacts());
    }

    public function testChangedLiveArtifactIsRejectedWithoutOverwritingIt(): void
    {
        $oldTimestamp = '2026-08-12T09:00:00.000Z';
        $this->write('playlists/freetv.json', $this->playlistJson($oldTimestamp, 'Old Playlist'));
        $this->write('playlists/index.json', $this->indexJson('freetv.json', ['freetv.json' => $oldTimestamp]));
        $updates = [];
        $undo = $this->undoService($updates);
        $this->activate($undo, 'playlist', 'freetv.json', [
            'playlists/freetv.json' => $this->playlistJson('2026-08-12T10:00:00.000Z', 'Published'),
            'playlists/index.json' => $this->indexJson(
                'freetv.json',
                ['freetv.json' => '2026-08-12T10:00:00.000Z']
            ),
        ]);
        $externalChange = $this->playlistJson('2026-08-12T11:00:00.000Z', 'External Change');
        $indexBefore = $this->read('playlists/index.json');
        $this->write('playlists/freetv.json', $externalChange);

        $exception = $this->capturePublicationFailure(fn() => $undo->undo());

        self::assertSame(409, $exception->getHttpStatus());
        self::assertSame('Live publication no longer matches the available Undo', $exception->getMessage());
        self::assertSame($externalChange, $this->read('playlists/freetv.json'));
        self::assertSame($indexBefore, $this->read('playlists/index.json'));
        self::assertSame([], $updates);
        self::assertTrue($undo->status()['available']);
        self::assertSame([], $this->temporaryArtifacts());
    }

    #[DataProvider('invalidMetadataProvider')]
    public function testInvalidMetadataIsRejectedWithoutMutation(string $scenario): void
    {
        [$undo, $updates] = $this->activeConfigUndo();
        $liveBefore = $this->read('config.json');
        $metadata = $this->metadata();
        if ($scenario === 'malformed JSON') {
            $contents = '{bad metadata';
        } else {
            if ($scenario === 'missing target') {
                unset($metadata['target']);
            } elseif ($scenario === 'invalid operation') {
                $metadata['operation'] = 'invalid';
            } elseif ($scenario === 'invalid path') {
                $metadata['files'][0]['path'] = '../config.json';
            } elseif ($scenario === 'invalid hash') {
                $metadata['files'][0]['published_hash'] = 'invalid';
            } elseif ($scenario === 'inconsistent files') {
                $metadata['files'][] = $metadata['files'][0];
            }
            $contents = $this->encode($metadata);
        }
        $this->writeUndo('active/operation.json', $contents);

        $exception = $this->capturePublicationFailure(fn() => $undo->undo());

        self::assertSame(409, $exception->getHttpStatus());
        self::assertSame($liveBefore, $this->read('config.json'));
        self::assertSame([], $updates);
        self::assertDirectoryExists($this->undoRoot . '/active');
        self::assertSame([], $this->temporaryArtifacts());
    }

    public static function invalidMetadataProvider(): array
    {
        return [
            'malformed JSON' => ['malformed JSON'],
            'missing required target' => ['missing target'],
            'invalid operation' => ['invalid operation'],
            'invalid artifact path' => ['invalid path'],
            'invalid recorded hash' => ['invalid hash'],
            'inconsistent operation files' => ['inconsistent files'],
        ];
    }

    public function testTimestampUpdaterFailureRecoversImmediatePreUndoFilesAndRetainsUndo(): void
    {
        $oldTimestamp = '2026-08-12T09:00:00.000Z';
        $newTimestamp = '2026-08-12T10:00:00.000Z';
        $this->write('playlists/freetv.json', $this->playlistJson($oldTimestamp, 'Old Playlist'));
        $this->write('playlists/index.json', $this->indexJson('freetv.json', ['freetv.json' => $oldTimestamp]));
        $calls = [];
        $undo = new PublicationUndoService(
            $this->publicationRoot,
            $this->undoRoot,
            static function (string $filename, string $timestamp) use (&$calls): void {
                $calls[] = [$filename, $timestamp];
                throw new RuntimeException('Simulated timestamp restoration failure');
            }
        );
        $this->activate($undo, 'playlist', 'freetv.json', [
            'playlists/freetv.json' => $this->playlistJson($newTimestamp, 'New Playlist'),
            'playlists/index.json' => $this->indexJson('freetv.json', ['freetv.json' => $newTimestamp]),
        ]);
        $liveBefore = $this->treeHashes($this->publicationRoot);
        $activeBefore = $this->treeHashes($this->undoRoot . '/active');

        $exception = null;
        try {
            $undo->undo();
        } catch (RuntimeException $caught) {
            $exception = $caught;
        }

        self::assertInstanceOf(RuntimeException::class, $exception);
        self::assertSame('Simulated timestamp restoration failure', $exception->getMessage());
        self::assertSame([['freetv.json', '2026-08-12 09:00:00']], $calls);
        self::assertSame($liveBefore, $this->treeHashes($this->publicationRoot));
        self::assertSame($activeBefore, $this->treeHashes($this->undoRoot . '/active'));
        self::assertTrue($undo->status()['available']);
        self::assertSame([], $this->temporaryArtifacts());
    }

    private function activeConfigUndo(): array
    {
        $this->write('config.json', "{\n    \"show_ads\": false\n}\n");
        $updates = [];
        $undo = $this->undoService($updates);
        $this->activate($undo, 'config', 'config.json', [
            'config.json' => "{\n    \"show_ads\": true\n}\n",
        ]);
        return [$undo, &$updates];
    }

    private function undoService(array &$updates): PublicationUndoService
    {
        return new PublicationUndoService(
            $this->publicationRoot,
            $this->undoRoot,
            static function (string $filename, string $timestamp) use (&$updates): void {
                $updates[] = [$filename, $timestamp];
            }
        );
    }

    private function activate(
        PublicationUndoService $undo,
        string $operation,
        string $target,
        array $newContents
    ): void {
        $undo->withLock(function (PublicationUndoService $locked) use (
            $operation,
            $target,
            $newContents
        ): void {
            $prepared = $locked->prepare($operation, $target, array_keys($newContents));
            foreach ($newContents as $path => $contents) {
                $this->write($path, $contents);
            }
            $locked->promote($prepared);
        });
    }

    private function publicationFixture(): array
    {
        $playlists = [
            $this->playlist(1, 'freetv.json', 'FreeTV', false, 0, '2026-08-12 11:00:00', '2.0'),
            $this->playlist(2, 'british.json', 'British', true, 1, '2026-08-12 12:00:00'),
            $this->playlist(3, 'holidays.json', 'Holidays', false, 2, null),
        ];
        $shows = [
            1 => [$this->show(1, 'first-show', 'Changed First Show')],
            2 => [$this->show(2, 'british-show', 'British Show')],
            3 => [$this->show(3, 'holiday-show', 'Changed Holiday Show')],
        ];
        $publishedPlaylists = $playlists;
        $publishedPlaylists[0]['dbversion'] = '1.0';
        $publishedShows = $shows;
        $publishedShows[1][0]['title'] = 'First Show';
        $publishedShows[1][0]['description'] = 'Description for First Show';
        $publishedShows[3][0]['title'] = 'Holiday Show';
        $publishedShows[3][0]['description'] = 'Description for Holiday Show';
        return [$playlists, $shows, $publishedPlaylists, $publishedShows];
    }

    private function playlist(
        int $id,
        string $filename,
        string $title,
        bool $default,
        int $sortOrder,
        ?string $lastUpdated,
        string $version = '1.0'
    ): array {
        return [
            'id' => $id,
            'filename' => $filename,
            'dbtitle' => $title,
            'dbversion' => $version,
            'author' => 'Free TV',
            'email' => 'support@example.test',
            'link' => 'https://example.test',
            'lastupdated' => $lastUpdated,
            'is_default' => $default ? 1 : 0,
            'sort_order' => $sortOrder,
        ];
    }

    private function show(int $id, string $identifier, string $title): array
    {
        return [
            'id' => $id,
            'sort_order' => 0,
            'category' => 'comedy',
            'status' => 'active',
            'identifier' => $identifier,
            'title' => $title,
            'description' => 'Description for ' . $title,
            'start_year' => '1960',
            'end_year' => '1961',
            'imdb' => sprintf('tt%07d', $id),
            'group_name' => null,
        ];
    }

    private function writePublished(array $playlists, array $shows, string $default): void
    {
        $timestamps = [
            'freetv.json' => '2026-08-12T08:00:00.000Z',
            'british.json' => '2026-08-12T09:00:00.000Z',
            'holidays.json' => '2026-08-12T10:00:00.000Z',
        ];
        $entries = [];
        foreach ($playlists as $playlist) {
            $filename = $playlist['filename'];
            $this->write('playlists/' . $filename, $this->encode(
                PlaylistPublicationSerializer::serialize(
                    $playlist,
                    $shows[$playlist['id']],
                    $timestamps[$filename]
                )
            ));
            $entries[] = [
                'filename' => $filename,
                'dbtitle' => $playlist['dbtitle'],
                'lastupdated' => $timestamps[$filename],
                'author' => $playlist['author'],
            ];
        }
        $this->write('playlists/index.json', $this->encode([
            'default' => $default,
            'playlists' => $entries,
        ]));
        $this->write('config.json', "{\n    \"show_ads\": true\n}\n");
    }

    private function publishAll(
        array $playlists,
        array $shows,
        array &$updates,
        PublicationUndoService $undo,
        string $time
    ): array {
        return (new AllPlaylistsPublicationService(
            $this->publicationRoot,
            static fn(): array => $playlists,
            static fn(int $playlistId): array => $shows[$playlistId],
            static function (int $playlistId, ?string $timestamp) use (&$updates): void {
                $updates[] = [$playlistId, $timestamp];
            },
            static fn(): DateTimeImmutable => new DateTimeImmutable($time),
            $undo
        ))->publish();
    }

    private function playlistJson(string $timestamp, string $title): string
    {
        return $this->encode([
            'lastupdated' => $timestamp,
            'dbtitle' => $title,
            'dbversion' => '1.0',
            'author' => 'Free TV',
            'email' => 'support@example.test',
            'link' => 'https://example.test',
            'shows' => [],
        ]);
    }

    private function indexJson(string $default, array $timestamps): string
    {
        $entries = [];
        foreach ($timestamps as $filename => $timestamp) {
            $entries[] = [
                'filename' => $filename,
                'dbtitle' => pathinfo($filename, PATHINFO_FILENAME),
                'lastupdated' => $timestamp,
                'author' => 'Free TV',
            ];
        }
        return $this->encode(['default' => $default, 'playlists' => $entries]);
    }

    private function publicationBytes(): array
    {
        return [
            'freetv.json' => $this->read('playlists/freetv.json'),
            'british.json' => $this->read('playlists/british.json'),
            'holidays.json' => $this->read('playlists/holidays.json'),
            'index.json' => $this->read('playlists/index.json'),
        ];
    }

    private function playlistHashes(): array
    {
        return [
            'freetv.json' => hash_file('sha256', $this->publicationRoot . '/playlists/freetv.json'),
            'british.json' => hash_file('sha256', $this->publicationRoot . '/playlists/british.json'),
            'holidays.json' => hash_file('sha256', $this->publicationRoot . '/playlists/holidays.json'),
        ];
    }

    private function metadata(): array
    {
        return json_decode(
            (string) file_get_contents($this->undoRoot . '/active/operation.json'),
            true,
            64,
            JSON_THROW_ON_ERROR
        );
    }

    private function capturePublicationFailure(callable $operation): PublicationException
    {
        try {
            $operation();
        } catch (PublicationException $exception) {
            return $exception;
        }
        self::fail('Expected PublicationException');
    }

    private function encode(array $value): string
    {
        return json_encode(
            $value,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
        ) . "\n";
    }

    private function write(string $relativePath, string $contents): void
    {
        $path = $this->publicationRoot . '/' . $relativePath;
        if (file_put_contents($path, $contents) !== strlen($contents)) {
            self::fail('Could not write fixture ' . $relativePath);
        }
    }

    private function writeUndo(string $relativePath, string $contents): void
    {
        $path = $this->undoRoot . '/' . $relativePath;
        if (file_put_contents($path, $contents) !== strlen($contents)) {
            self::fail('Could not write Undo fixture ' . $relativePath);
        }
    }

    private function read(string $relativePath): string
    {
        return (string) file_get_contents($this->publicationRoot . '/' . $relativePath);
    }

    private function decode(string $relativePath): array
    {
        return json_decode($this->read($relativePath), true, 64, JSON_THROW_ON_ERROR);
    }

    private function treeHashes(string $root): array
    {
        if (!is_dir($root)) {
            return [];
        }
        $hashes = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $item) {
            if ($item->isFile() && $item->getFilename() !== '.lock') {
                $relativePath = substr($item->getPathname(), strlen($root) + 1);
                $hashes[$relativePath] = hash_file('sha256', $item->getPathname());
            }
        }
        ksort($hashes);
        return $hashes;
    }

    private function temporaryArtifacts(): array
    {
        return array_merge(
            glob($this->undoRoot . '/.prepared-*') ?: [],
            glob($this->undoRoot . '/.prior-*') ?: [],
            glob($this->undoRoot . '/.restore-*') ?: [],
            glob($this->publicationRoot . '/playlists/.publish-all-*') ?: [],
            glob($this->publicationRoot . '/playlists/.undo-restore-*') ?: []
        );
    }
}
