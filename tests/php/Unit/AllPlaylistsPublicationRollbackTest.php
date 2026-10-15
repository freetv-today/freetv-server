<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../public/api/admin/publication/AllPlaylistsPublicationService.php';

use FreeTV\Admin\Publication\AllPlaylistsPublicationService;
use FreeTV\Admin\Publication\PlaylistPublicationSerializer;
use FreeTV\Admin\Publication\PublicationException;
use FreeTV\Admin\Publication\PublicationUndoService;
use PHPUnit\Framework\TestCase;

final class AllPlaylistsPublicationRollbackTest extends TestCase
{
    private string $root;
    private string $publicationRoot;
    private string $undoRoot;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/freetv-publish-all-rollback-' . bin2hex(random_bytes(8));
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

    public function testFailedPublishAllRestoresTimestampForPreviouslyUnpublishedPlaylist(): void
    {
        $playlists = [
            $this->playlist(1, 'new.json', 'Never Published', false, 0, null),
            $this->playlist(2, 'existing.json', 'Existing Playlist', true, 1, '2026-09-01 09:15:00'),
            $this->playlist(3, 'unchanged.json', 'Unchanged Playlist', false, 2, '2026-09-01 10:30:00'),
        ];
        $authoritativeShows = [
            1 => [$this->show(1, 'new-show', 'New Show')],
            2 => [$this->show(2, 'existing-show', 'Changed Existing Show')],
            3 => [$this->show(3, 'unchanged-show', 'Unchanged Show')],
        ];
        $publishedShows = $authoritativeShows;
        $publishedShows[2][0]['title'] = 'Previously Published Existing Show';

        $publishedTimestamps = [
            'existing.json' => '2026-08-20T08:00:00.000Z',
            'unchanged.json' => '2026-08-20T08:30:00.000Z',
        ];
        $existingArtifact = $this->playlistArtifact(
            $playlists[1],
            $publishedShows[2],
            $publishedTimestamps['existing.json']
        );
        $unchangedArtifact = $this->playlistArtifact(
            $playlists[2],
            $publishedShows[3],
            $publishedTimestamps['unchanged.json']
        );
        $index = $this->encode([
            'default' => 'existing.json',
            'playlists' => [
                $this->indexEntry($playlists[1], $publishedTimestamps['existing.json']),
                $this->indexEntry($playlists[2], $publishedTimestamps['unchanged.json']),
            ],
        ]);

        $this->write('playlists/existing.json', $existingArtifact);
        $this->write('playlists/unchanged.json', $unchangedArtifact);
        $this->write('playlists/index.json', $index);
        $this->write('config.json', "{\n    \"lastupdated\": \"prior-config\",\n    \"show_ads\": false\n}\n");
        $this->write('playlists/unrelated.txt', "unrelated publication file\n");

        $undoService = new PublicationUndoService(
            $this->publicationRoot,
            $this->undoRoot,
            static function (): void {
                self::fail('The preserved config Undo state should not be executed');
            }
        );
        $undoService->withLock(function (PublicationUndoService $locked): void {
            $prepared = $locked->prepare('config', 'config.json', ['config.json']);
            $this->write(
                'config.json',
                "{\n    \"lastupdated\": \"active-config\",\n    \"show_ads\": true\n}\n"
            );
            $locked->promote($prepared);
        });

        $undoStatusBefore = $undoService->status();
        $activeUndoBefore = $this->treeHashes($this->undoRoot . '/active');
        $activeMetadataBefore = file_get_contents($this->undoRoot . '/active/operation.json');
        $unrelatedBefore = file_get_contents($this->publicationRoot . '/playlists/unrelated.txt');

        $databaseTimestamps = [
            1 => null,
            2 => '2026-09-01 09:15:00',
            3 => '2026-09-01 10:30:00',
        ];
        $timestampsBefore = $databaseTimestamps;
        $timestampCalls = [];
        $publicationStateAtFirstTimestampUpdate = null;
        $publicationDatabaseTimestamp = '2026-09-10 14:00:00';
        $newArtifactPath = $this->publicationRoot . '/playlists/new.json';
        $indexPath = $this->publicationRoot . '/playlists/index.json';

        $service = new AllPlaylistsPublicationService(
            $this->publicationRoot,
            static fn(): array => $playlists,
            static fn(int $playlistId): array => $authoritativeShows[$playlistId],
            static function (int $playlistId, ?string $timestamp) use (
                &$databaseTimestamps,
                &$timestampCalls,
                &$publicationStateAtFirstTimestampUpdate,
                $publicationDatabaseTimestamp,
                $newArtifactPath,
                $indexPath
            ): ?string {
                $timestampCalls[] = [$playlistId, $timestamp];
                if ($playlistId === 1 && $timestamp === $publicationDatabaseTimestamp) {
                    $publicationStateAtFirstTimestampUpdate = [
                        'new_artifact_exists' => is_file($newArtifactPath),
                        'index_filenames' => array_column(
                            json_decode(
                                (string) file_get_contents($indexPath),
                                true,
                                32,
                                JSON_THROW_ON_ERROR
                            )['playlists'],
                            'filename'
                        ),
                    ];
                }
                if ($playlistId === 2 && $timestamp === $publicationDatabaseTimestamp) {
                    throw new RuntimeException('Simulated Playlist B timestamp update failure');
                }

                $previous = $databaseTimestamps[$playlistId];
                $databaseTimestamps[$playlistId] = $timestamp;
                return $previous;
            },
            static fn(): DateTimeImmutable => new DateTimeImmutable('2026-09-10T14:00:00.987Z'),
            $undoService
        );

        $caught = null;
        try {
            $service->publish();
        } catch (PublicationException $exception) {
            $caught = $exception;
        }

        self::assertInstanceOf(PublicationException::class, $caught);
        self::assertSame(
            'Playlist-content publication failed: Simulated Playlist B timestamp update failure',
            $caught->getMessage()
        );
        self::assertSame(500, $caught->getHttpStatus());

        self::assertSame($existingArtifact, file_get_contents($this->publicationRoot . '/playlists/existing.json'));
        self::assertSame($index, file_get_contents($this->publicationRoot . '/playlists/index.json'));
        self::assertSame($unchangedArtifact, file_get_contents($this->publicationRoot . '/playlists/unchanged.json'));
        self::assertFileDoesNotExist($this->publicationRoot . '/playlists/new.json');
        self::assertNotContains(
            'new.json',
            array_column(
                json_decode(
                    (string) file_get_contents($this->publicationRoot . '/playlists/index.json'),
                    true,
                    32,
                    JSON_THROW_ON_ERROR
                )['playlists'],
                'filename'
            )
        );
        self::assertSame($unrelatedBefore, file_get_contents($this->publicationRoot . '/playlists/unrelated.txt'));

        self::assertSame($timestampsBefore[2], $databaseTimestamps[2]);
        self::assertSame($timestampsBefore[3], $databaseTimestamps[3]);
        self::assertSame(
            [
                'new_artifact_exists' => true,
                'index_filenames' => ['new.json', 'existing.json', 'unchanged.json'],
            ],
            $publicationStateAtFirstTimestampUpdate
        );
        self::assertSame(
            [
                [1, $publicationDatabaseTimestamp],
                [2, $publicationDatabaseTimestamp],
                [1, null],
            ],
            $timestampCalls
        );

        self::assertSame($undoStatusBefore, $undoService->status());
        self::assertSame($activeMetadataBefore, file_get_contents($this->undoRoot . '/active/operation.json'));
        self::assertSame($activeUndoBefore, $this->treeHashes($this->undoRoot . '/active'));
        self::assertSame([], glob($this->undoRoot . '/.prepared-*') ?: []);
        self::assertSame([], glob($this->undoRoot . '/.prior-*') ?: []);
        self::assertSame([], glob($this->undoRoot . '/.restore-*') ?: []);
        self::assertSame([], glob($this->publicationRoot . '/playlists/.publish-all-*') ?: []);

        self::assertSame(
            $timestampsBefore[1],
            $databaseTimestamps[1],
            'Rollback must restore the exact pre-operation timestamp, including NULL, for a newly published playlist'
        );
    }

