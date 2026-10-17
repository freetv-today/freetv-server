<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../public/api/admin/publication/PlaylistPublicationService.php';

use FreeTV\Admin\Publication\PlaylistPublicationSerializer;
use FreeTV\Admin\Publication\PlaylistPublicationService;
use FreeTV\Admin\Publication\PublicationException;
use FreeTV\Admin\Publication\PublicationUndoService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PlaylistPublicationServiceTest extends TestCase
{
    private string $root;
    private string $publicationRoot;
    private string $playlistDirectory;
    private string $undoRoot;
    private array $playlists;
    private array $showsByPlaylist;
    private array $publishedIndex;
    private string $unselectedArtifact;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/freetv-playlist-publication-' . bin2hex(random_bytes(8));
        $this->publicationRoot = $this->root . '/published';
        $this->playlistDirectory = $this->publicationRoot . '/playlists';
        $this->undoRoot = $this->root . '/undo';

        if (!mkdir($this->playlistDirectory, 0700, true)) {
            self::fail('Could not create publication fixture directory');
        }

        $this->playlists = [
            $this->playlist(1, 'british.json', 'British', true, 0, '2026-08-12 11:00:00'),
            $this->playlist(2, 'freetv.json', 'FreeTV', false, 1, '2026-08-12 10:00:00'),
        ];
        $this->showsByPlaylist = [
            1 => [$this->show(10, 'published-british', 'Published British Show')],
            2 => [$this->show(20, 'fixture-show', 'Fixture Show', '  Evening Comedy  ')],
        ];
        $this->publishedIndex = [
            'default' => 'freetv.json',
            'playlists' => [
                $this->indexEntry($this->playlists[0], '2026-08-12T09:00:00.000Z'),
                $this->indexEntry($this->playlists[1], '2026-08-12T08:00:00.000Z'),
            ],
        ];

        $this->unselectedArtifact = $this->encode(PlaylistPublicationSerializer::serialize(
            $this->playlists[0],
            $this->showsByPlaylist[1],
            '2026-08-12T09:00:00.000Z'
        ));
        $selectedArtifact = $this->encode(PlaylistPublicationSerializer::serialize(
            $this->playlists[1],
            [],
            '2026-08-12T08:00:00.000Z'
        ));

        $this->write('playlists/british.json', $this->unselectedArtifact);
        $this->write('playlists/freetv.json', $selectedArtifact);
        $this->write('playlists/index.json', $this->encode($this->publishedIndex));
        $this->write('config.json', "{\n    \"show_ads\": false\n}\n");
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

    public function testPublishesOnlySelectedPlaylistAndRegeneratesIndexBeforeTimestampUpdate(): void
    {
        $updates = [];
        $stateAtUpdate = null;
        $publicationRoot = $this->publicationRoot;
        $selectedBefore = file_get_contents($this->playlistDirectory . '/freetv.json');
        $indexBefore = file_get_contents($this->playlistDirectory . '/index.json');
        $undo = $this->undoService();
        $service = $this->service(
            static function (int $playlistId, string $timestamp) use (
                &$updates,
                &$stateAtUpdate,
                $publicationRoot
            ): void {
                $updates[] = [$playlistId, $timestamp];
                $stateAtUpdate = [
                    'playlist' => json_decode(
                        (string) file_get_contents($publicationRoot . '/playlists/freetv.json'),
                        true
                    ),
                    'index' => json_decode(
                        (string) file_get_contents($publicationRoot . '/playlists/index.json'),
                        true
                    ),
                ];
            },
            static fn(): DateTimeImmutable => new DateTimeImmutable('2026-08-12T16:30:00.987Z'),
            $undo
        );
        $result = $service->publish('freetv.json');

        $expectedTimestamp = '2026-08-12T16:30:00.000Z';
        $expectedPlaylist = PlaylistPublicationSerializer::serialize(
            $this->playlists[1],
            $this->showsByPlaylist[2],
            $expectedTimestamp
        );
        $expectedIndex = [
            'default' => 'british.json',
            'playlists' => [
                $this->indexEntry($this->playlists[0], '2026-08-12T09:00:00.000Z'),
                $this->indexEntry($this->playlists[1], $expectedTimestamp),
            ],
        ];

        self::assertSame(['filename' => 'freetv.json', 'lastupdated' => $expectedTimestamp], $result);
        self::assertSame($expectedPlaylist, $this->decode('playlists/freetv.json'));
        self::assertSame($expectedIndex, $this->decode('playlists/index.json'));
        self::assertSame($expectedPlaylist, $stateAtUpdate['playlist']);
        self::assertSame($expectedIndex, $stateAtUpdate['index']);
        self::assertSame([[2, '2026-08-12 16:30:00']], $updates);
        self::assertSame($this->unselectedArtifact, file_get_contents($this->playlistDirectory . '/british.json'));
        self::assertSame(0644, fileperms($this->playlistDirectory . '/freetv.json') & 0777);
        self::assertSame(0644, fileperms($this->playlistDirectory . '/index.json') & 0777);
        self::assertSame(
            ['available' => true, 'operation' => 'playlist', 'target' => 'freetv.json'],
            $undo->status()
        );

        $metadata = $this->decodeUndoMetadata();
        self::assertSame('playlist', $metadata['operation']);
        self::assertSame('freetv.json', $metadata['target']);
        self::assertSame(
            ['playlists/freetv.json', 'playlists/index.json'],
            array_column($metadata['files'], 'path')
        );
        self::assertSame(hash('sha256', $selectedBefore), $metadata['files'][0]['backup_hash']);
        self::assertSame(hash('sha256', $indexBefore), $metadata['files'][1]['backup_hash']);
        self::assertSame([], $this->temporaryArtifacts());
    }

    public function testRepeatedExplicitPublicationPublishesAgainAndReplacesUndo(): void
    {
        $updates = [];
        $times = [
            new DateTimeImmutable('2026-08-12T16:30:00.987Z'),
            new DateTimeImmutable('2026-08-12T16:45:00.456Z'),
        ];
        $undo = $this->undoService();
        $service = $this->service(
            static function (int $playlistId, string $timestamp) use (&$updates): void {
                $updates[] = [$playlistId, $timestamp];
            },
            static function () use (&$times): DateTimeImmutable {
                return array_shift($times);
            },
            $undo
        );

        $firstResult = $service->publish('freetv.json');
        $firstPlaylist = (string) file_get_contents($this->playlistDirectory . '/freetv.json');
        $firstIndex = (string) file_get_contents($this->playlistDirectory . '/index.json');
        $secondResult = $service->publish('freetv.json');

        self::assertSame('2026-08-12T16:30:00.000Z', $firstResult['lastupdated']);
        self::assertSame('2026-08-12T16:45:00.000Z', $secondResult['lastupdated']);
        self::assertSame(
            [[2, '2026-08-12 16:30:00'], [2, '2026-08-12 16:45:00']],
            $updates
        );
        self::assertSame(
            $firstPlaylist,
            file_get_contents($this->undoRoot . '/active/files/playlists/freetv.json')
        );
        self::assertSame(
            $firstIndex,
            file_get_contents($this->undoRoot . '/active/files/playlists/index.json')
        );
        self::assertSame($this->unselectedArtifact, file_get_contents($this->playlistDirectory . '/british.json'));
        self::assertSame([], $this->temporaryArtifacts());
    }

    public function testTimestampUpdateFailureRestoresArtifactsAndPreservesPriorUndo(): void
    {
        $undo = $this->undoService();
        $this->activateConfigUndo($undo);
        $artifactsBefore = $this->publicationHashes();
        $undoBefore = $this->treeHashes($this->undoRoot . '/active');
        $statusBefore = $undo->status();
        $stateAtFailure = null;
        $updates = [];
        $service = $this->service(
            function (int $playlistId, string $timestamp) use (&$stateAtFailure, &$updates): void {
                $updates[] = [$playlistId, $timestamp];
                $stateAtFailure = [
                    $this->decode('playlists/freetv.json')['lastupdated'],
                    $this->decode('playlists/index.json')['playlists'][1]['lastupdated'],
                ];
                throw new RuntimeException('Simulated timestamp update failure');
            },
            static fn(): DateTimeImmutable => new DateTimeImmutable('2026-08-12T17:00:00Z'),
            $undo
        );

        $exception = $this->captureFailure(fn() => $service->publish('freetv.json'));

        self::assertSame(500, $exception->getHttpStatus());
        self::assertSame(
            'Playlist publication failed: Simulated timestamp update failure',
            $exception->getMessage()
        );
        self::assertSame(
            ['2026-08-12T17:00:00.000Z', '2026-08-12T17:00:00.000Z'],
            $stateAtFailure
        );
        self::assertSame([[2, '2026-08-12 17:00:00']], $updates);
        self::assertSame($artifactsBefore, $this->publicationHashes());
        self::assertSame($statusBefore, $undo->status());
        self::assertSame($undoBefore, $this->treeHashes($this->undoRoot . '/active'));
        self::assertSame([], $this->temporaryArtifacts());
    }

    #[DataProvider('targetValidationProvider')]
    public function testRejectsInvalidTargetWithoutMutation(string $filename, int $status): void
    {
        $this->assertRejectedWithoutMutation($filename, $status);
    }

    public static function targetValidationProvider(): array
    {
        return [
            'unsafe filename' => ['../freetv.json', 400],
            'unknown playlist' => ['missing.json', 404],
        ];
    }

    #[DataProvider('publishedIndexValidationProvider')]
    public function testRejectsInvalidPublishedIndexWithoutMutation(string $scenario): void
    {
        if ($scenario === 'missing index') {
            unlink($this->playlistDirectory . '/index.json');
        } elseif ($scenario === 'malformed JSON') {
            $this->write('playlists/index.json', '{invalid json');
        } elseif ($scenario === 'missing unselected entry') {
            $index = $this->publishedIndex;
            $index['playlists'] = [$index['playlists'][1]];
            $this->write('playlists/index.json', $this->encode($index));
        } elseif ($scenario === 'duplicate filename') {
            $index = $this->publishedIndex;
            $index['playlists'][] = $index['playlists'][0];
            $this->write('playlists/index.json', $this->encode($index));
        } elseif ($scenario === 'invalid unselected timestamp') {
            $index = $this->publishedIndex;
            $index['playlists'][0]['lastupdated'] = 'not-a-timestamp';
            $this->write('playlists/index.json', $this->encode($index));
        }

        $this->assertRejectedWithoutMutation('freetv.json', 409);
    }

    public static function publishedIndexValidationProvider(): array
    {
        return [
            'missing index' => ['missing index'],
            'malformed JSON' => ['malformed JSON'],
            'missing unselected entry' => ['missing unselected entry'],
            'duplicate filename' => ['duplicate filename'],
            'invalid unselected timestamp' => ['invalid unselected timestamp'],
        ];
    }

    private function assertRejectedWithoutMutation(string $filename, int $status): void
    {
        $undo = $this->undoService();
        $this->activateConfigUndo($undo);
        $artifactsBefore = $this->publicationHashes();
        $undoBefore = $this->treeHashes($this->undoRoot . '/active');
        $statusBefore = $undo->status();
        $updates = [];
        $service = $this->service(
            static function (int $playlistId, string $timestamp) use (&$updates): void {
                $updates[] = [$playlistId, $timestamp];
            },
            static fn(): DateTimeImmutable => new DateTimeImmutable('2026-08-12T18:00:00Z'),
            $undo
        );

        $exception = $this->captureFailure(fn() => $service->publish($filename));

        self::assertSame($status, $exception->getHttpStatus());
        self::assertSame([], $updates);
        self::assertSame($artifactsBefore, $this->publicationHashes());
        self::assertSame($statusBefore, $undo->status());
        self::assertSame($undoBefore, $this->treeHashes($this->undoRoot . '/active'));
        self::assertSame([], $this->temporaryArtifacts());
    }

    private function service(callable $updater, callable $clock, PublicationUndoService $undo): PlaylistPublicationService
    {
        return new PlaylistPublicationService(
            $this->publicationRoot,
            fn(): array => $this->playlists,
            fn(int $playlistId): array => $this->showsByPlaylist[$playlistId],
            $updater,
            $clock,
            $undo
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
            $this->write('config.json', "{\n    \"show_ads\": true\n}\n");
            $locked->promote($prepared);
        });
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

    private function playlist(
        int $id,
        string $filename,
        string $title,
        bool $default,
        int $sortOrder,
        string $lastUpdated
    ): array {
        return [
            'id' => $id,
            'filename' => $filename,
            'dbtitle' => $title,
            'dbversion' => '1.0',
            'author' => 'Free TV',
            'email' => 'support@example.test',
            'link' => 'https://example.test',
            'lastupdated' => $lastUpdated,
            'is_default' => $default ? 1 : 0,
            'sort_order' => $sortOrder,
        ];
    }

    private function show(int $id, string $identifier, string $title, ?string $group = null): array
    {
        return [
            'id' => $id,
            'sort_order' => 0,
            'category' => 'comedy',
            'status' => 'active',
            'identifier' => $identifier,
            'title' => $title,
            'description' => 'A fixture',
            'start_year' => '1960',
            'end_year' => '1961',
            'imdb' => 'tt' . str_pad((string) $id, 7, '0', STR_PAD_LEFT),
            'group_name' => $group,
        ];
    }

    private function indexEntry(array $playlist, string $timestamp): array
    {
        return [
            'filename' => $playlist['filename'],
            'dbtitle' => $playlist['dbtitle'],
            'lastupdated' => $timestamp,
            'author' => $playlist['author'],
        ];
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
        $directory = dirname($path);
        if (!is_dir($directory) && !mkdir($directory, 0700, true)) {
            self::fail('Could not create fixture directory');
        }
        if (file_put_contents($path, $contents) !== strlen($contents)) {
            self::fail('Could not write fixture ' . $relativePath);
        }
    }

    private function decode(string $relativePath): array
    {
        return json_decode(
            (string) file_get_contents($this->publicationRoot . '/' . $relativePath),
            true,
            512,
            JSON_THROW_ON_ERROR
        );
    }

    private function decodeUndoMetadata(): array
    {
        return json_decode(
            (string) file_get_contents($this->undoRoot . '/active/operation.json'),
            true,
            512,
            JSON_THROW_ON_ERROR
        );
    }

    private function publicationHashes(): array
    {
        return $this->treeHashes($this->publicationRoot);
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
            if ($item->isFile()) {
                $hashes[substr($item->getPathname(), strlen($root) + 1)] = hash_file(
                    'sha256',
                    $item->getPathname()
                );
            }
        }
        ksort($hashes);
        return $hashes;
    }

    private function temporaryArtifacts(): array
    {
        return array_merge(
            glob($this->playlistDirectory . '/.publish-*') ?: [],
            glob($this->undoRoot . '/.prepared-*') ?: [],
            glob($this->undoRoot . '/.prior-*') ?: [],
            glob($this->undoRoot . '/.restore-*') ?: []
        );
    }
}
