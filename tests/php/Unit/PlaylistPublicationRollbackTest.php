<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../public/api/admin/publication/PlaylistPublicationService.php';

use FreeTV\Admin\Publication\PlaylistPublicationSerializer;
use FreeTV\Admin\Publication\PlaylistPublicationService;
use FreeTV\Admin\Publication\PublicationException;
use FreeTV\Admin\Publication\PublicationUndoService;
use PHPUnit\Framework\TestCase;

final class PlaylistPublicationRollbackTest extends TestCase
{
    private string $root;
    private string $publicationRoot;
    private string $playlistDirectory;
    private string $undoRoot;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/freetv-playlist-rollback-' . bin2hex(random_bytes(8));
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

    public function testPromotionFailureRestoresAuthoritativeDatabaseTimestamp(): void
    {
        $playlist = [
            'id' => 42,
            'filename' => 'freetv.json',
            'dbtitle' => 'FreeTV',
            'dbversion' => '1.0',
            'author' => 'Free TV',
            'email' => 'support@example.test',
            'link' => 'https://example.test',
            'lastupdated' => '2026-08-12 11:00:00',
            'is_default' => 1,
            'sort_order' => 0,
        ];
        $shows = [[
            'id' => 1,
            'sort_order' => 0,
            'category' => 'comedy',
            'status' => 'active',
            'identifier' => 'fixture-show',
            'title' => 'Changed Fixture Show',
            'description' => 'A fixture',
            'start_year' => '1960',
            'end_year' => '1961',
            'imdb' => 'tt0000001',
            'group_name' => null,
        ]];
        $publishedTimestamp = '2026-08-12T09:00:00.000Z';
        $playlistBefore = $this->encode(PlaylistPublicationSerializer::serialize(
            $playlist,
            [],
            $publishedTimestamp
        ));
        $indexBefore = $this->encode([
            'default' => 'freetv.json',
            'playlists' => [[
                'filename' => 'freetv.json',
                'dbtitle' => 'FreeTV',
                'lastupdated' => $publishedTimestamp,
                'author' => 'Free TV',
            ]],
        ]);
        $unrelatedBefore = "unrelated public artifact\n";

        $this->write('playlists/freetv.json', $playlistBefore);
        $this->write('playlists/index.json', $indexBefore);
        $this->write('playlists/unrelated.txt', $unrelatedBefore);
        $this->write('config.json', "{\n    \"show_ads\": false\n}\n");

        $realUndo = new PublicationUndoService(
            $this->publicationRoot,
            $this->undoRoot,
            static function (): void {
                self::fail('The preserved config Undo should not be executed');
            }
        );
        $realUndo->withLock(function (PublicationUndoService $locked): void {
            $prepared = $locked->prepare('config', 'config.json', ['config.json']);
            $this->write('config.json', "{\n    \"show_ads\": true\n}\n");
            $locked->promote($prepared);
        });

        $undoStatusBefore = $realUndo->status();
        $undoMetadataBefore = (string) file_get_contents($this->undoRoot . '/active/operation.json');
        $undoHashesBefore = $this->treeHashes($this->undoRoot . '/active');
        $databaseTimestamp = '2026-08-12 11:00:00';
        $timestampCalls = [];
        $databaseStateAfterCalls = [];

        $failingUndo = new class(
            $this->publicationRoot,
            $this->undoRoot,
            static function (): void {}
        ) extends PublicationUndoService {
            public function promote(string $preparedName): void
            {
                throw new RuntimeException('Simulated Undo promotion failure');
            }
        };
        $service = new PlaylistPublicationService(
            $this->publicationRoot,
            static fn(): array => [$playlist],
            static fn(int $playlistId): array => $shows,
            static function (int $playlistId, string $timestamp) use (
                &$databaseTimestamp,
                &$timestampCalls,
                &$databaseStateAfterCalls
            ): void {
                $timestampCalls[] = [$playlistId, $timestamp];
                $databaseTimestamp = $timestamp;
                $databaseStateAfterCalls[] = $databaseTimestamp;
            },
            static fn(): DateTimeImmutable => new DateTimeImmutable('2026-08-12T17:00:00.000Z'),
            $failingUndo
        );

        $exception = null;
        try {
            $service->publish('freetv.json');
        } catch (PublicationException $caught) {
            $exception = $caught;
        }

        self::assertInstanceOf(PublicationException::class, $exception);
        self::assertSame(
            'Playlist publication failed: Simulated Undo promotion failure',
            $exception->getMessage()
        );
        self::assertSame(500, $exception->getHttpStatus());

        self::assertCount(2, $timestampCalls);
        self::assertSame([42, '2026-08-12 17:00:00'], $timestampCalls[0]);
        self::assertSame('2026-08-12 17:00:00', $databaseStateAfterCalls[0]);
        self::assertSame(42, $timestampCalls[1][0]);

        self::assertSame($playlistBefore, file_get_contents($this->playlistDirectory . '/freetv.json'));
        self::assertSame($indexBefore, file_get_contents($this->playlistDirectory . '/index.json'));
        self::assertSame($unrelatedBefore, file_get_contents($this->playlistDirectory . '/unrelated.txt'));

        self::assertSame($undoStatusBefore, $realUndo->status());
        self::assertSame(
            $undoMetadataBefore,
            file_get_contents($this->undoRoot . '/active/operation.json')
        );
        self::assertSame($undoHashesBefore, $this->treeHashes($this->undoRoot . '/active'));
        self::assertSame([], glob($this->undoRoot . '/.prepared-*') ?: []);
        self::assertSame([], glob($this->undoRoot . '/.prior-*') ?: []);
        self::assertSame([], glob($this->undoRoot . '/.restore-*') ?: []);
        self::assertSame([], glob($this->playlistDirectory . '/.publish-*') ?: []);

        self::assertSame(
            '2026-08-12 11:00:00',
            $databaseTimestamp,
            'Rollback must restore the authoritative pre-publication database timestamp, not the old published JSON timestamp'
        );
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
