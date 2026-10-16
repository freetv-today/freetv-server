<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../public/api/admin/DatasetPackage.php';
require_once __DIR__ . '/../../../public/api/admin/publication/ThumbnailExportService.php';

use FreeTV\Admin\DatasetPackage;
use FreeTV\Admin\Publication\ThumbnailExportException;
use FreeTV\Admin\Publication\ThumbnailExportService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ThumbnailExportServiceTest extends TestCase
{
    private string $root;
    private string $thumbs;
    private string $undo;

    protected function setUp(): void
    {
        if (!extension_loaded('imagick')) {
            self::markTestSkipped('Imagick is required for thumbnail export validation.');
        }
        $this->root = sys_get_temp_dir() . '/freetv-thumbnail-export-' . bin2hex(random_bytes(8));
        $this->thumbs = $this->root . '/source/thumbs';
        $this->undo = $this->root . '/private/thumbnail-undo';
        self::assertTrue(mkdir($this->thumbs, 0700, true));
        self::assertTrue(mkdir($this->undo, 0700, true));
        file_put_contents($this->undo . '/sentinel', 'unchanged thumbnail Undo state');
        file_put_contents($this->root . '/private/unrelated', 'unrelated private state');
    }

    protected function tearDown(): void
    {
        if (isset($this->root)) {
            DatasetPackage::removeTree($this->root);
        }
    }

    public function testEmptyExportProducesExactManifestAndPreservesPrivateState(): void
    {
        $before = $this->treeHashes($this->root . '/source');
        $privateBefore = $this->treeHashes($this->root . '/private');
        $destination = $this->root . '/empty-export';

        $manifest = $this->service()->export($destination);

        self::assertSame([
            'contract_version' => 1,
            'created_at' => '2026-08-24T09:10:11.123Z',
            'server_revision' => str_repeat('a', 40),
            'dataset' => ['thumbnail_count' => 0, 'total_bytes' => 0],
            'files' => [],
        ], $manifest);
        self::assertDirectoryExists($destination . '/thumbs');
        self::assertSame(
            $manifest,
            json_decode((string) file_get_contents($destination . '/manifest.json'), true, 512, JSON_THROW_ON_ERROR)
        );
        self::assertSame(0644, fileperms($destination . '/manifest.json') & 0777);
        self::assertSame($before, $this->treeHashes($this->root . '/source'));
        self::assertSame($privateBefore, $this->treeHashes($this->root . '/private'));
    }

    public function testSuccessfulExportPreservesExactOrderedJpegBytesAndMetadata(): void
    {
        $second = $this->jpeg('tt7654321.jpg', '#336699');
        $first = $this->jpeg('tt0052520.jpg', '#993366');
        $sourceBefore = $this->treeHashes($this->root . '/source');
        $privateBefore = $this->treeHashes($this->root . '/private');
        $destination = $this->root . '/export';

        $manifest = $this->service()->export($destination);
        $bytes = ['tt0052520.jpg' => $first, 'tt7654321.jpg' => $second];
        $expectedFiles = [];
        foreach ($bytes as $filename => $contents) {
            $expectedFiles[] = [
                'path' => 'thumbs/' . $filename,
                'sha256' => hash('sha256', $contents),
                'bytes' => strlen($contents),
            ];
            self::assertSame($contents, file_get_contents($destination . '/thumbs/' . $filename));
            self::assertSame(0644, fileperms($destination . '/thumbs/' . $filename) & 0777);
        }
        self::assertSame($expectedFiles, $manifest['files']);
        self::assertSame([
            'thumbnail_count' => 2,
            'total_bytes' => strlen($first) + strlen($second),
        ], $manifest['dataset']);
        self::assertSame('2026-08-24T09:10:11.123Z', $manifest['created_at']);
        self::assertSame(str_repeat('a', 40), $manifest['server_revision']);
        self::assertSame(['manifest.json', 'thumbs'], array_values(array_diff(scandir($destination), ['.', '..'])));
        self::assertSame($manifest, json_decode((string) file_get_contents($destination . '/manifest.json'), true, 512, JSON_THROW_ON_ERROR));
        self::assertSame($sourceBefore, $this->treeHashes($this->root . '/source'));
        self::assertSame($privateBefore, $this->treeHashes($this->root . '/private'));
    }

    public function testModificationTimeDoesNotAffectContentIdentity(): void
    {
        $this->jpeg('tt0052520.jpg', '#993366');
        $first = $this->service()->export($this->root . '/first');
        touch($this->thumbs . '/tt0052520.jpg', 1_800_000_000);
        $second = $this->service()->export($this->root . '/second');
        self::assertSame($first['files'], $second['files']);
    }

    #[DataProvider('revisionProvider')]
    public function testUnavailableOrInvalidRevisionNormalizesToNull(mixed $revision): void
    {
        $manifest = $this->service(static fn(): mixed => $revision)
            ->export($this->root . '/revision-' . bin2hex(random_bytes(3)));
        self::assertNull($manifest['server_revision']);
    }

    public static function revisionProvider(): array
    {
        return ['null' => [null], 'short' => ['abc'], 'nonhex' => [str_repeat('z', 40)], 'integer' => [123]];
    }

    #[DataProvider('invalidEntryProvider')]
    public function testInvalidSourceEntriesFailWithoutChangingSources(string $case): void
    {
        $outside = $this->root . '/outside.jpg';
        file_put_contents($outside, 'outside');
        $path = match ($case) {
            'poster' => $this->thumbs . '/poster.jpg',
            'text' => $this->thumbs . '/notes.txt',
            'hidden' => $this->thumbs . '/.thumbnail-temporary',
            'invalid jpeg' => $this->thumbs . '/tt9999997.jpg',
            'directory' => $this->thumbs . '/tt9999998.jpg',
            'symlink' => $this->thumbs . '/tt9999999.jpg',
        };
        if ($case === 'directory') {
            mkdir($path);
        } elseif ($case === 'symlink') {
            symlink($outside, $path);
        } else {
            file_put_contents($path, 'invalid source bytes');
        }
        $before = $this->treeState($this->root);
        $destination = $this->root . '/invalid-export';

        $this->expectExportFailure(fn() => $this->service()->export($destination));

        self::assertFileDoesNotExist($destination);
        self::assertSame($before, $this->treeState($this->root));
    }

    public static function invalidEntryProvider(): array
    {
        return [
            'unexpected jpg' => ['poster'], 'text file' => ['text'], 'hidden file' => ['hidden'],
            'invalid jpeg bytes' => ['invalid jpeg'], 'directory' => ['directory'], 'symlink' => ['symlink'],
        ];
    }

    public function testMissingSourceDirectoryIsRejectedBeforeDestinationCreation(): void
    {
        $destination = $this->root . '/missing-source-export';
        $service = new ThumbnailExportService(
            $this->root . '/missing/thumbs',
            $this->clock(),
            static fn(): string => str_repeat('a', 40)
        );
        $exception = $this->expectExportFailure(fn() => $service->export($destination));
        self::assertSame(409, $exception->getHttpStatus());
        self::assertFileDoesNotExist($destination);
    }

    #[DataProvider('existingDestinationProvider')]
    public function testExistingDestinationIsRejectedWithoutModification(bool $withFile): void
    {
        $destination = $this->root . '/existing';
        mkdir($destination);
        if ($withFile) {
            file_put_contents($destination . '/keep', 'keep');
        }
        $before = $this->treeState($destination);
        $exception = $this->expectExportFailure(fn() => $this->service()->export($destination));
        self::assertSame(409, $exception->getHttpStatus());
        self::assertSame($before, $this->treeState($destination));
    }

    public static function existingDestinationProvider(): array
    {
        return ['empty' => [false], 'nonempty' => [true]];
    }

    public function testDestinationInsideSourceAndMissingParentAreRejected(): void
    {
        $inside = $this->thumbs . '/nested-export';
        $exception = $this->expectExportFailure(fn() => $this->service()->export($inside));
        self::assertSame(400, $exception->getHttpStatus());
        self::assertFileDoesNotExist($inside);

        $missingParent = $this->root . '/missing/export';
        $exception = $this->expectExportFailure(fn() => $this->service()->export($missingParent));
        self::assertSame(400, $exception->getHttpStatus());
        self::assertFileDoesNotExist($missingParent);
    }

    public function testStagingFailureRemovesOwnedDestinationAndPreservesSourceAndUndoState(): void
    {
        $this->jpeg('tt0052520.jpg', '#993366');
        $before = $this->treeState($this->root . '/source');
        $privateBefore = $this->treeState($this->root . '/private');
        $destination = $this->root . '/failed-export';
        $service = $this->service(null, static function (string $source, string $destination): void {
            file_put_contents($destination, 'partial');
            throw new RuntimeException('Injected staging failure');
        });

        $this->expectExportFailure(fn() => $service->export($destination));

        self::assertFileDoesNotExist($destination);
        self::assertSame($before, $this->treeState($this->root . '/source'));
        self::assertSame($privateBefore, $this->treeState($this->root . '/private'));
    }

    private function service(?callable $revision = null, ?callable $stager = null): ThumbnailExportService
    {
        return new ThumbnailExportService(
            $this->thumbs,
            $this->clock(),
            $revision ?? static fn(): string => str_repeat('A', 40),
            $stager
        );
    }

    private function clock(): callable
    {
        return static fn(): DateTimeImmutable => new DateTimeImmutable('2026-08-24T09:10:11.123Z');
    }

    private function jpeg(string $filename, string $color): string
    {
        $path = $this->thumbs . '/' . $filename;
        $image = new Imagick();
        $image->newImage(8, 12, new ImagickPixel($color));
        $image->setImageFormat('jpeg');
        $image->writeImage($path);
        $image->clear();
        $image->destroy();
        return (string) file_get_contents($path);
    }

    private function expectExportFailure(callable $operation): ThumbnailExportException
    {
        try {
            $operation();
            self::fail('Expected ThumbnailExportException');
        } catch (ThumbnailExportException $exception) {
            return $exception;
        }
    }

    private function treeHashes(string $root): array
    {
        return array_filter($this->treeState($root), static fn(string $value): bool => str_starts_with($value, 'file:'));
    }

    private function treeState(string $root): array
    {
        if (!is_dir($root)) {
            return [];
        }
        $state = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($iterator as $item) {
            $relative = substr($item->getPathname(), strlen($root) + 1);
            if ($item->isLink()) {
                $state[$relative] = 'link:' . readlink($item->getPathname());
            } elseif ($item->isDir()) {
                $state[$relative] = 'directory';
            } else {
                $state[$relative] = 'file:' . hash_file('sha256', $item->getPathname());
            }
        }
        ksort($state);
        return $state;
    }
}
