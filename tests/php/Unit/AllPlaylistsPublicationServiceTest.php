<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../public/api/admin/publication/AllPlaylistsPublicationService.php';

use FreeTV\Admin\Publication\AllPlaylistsPublicationService;
use FreeTV\Admin\Publication\PlaylistPublicationSerializer;
use FreeTV\Admin\Publication\PublicationException;
use FreeTV\Admin\Publication\PublicationUndoService;
use PHPUnit\Framework\TestCase;

final class AllPlaylistsPublicationServiceTest extends TestCase
{
    private string $root;
    private string $publicationRoot;
    private string $playlistDirectory;
    private string $undoRoot;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/freetv-publish-all-' . bin2hex(random_bytes(8));
        $this->publicationRoot = $this->root . '/published';
        $this->playlistDirectory = $this->publicationRoot . '/playlists';
        $this->undoRoot = $this->root . '/undo';

        if (!mkdir($this->playlistDirectory, 0700, true)) {
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

    public function testPublishesOnlyChangedPlaylistsAndAuthoritativeDefault(): void
    {
        [$playlists, $shows, $publishedPlaylists, $publishedShows] = $this->fixture();
        $timestamps = $this->writePublishedFixture($publishedPlaylists, $publishedShows, 'freetv.json');
        $britishBefore = $this->read('playlists/british.json');
        $configBefore = $this->read('config.json');
        $writes = [];
        $updates = [];
        $stateAtUpdate = [];
        $operationTimestamp = '2026-08-12T16:30:00.000Z';
        $service = $this->service(
            $playlists,
            $shows,
            function (int $playlistId, ?string $timestamp) use (
                &$updates,
                &$stateAtUpdate,
                $operationTimestamp
            ): void {
                $updates[] = [$playlistId, $timestamp];
                $index = $this->decode('playlists/index.json');
                $entries = array_column($index['playlists'], null, 'filename');
                $stateAtUpdate[] = [
                    $this->decode('playlists/freetv.json')['lastupdated'],
                    $this->decode('playlists/holidays.json')['lastupdated'],
                    $entries['freetv.json']['lastupdated'],
                    $entries['holidays.json']['lastupdated'],
                ];
                self::assertSame(
                    [$operationTimestamp, $operationTimestamp, $operationTimestamp, $operationTimestamp],
                    $stateAtUpdate[array_key_last($stateAtUpdate)]
                );
            },
            static fn(): DateTimeImmutable => new DateTimeImmutable('2026-08-12T16:30:00.987Z'),
            $this->undoService(),
            $this->writer($writes)
        );

        $result = $service->publish();
        $index = $this->decode('playlists/index.json');
        $entries = array_column($index['playlists'], null, 'filename');

        self::assertSame([
            'lastupdated' => $operationTimestamp,
            'playlists' => ['freetv.json', 'holidays.json'],
            'default_changed' => true,
            'no_op' => false,
        ], $result);
        self::assertSame(1, count(array_filter(
            $writes,
            static fn(string $path): bool => basename($path) === 'index.json'
        )));
        self::assertSame(
            PlaylistPublicationSerializer::serialize($playlists[0], $shows[1], $operationTimestamp),
            $this->decode('playlists/freetv.json')
        );
        self::assertSame(
            PlaylistPublicationSerializer::serialize($playlists[2], $shows[3], $operationTimestamp),
            $this->decode('playlists/holidays.json')
        );
        self::assertSame('british.json', $index['default']);
        self::assertSame($operationTimestamp, $entries['freetv.json']['lastupdated']);
        self::assertSame($operationTimestamp, $entries['holidays.json']['lastupdated']);
        self::assertSame($timestamps['british.json'], $entries['british.json']['lastupdated']);
        self::assertSame($britishBefore, $this->read('playlists/british.json'));
        self::assertSame($configBefore, $this->read('config.json'));
        self::assertSame([
            [1, '2026-08-12 16:30:00'],
            [3, '2026-08-12 16:30:00'],
        ], $updates);
        self::assertCount(2, $stateAtUpdate);

        $metadata = $this->undoMetadata();
        self::assertSame('playlist_all', $metadata['operation']);
        self::assertSame('All Shows and Playlist Content', $metadata['target']);
        self::assertSame([
            'playlists/freetv.json',
            'playlists/holidays.json',
            'playlists/index.json',
        ], array_column($metadata['files'], 'path'));
        self::assertSame([], $this->temporaryArtifacts());
    }

    public function testPublishesDefaultChangeWithoutRewritingPlaylistContent(): void
    {
        [$playlists, $shows] = $this->fixture();
        $timestamps = $this->writePublishedFixture($playlists, $shows, 'freetv.json');
        $playlistHashes = $this->playlistContentHashes();
        $updates = [];
        $writes = [];
        $result = $this->service(
            $playlists,
            $shows,
            static function (int $playlistId, ?string $timestamp) use (&$updates): void {
                $updates[] = [$playlistId, $timestamp];
            },
            static fn(): DateTimeImmutable => new DateTimeImmutable('2026-08-12T17:00:00Z'),
            $this->undoService(),
            $this->writer($writes)
        )->publish();

        self::assertSame([
            'lastupdated' => '2026-08-12T17:00:00.000Z',
            'playlists' => [],
            'default_changed' => true,
            'no_op' => false,
        ], $result);
        self::assertCount(1, $writes);
        self::assertSame('index.json', basename($writes[0]));
        self::assertSame([], $updates);
        self::assertSame($playlistHashes, $this->playlistContentHashes());
        $index = $this->decode('playlists/index.json');
        self::assertSame('british.json', $index['default']);
        self::assertSame(
            $timestamps,
            array_column($index['playlists'], 'lastupdated', 'filename')
        );
        self::assertSame(['playlists/index.json'], array_column($this->undoMetadata()['files'], 'path'));
        self::assertSame([], $this->temporaryArtifacts());
    }

    public function testMatchingPublishedStateIsSemanticNoOp(): void
    {
        [$playlists, $shows] = $this->fixture();
        $this->writePublishedFixture($playlists, $shows, 'british.json');
        $undo = $this->undoService();
        $this->activateConfigUndo($undo);
        $publicationBefore = $this->treeHashes($this->publicationRoot);
        $undoBefore = $this->treeHashes($this->undoRoot . '/active');
        $statusBefore = $undo->status();
        $writes = [];
        $updates = [];
        $clockCalls = 0;

        $result = $this->service(
            $playlists,
            $shows,
            static function (int $playlistId, ?string $timestamp) use (&$updates): void {
                $updates[] = [$playlistId, $timestamp];
            },
            static function () use (&$clockCalls): DateTimeImmutable {
                $clockCalls++;
                throw new RuntimeException('No-op requested a publication timestamp');
            },
            $undo,
            $this->writer($writes)
        )->publish();

        self::assertSame([
            'lastupdated' => null,
            'playlists' => [],
            'default_changed' => false,
            'no_op' => true,
        ], $result);
        self::assertSame([], $writes);
        self::assertSame([], $updates);
        self::assertSame(0, $clockCalls);
        self::assertSame($publicationBefore, $this->treeHashes($this->publicationRoot));
        self::assertSame($statusBefore, $undo->status());
        self::assertSame($undoBefore, $this->treeHashes($this->undoRoot . '/active'));
        self::assertSame([], $this->temporaryArtifacts());
    }

    public function testRepeatedPublicationReplacesUndoWithImmediatePredecessor(): void
    {
        [$playlists, $shows] = $this->fixture();
        $this->writePublishedFixture($playlists, $shows, 'british.json');
        $undo = $this->undoService();

        $shows[1][0]['title'] = 'First Replacement';
        $this->service(
            $playlists,
            $shows,
            static function (): void {},
            static fn(): DateTimeImmutable => new DateTimeImmutable('2026-08-12T17:30:00Z'),
            $undo
        )->publish();
        $firstArtifact = $this->read('playlists/freetv.json');
        $firstIndex = $this->read('playlists/index.json');
        $firstUndo = $this->treeHashes($this->undoRoot . '/active');

        $shows[1][0]['title'] = 'Second Replacement';
        $result = $this->service(
            $playlists,
            $shows,
            static function (): void {},
            static fn(): DateTimeImmutable => new DateTimeImmutable('2026-08-12T17:45:00Z'),
            $undo
        )->publish();

        self::assertSame(['freetv.json'], $result['playlists']);
        self::assertNotSame($firstUndo, $this->treeHashes($this->undoRoot . '/active'));
        self::assertSame(
            $firstArtifact,
            $this->readUndoBackup('playlists/freetv.json')
        );
        self::assertSame($firstIndex, $this->readUndoBackup('playlists/index.json'));
        self::assertSame('Second Replacement', $this->decode('playlists/freetv.json')['shows'][0]['title']);
        self::assertSame([], $this->temporaryArtifacts());
    }

    public function testPublishesNewAndExistingChangedPlaylistsInOneOperation(): void
    {
        [$playlists, $shows, $publishedPlaylists, $publishedShows] = $this->fixture();
        $this->writePublishedFixture(
            array_slice($publishedPlaylists, 0, 2),
            $publishedShows,
            'british.json'
        );
        $freetvBefore = $this->read('playlists/freetv.json');
        $britishBefore = $this->read('playlists/british.json');
        $undo = $this->undoService();

        $preparedTimestamps = $undo->withLock(function (PublicationUndoService $locked): array {
            $prepared = $locked->prepare(
                'playlist_all',
                'All Shows and Playlist Content',
                ['playlists/holidays.json', 'playlists/index.json']
            );
            $timestamps = $locked->preparedPlaylistTimestamps($prepared);
            $locked->rollbackPrepared($prepared);
            return $timestamps;
        });
        self::assertSame([], $preparedTimestamps);

        $updates = [];
        $result = $this->service(
            $playlists,
            $shows,
            static function (int $playlistId, ?string $timestamp) use (&$updates): void {
                $updates[] = [$playlistId, $timestamp];
            },
            static fn(): DateTimeImmutable => new DateTimeImmutable('2026-08-12T17:50:00Z'),
            $undo
        )->publish();

        self::assertSame(['freetv.json', 'holidays.json'], $result['playlists']);
        self::assertFileExists($this->playlistDirectory . '/holidays.json');
        self::assertSame('2026-08-12T17:50:00.000Z', $this->decode('playlists/freetv.json')['lastupdated']);
        self::assertSame('2026-08-12T17:50:00.000Z', $this->decode('playlists/holidays.json')['lastupdated']);
        self::assertNotSame($freetvBefore, $this->read('playlists/freetv.json'));
        self::assertSame($britishBefore, $this->read('playlists/british.json'));
        self::assertSame([
            [1, '2026-08-12 17:50:00'],
            [3, '2026-08-12 17:50:00'],
        ], $updates);
        $index = $this->decode('playlists/index.json');
        self::assertSame(
            ['freetv.json', 'british.json', 'holidays.json'],
            array_column($index['playlists'], 'filename')
        );
        $files = array_column($this->undoMetadata()['files'], null, 'path');
        self::assertTrue($files['playlists/freetv.json']['existed']);
        self::assertFalse($files['playlists/holidays.json']['existed']);
        self::assertSame([], $this->temporaryArtifacts());
    }

    public function testArtifactWriterFailureRestoresFilesAndPriorUndo(): void
    {
        [$playlists, $shows, $publishedPlaylists, $publishedShows] = $this->fixture();
        $this->writePublishedFixture(
            array_slice($publishedPlaylists, 0, 2),
            $publishedShows,
            'british.json'
        );
        $this->write('playlists/unrelated.txt', "unrelated publication artifact\n");
        $undo = $this->undoService();
        $this->activateConfigUndo($undo);
        $publicationBefore = $this->treeHashes($this->publicationRoot);
        $undoBefore = $this->treeHashes($this->undoRoot . '/active');
        $statusBefore = $undo->status();
        $writes = [];
        $updates = [];

        $service = $this->service(
            $playlists,
            $shows,
            static function (int $playlistId, ?string $timestamp) use (&$updates): void {
                $updates[] = [$playlistId, $timestamp];
            },
            static fn(): DateTimeImmutable => new DateTimeImmutable('2026-08-12T18:00:00Z'),
            $undo,
            $this->writer($writes, 3)
        );
        $exception = $this->captureFailure(fn() => $service->publish());

        self::assertSame(500, $exception->getHttpStatus());
        self::assertSame(
            'Playlist-content publication failed: Simulated publication write failure',
            $exception->getMessage()
        );
        self::assertCount(3, $writes);
        self::assertSame([], $updates);
        self::assertSame($publicationBefore, $this->treeHashes($this->publicationRoot));
        self::assertFileDoesNotExist($this->playlistDirectory . '/holidays.json');
        self::assertSame($statusBefore, $undo->status());
        self::assertSame($undoBefore, $this->treeHashes($this->undoRoot . '/active'));
        self::assertSame([], $this->temporaryArtifacts());
    }

    public function testMalformedPublishedPlaylistFailsBeforeMutation(): void
    {
        [$playlists, $shows, $publishedPlaylists, $publishedShows] = $this->fixture();
        $this->writePublishedFixture($publishedPlaylists, $publishedShows, 'freetv.json');
        $this->write('playlists/freetv.json', '{invalid');

        $this->assertInvalidStatePreservesPublication($playlists, $shows);
    }

    public function testMultipleAuthoritativeDefaultsFailBeforeMutation(): void
    {
        [$playlists, $shows, $publishedPlaylists, $publishedShows] = $this->fixture();
        $this->writePublishedFixture($publishedPlaylists, $publishedShows, 'freetv.json');
        $playlists[0]['is_default'] = 1;

        $this->assertInvalidStatePreservesPublication($playlists, $shows);
    }

    private function assertInvalidStatePreservesPublication(array $playlists, array $shows): void
    {
        $undo = $this->undoService();
        $this->activateConfigUndo($undo);
        $publicationBefore = $this->treeHashes($this->publicationRoot);
        $undoBefore = $this->treeHashes($this->undoRoot . '/active');
        $statusBefore = $undo->status();
        $writes = [];
        $updates = [];
        $service = $this->service(
            $playlists,
            $shows,
            static function (int $playlistId, ?string $timestamp) use (&$updates): void {
                $updates[] = [$playlistId, $timestamp];
            },
            static fn(): DateTimeImmutable => new DateTimeImmutable('2026-08-12T18:30:00Z'),
            $undo,
            $this->writer($writes)
        );

        $exception = $this->captureFailure(fn() => $service->publish());

        self::assertSame(409, $exception->getHttpStatus());
        self::assertSame([], $writes);
        self::assertSame([], $updates);
        self::assertSame($publicationBefore, $this->treeHashes($this->publicationRoot));
        self::assertSame($statusBefore, $undo->status());
        self::assertSame($undoBefore, $this->treeHashes($this->undoRoot . '/active'));
        self::assertSame([], $this->temporaryArtifacts());
    }

    private function fixture(): array
    {
        $playlists = [
            $this->playlist(1, 'freetv.json', 'FreeTV', false, 0, '2026-08-12 11:00:00', '2.0'),
            $this->playlist(2, 'british.json', 'British', true, 1, '2026-08-12 12:00:00'),
            $this->playlist(3, 'holidays.json', 'Holidays', false, 2, null),
        ];
        $shows = [
            1 => [
                $this->show(1, 'first-show', 'First Show'),
                $this->show(2, 'new-show', 'New Show', 1),
            ],
            2 => [$this->show(3, 'british-show', 'British Show')],
            3 => [$this->show(4, 'holiday-show', 'Edited Holiday Show')],
        ];
        $publishedPlaylists = $playlists;
        $publishedPlaylists[0]['dbversion'] = '1.0';
        $publishedShows = $shows;
        $publishedShows[1] = [$shows[1][0]];
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

    private function show(int $id, string $identifier, string $title, int $sortOrder = 0): array
    {
        return [
            'id' => $id,
            'sort_order' => $sortOrder,
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

    private function writePublishedFixture(
        array $publishedPlaylists,
        array $publishedShows,
        string $publishedDefault
    ): array {
        $timestamps = [
            'freetv.json' => '2026-08-12T08:00:00.000Z',
            'british.json' => '2026-08-12T09:00:00.000Z',
            'holidays.json' => '2026-08-12T10:00:00.000Z',
        ];
        $entries = [];
        foreach ($publishedPlaylists as $playlist) {
            $filename = $playlist['filename'];
            $this->write('playlists/' . $filename, $this->encode(
                PlaylistPublicationSerializer::serialize(
                    $playlist,
                    $publishedShows[$playlist['id']],
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
            'default' => $publishedDefault,
            'playlists' => $entries,
        ]));
        $this->write('config.json', $this->encode([
            'lastupdated' => '2026-08-12T07:00:00.000Z',
            'show_ads' => true,
        ]));
        return array_intersect_key($timestamps, array_flip(array_column($publishedPlaylists, 'filename')));
    }

    private function service(
        array $playlists,
        array $shows,
        callable $updater,
        callable $clock,
        PublicationUndoService $undo,
        ?callable $writer = null
    ): AllPlaylistsPublicationService {
        return new AllPlaylistsPublicationService(
            $this->publicationRoot,
            static fn(): array => $playlists,
            static fn(int $playlistId): array => $shows[$playlistId],
            $updater,
            $clock,
            $undo,
            $writer
        );
    }

    private function undoService(): PublicationUndoService
    {
        return new PublicationUndoService(
            $this->publicationRoot,
            $this->undoRoot,
            static function (): void {
                self::fail('The preserved Undo state should not be executed');
            }
        );
    }

    private function activateConfigUndo(PublicationUndoService $undo): void
    {
        $undo->withLock(function (PublicationUndoService $locked): void {
            $prepared = $locked->prepare('config', 'config.json', ['config.json']);
            $this->write('config.json', $this->encode([
                'lastupdated' => '2026-08-12T07:30:00.000Z',
                'show_ads' => true,
            ]));
            $locked->promote($prepared);
        });
    }

    private function writer(array &$writes, ?int $failAt = null): callable
    {
        return function (string $path, string $contents) use (&$writes, $failAt): void {
            $writes[] = $path;
            if ($failAt !== null && count($writes) === $failAt) {
                throw new RuntimeException('Simulated publication write failure');
            }
            $relativePath = substr($path, strlen($this->publicationRoot) + 1);
            $this->write($relativePath, $contents);
        };
    }

    private function captureFailure(callable $operation): PublicationException
    {
        try {
            $operation();
        } catch (PublicationException $exception) {
            return $exception;
        }
        self::fail('Expected publication to fail');
    }

    private function encode(array $artifact): string
    {
        return json_encode(
            $artifact,
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

    private function read(string $relativePath): string
    {
        return (string) file_get_contents($this->publicationRoot . '/' . $relativePath);
    }

    private function decode(string $relativePath): array
    {
        return json_decode($this->read($relativePath), true, 512, JSON_THROW_ON_ERROR);
    }

    private function undoMetadata(): array
    {
        return json_decode(
            (string) file_get_contents($this->undoRoot . '/active/operation.json'),
            true,
            512,
            JSON_THROW_ON_ERROR
        );
    }

    private function readUndoBackup(string $relativePath): string
    {
        return (string) file_get_contents($this->undoRoot . '/active/files/' . $relativePath);
    }

    private function playlistContentHashes(): array
    {
        $hashes = [];
        foreach (['freetv.json', 'british.json', 'holidays.json'] as $filename) {
            $hashes[$filename] = hash_file('sha256', $this->playlistDirectory . '/' . $filename);
        }
        return $hashes;
    }

    private function treeHashes(string $root): array
    {
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
            glob($this->playlistDirectory . '/.publish-all-*') ?: [],
            glob($this->undoRoot . '/.prepared-*') ?: [],
            glob($this->undoRoot . '/.prior-*') ?: [],
            glob($this->undoRoot . '/.restore-*') ?: []
        );
    }
}