    public function testRollbackRestoresDatabaseTimestampInsteadOfPublishedTimestamp(): void
    {
        $playlists = [
            $this->playlist(10, 'first.json', 'First Playlist', true, 0, '2026-09-02 11:45:00'),
            $this->playlist(20, 'second.json', 'Second Playlist', false, 1, '2026-09-02 12:30:00'),
        ];
        $authoritativeShows = [
            10 => [$this->show(10, 'first-show', 'Changed First Show')],
            20 => [$this->show(20, 'second-show', 'Changed Second Show')],
        ];
        $publishedShows = $authoritativeShows;
        $publishedShows[10][0]['title'] = 'Published First Show';
        $publishedShows[20][0]['title'] = 'Published Second Show';
        $publishedTimestamps = [
            'first.json' => '2026-08-15T07:00:00.000Z',
            'second.json' => '2026-08-15T08:00:00.000Z',
        ];

        foreach ($playlists as $playlist) {
            $this->write(
                'playlists/' . $playlist['filename'],
                $this->playlistArtifact(
                    $playlist,
                    $publishedShows[$playlist['id']],
                    $publishedTimestamps[$playlist['filename']]
                )
            );
        }
        $this->write('playlists/index.json', $this->encode([
            'default' => 'first.json',
            'playlists' => [
                $this->indexEntry($playlists[0], $publishedTimestamps['first.json']),
                $this->indexEntry($playlists[1], $publishedTimestamps['second.json']),
            ],
        ]));

        $databaseTimestamps = [
            10 => '2026-09-02 11:45:00',
            20 => '2026-09-02 12:30:00',
        ];
        $timestampsBefore = $databaseTimestamps;
        $timestampCalls = [];
        $publicationDatabaseTimestamp = '2026-09-10 15:00:00';
        $undoService = new PublicationUndoService($this->publicationRoot, $this->undoRoot);
        $service = new AllPlaylistsPublicationService(
            $this->publicationRoot,
            static fn(): array => $playlists,
            static fn(int $playlistId): array => $authoritativeShows[$playlistId],
            static function (int $playlistId, ?string $timestamp) use (
                &$databaseTimestamps,
                &$timestampCalls,
                $publicationDatabaseTimestamp
            ): void {
                $timestampCalls[] = [$playlistId, $timestamp];
                if ($playlistId === 20 && $timestamp === $publicationDatabaseTimestamp) {
                    throw new RuntimeException('Simulated second playlist timestamp update failure');
                }
                $databaseTimestamps[$playlistId] = $timestamp;
            },
            static fn(): DateTimeImmutable => new DateTimeImmutable('2026-09-10T15:00:00Z'),
            $undoService
        );

        $caught = null;
        try {
            $service->publish();
        } catch (PublicationException $exception) {
            $caught = $exception;
        }

        self::assertInstanceOf(PublicationException::class, $caught);
        self::assertSame($timestampsBefore, $databaseTimestamps);
        self::assertNotSame('2026-08-15 07:00:00', $databaseTimestamps[10]);
        self::assertSame(
            [
                [10, $publicationDatabaseTimestamp],
                [20, $publicationDatabaseTimestamp],
                [10, $timestampsBefore[10]],
            ],
            $timestampCalls
        );
    }

