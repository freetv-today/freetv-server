<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../public/api/admin/ThumbnailOrphanCleanupService.php';
require_once __DIR__ . '/../../../public/api/admin/DatasetPackage.php';

use FreeTV\Admin\DatasetPackage;
use FreeTV\Admin\ServerPaths;
use FreeTV\Admin\ThumbnailOrphanCleanupException;
use FreeTV\Admin\ThumbnailOrphanCleanupService;
use PHPUnit\Framework\TestCase;

final class ThumbnailOrphanCleanupServiceTest extends TestCase
{
    private string $root;
    private string $thumbs;
    private ServerPaths $paths;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/freetv-thumb-orphans-' . bin2hex(random_bytes(8));
        $this->thumbs = $this->root . '/public/thumbs';
        self::assertTrue(mkdir($this->thumbs, 0700, true));
        $this->paths = new ServerPaths($this->root);
    }

    protected function tearDown(): void
    {
        DatasetPackage::removeTree($this->root);
    }

    public function testDryRunIsDeterministicAndHasNoFilesystemOrReferenceSideEffects(): void
    {
        file_put_contents($this->thumbs . '/tt0000100.jpg', 'orphan');
        file_put_contents($this->thumbs . '/index.html', 'support');
        $calls = [];
        $before = $this->treeState($this->root);
        $service = new ThumbnailOrphanCleanupService(
            $this->paths,
            fn(): array => $this->audit(),
            static function (string $imdb) use (&$calls): bool {
                $calls[] = $imdb;
                return false;
            },
            static fn(): DateTimeImmutable => new DateTimeImmutable('2026-08-29T13:00:00.123Z')
        );

        $result = $service->run();

        self::assertSame('dry-run', $result['mode']);
        self::assertSame(5, $result['orphan_count']);
        self::assertSame(
            ['tt0000100.jpg', 'tt0000200.jpg', 'tt0000400.jpg', 'tt0000500.jpg', 'tt0000600.jpg'],
            array_column($result['candidates'], 'filename')
        );
        self::assertSame([], $result['moved']);
        self::assertSame([], $result['skipped']);
        self::assertSame([], $result['failed']);
        self::assertNull($result['manifest']);
        self::assertSame([], $calls);
        self::assertSame($before, $this->treeState($this->root));
        self::assertDirectoryDoesNotExist($this->root . '/temp');
    }

    public function testApplyQuarantinesOnlySafeUnreferencedFilesAndRecordsPartialFailures(): void
    {
        foreach ([
            'tt0000100.jpg' => 'move this orphan',
            'tt0000200.jpg' => 'becomes referenced',
            'tt0000300.jpg' => 'shared referenced thumbnail',
            'tt0000400.jpg' => 'becomes unsafe symlink',
            'tt0000500.jpg' => 'destination collision',
            'tt0000600.jpg' => 'move failure source',
            'index.html' => 'support file',
        ] as $filename => $contents) {
            file_put_contents($this->thumbs . '/' . $filename, $contents);
        }
        file_put_contents($this->root . '/outside.jpg', 'outside symlink target');
        $databaseRows = [
            ['imdb' => 'tt0000200'],
            ['imdb' => 'tt0000300'],
            ['imdb' => 'tt0000300'],
        ];
        $databaseRowsBefore = $databaseRows;
        $checks = [];
        $checker = function (string $imdb) use (&$checks, $databaseRows): bool {
            $checks[] = $imdb;
            $batch = $this->root . '/temp/thumbnail-quarantine/20260829T130000Z';
            if ($imdb === 'tt0000500') {
                file_put_contents($batch . '/tt0000500.jpg', 'existing quarantine evidence');
            }
            if ($imdb === 'tt0000400') {
                unlink($this->thumbs . '/tt0000400.jpg');
                symlink($this->root . '/outside.jpg', $this->thumbs . '/tt0000400.jpg');
            }
            return in_array($imdb, array_column($databaseRows, 'imdb'), true);
        };
        $mover = static fn(string $source, string $destination): bool =>
            basename($source) !== 'tt0000600.jpg' && rename($source, $destination);
        $service = new ThumbnailOrphanCleanupService(
            $this->paths,
            fn(): array => $this->audit(),
            $checker,
            static fn(): DateTimeImmutable => new DateTimeImmutable('2026-08-29T13:00:00.123Z'),
            $mover
        );

        $result = $service->run(true);
        $batch = $this->root . '/temp/thumbnail-quarantine/20260829T130000Z';

        self::assertSame(['tt0000100.jpg'], array_column($result['moved'], 'filename'));
        self::assertSame(['tt0000200.jpg'], array_column($result['skipped'], 'filename'));
        self::assertSame(
            ['unsafe_or_missing_source', 'destination_exists', 'move_failed'],
            array_column($result['failed'], 'reason')
        );
        self::assertSame(
            ['tt0000100', 'tt0000200', 'tt0000400', 'tt0000500', 'tt0000600'],
            $checks
        );
        self::assertSame($databaseRowsBefore, $databaseRows);
        self::assertFileDoesNotExist($this->thumbs . '/tt0000100.jpg');
        self::assertSame('move this orphan', file_get_contents($batch . '/tt0000100.jpg'));
        foreach (['tt0000200.jpg', 'tt0000300.jpg', 'tt0000500.jpg', 'tt0000600.jpg', 'index.html'] as $filename) {
            self::assertFileExists($this->thumbs . '/' . $filename);
        }
        self::assertTrue(is_link($this->thumbs . '/tt0000400.jpg'));

        $manifestPath = $batch . '/manifest.json';
        $manifest = json_decode((string) file_get_contents($manifestPath), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame(1, $manifest['format_version']);
        self::assertSame('2026-08-29T13:00:00.123Z', $manifest['created_at']);
        self::assertSame([1, 1, 3], [
            $manifest['moved_count'],
            $manifest['skipped_count'],
            $manifest['failed_count'],
        ]);
        self::assertSame(hash('sha256', 'move this orphan'), $manifest['files'][0]['sha256']);
        self::assertSame($manifestPath, $result['manifest']);
        self::assertSame(0700, fileperms($batch) & 0777);
        self::assertSame(0600, fileperms($manifestPath) & 0777);

        $publicBefore = $this->treeState($this->root . '/public');
        $this->expectCleanupFailure(fn() => $service->run(true), 'batch directory already exists');
        self::assertSame($publicBefore, $this->treeState($this->root . '/public'));
    }

    public function testInvalidAuditResultsAndCandidatesAreRejectedBeforePrivateState(): void
    {
        foreach ([
            [],
            ['summary' => [], 'orphaned' => 'not-an-array'],
            ['summary' => [], 'orphaned' => [['imdb' => '../bad', 'filename' => '../bad.jpg']]],
            ['summary' => [], 'orphaned' => [['imdb' => 'tt123', 'filename' => 'tt124.jpg']]],
        ] as $audit) {
            $service = new ThumbnailOrphanCleanupService($this->paths, static fn(): array => $audit);
            $this->expectCleanupFailure(fn() => $service->run(true), 'invalid');
            self::assertDirectoryDoesNotExist($this->root . '/temp');
        }
    }

    public function testApplyReportsReferenceHashAndMissingSourceFailures(): void
    {
        foreach (['tt1.jpg', 'tt2.jpg', 'tt3.jpg'] as $filename) {
            file_put_contents($this->thumbs . '/' . $filename, $filename);
        }
        $audit = [
            'summary' => ['orphaned' => 4],
            'orphaned' => [
                ['imdb' => 'tt1', 'filename' => 'tt1.jpg'],
                ['imdb' => 'tt2', 'filename' => 'tt2.jpg'],
                ['imdb' => 'tt3', 'filename' => 'tt3.jpg'],
                ['imdb' => 'tt4', 'filename' => 'tt4.jpg'],
            ],
        ];
        $service = new ThumbnailOrphanCleanupService(
            $this->paths,
            static fn(): array => $audit,
            static function (string $imdb): bool {
                if ($imdb === 'tt1') {
                    throw new RuntimeException('database unavailable');
                }
                return false;
            },
            static fn(): DateTimeImmutable => new DateTimeImmutable('2026-08-30T00:00:00Z'),
            null,
            static fn(string $path): string|false => basename($path) === 'tt2.jpg'
                ? false
                : hash_file('sha256', $path)
        );

        $result = $service->run(true);

        self::assertSame(['reference_check_failed', 'hash_failed', 'unsafe_or_missing_source'], [
            $result['failed'][0]['reason'],
            $result['failed'][1]['reason'],
            $result['failed'][2]['reason'],
        ]);
        self::assertSame(['tt3.jpg'], array_column($result['moved'], 'filename'));
        self::assertFileExists($this->thumbs . '/tt1.jpg');
        self::assertFileExists($this->thumbs . '/tt2.jpg');
        self::assertFileDoesNotExist($this->thumbs . '/tt3.jpg');
    }

    public function testPlaceholderCandidateIsIgnoredAndQuarantineMustRemainPrivate(): void
    {
        $audit = [
            'summary' => [],
            'orphaned' => [['imdb' => 'tt000000', 'filename' => 'tt000000.jpg']],
        ];
        $service = new ThumbnailOrphanCleanupService($this->paths, static fn(): array => $audit);
        $result = $service->run();
        self::assertSame(0, $result['orphan_count']);

        self::assertTrue(mkdir($this->root . '/temp/thumbnail-quarantine', 0777, true));
        chmod($this->root . '/temp/thumbnail-quarantine', 0755);
        $audit['orphaned'] = [['imdb' => 'tt9', 'filename' => 'tt9.jpg']];
        $service = new ThumbnailOrphanCleanupService($this->paths, static fn(): array => $audit);
        $this->expectCleanupFailure(fn() => $service->run(true), 'permissions are not private');
    }

    private function audit(): array
    {
        return [
            'summary' => [
                'total_distinct_valid_imdb_references' => 3,
                'total_thumbnail_jpg_files_considered' => 6,
                'present' => 2,
                'missing' => 1,
                'orphaned' => 5,
                'invalid_database_imdb_values' => 0,
                'invalid_database_imdb_rows' => 0,
            ],
            'orphaned' => [
                ['imdb' => 'tt0000600', 'filename' => 'tt0000600.jpg'],
                ['imdb' => 'tt0000500', 'filename' => 'tt0000500.jpg'],
                ['imdb' => 'tt0000400', 'filename' => 'tt0000400.jpg'],
                ['imdb' => 'tt0000200', 'filename' => 'tt0000200.jpg'],
                ['imdb' => 'tt0000100', 'filename' => 'tt0000100.jpg'],
            ],
        ];
    }

    private function treeState(string $root): array
    {
        if (!is_dir($root)) {
            return [];
        }
        $state = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $item) {
            $relative = substr($item->getPathname(), strlen($root) + 1);
            if ($item->isLink()) {
                $state[$relative] = 'symlink:' . readlink($item->getPathname());
            } elseif ($item->isFile()) {
                $state[$relative] = hash_file('sha256', $item->getPathname());
            }
        }
        ksort($state, SORT_STRING);
        return $state;
    }

    private function expectCleanupFailure(callable $operation, string $message): void
    {
        try {
            $operation();
            self::fail('Expected ThumbnailOrphanCleanupException');
        } catch (ThumbnailOrphanCleanupException $exception) {
            self::assertStringContainsString($message, $exception->getMessage());
        }
    }
}