    private function playlist(
        int $id,
        string $filename,
        string $dbtitle,
        bool $isDefault,
        int $sortOrder,
        ?string $lastupdated
    ): array {
        return [
            'id' => $id,
            'filename' => $filename,
            'dbtitle' => $dbtitle,
            'dbversion' => '1.0',
            'author' => 'FreeTV',
            'email' => 'admin@example.test',
            'link' => 'https://example.test',
            'is_default' => $isDefault ? 1 : 0,
            'sort_order' => $sortOrder,
            'lastupdated' => $lastupdated,
        ];
    }

    private function show(int $id, string $identifier, string $title): array
    {
        return [
            'id' => $id,
            'sort_order' => 0,
            'category' => 'Comedy',
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

    private function playlistArtifact(array $playlist, array $shows, string $timestamp): string
    {
        return $this->encode(PlaylistPublicationSerializer::serialize($playlist, $shows, $timestamp));
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
            throw new RuntimeException('Could not write test fixture ' . $relativePath);
        }
    }

    private function treeHashes(string $root): array
    {
        $hashes = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $item) {
            if ($item->isFile()) {
                $relativePath = substr($item->getPathname(), strlen($root) + 1);
                $hashes[$relativePath] = hash_file('sha256', $item->getPathname());
            }
        }
        ksort($hashes);
        return $hashes;
    }
}
