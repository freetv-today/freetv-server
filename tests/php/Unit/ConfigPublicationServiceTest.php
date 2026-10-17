<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../public/api/admin/DatasetPackage.php';
require_once __DIR__ . '/../../../public/api/admin/publication/ConfigPublicationService.php';

use FreeTV\Admin\DatasetPackage;
use FreeTV\Admin\Publication\ConfigPublicationService;
use FreeTV\Admin\Publication\PublicationException;
use FreeTV\Admin\Publication\PublicationUndoService;
use PHPUnit\Framework\TestCase;

final class ConfigPublicationServiceTest extends TestCase
{
    private string $root;
    private string $public;
    private string $undoRoot;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/freetv-config-publication-' . bin2hex(random_bytes(8));
        $this->public = $this->root . '/public';
        $this->undoRoot = $this->root . '/private/publication-undo';
        self::assertTrue(mkdir($this->public, 0700, true));
    }

    protected function tearDown(): void
    {
        DatasetPackage::removeTree($this->root);
    }

    public function testPublishWritesExactArtifactAndConcreteUndoRestoresPriorBytes(): void
    {
        $oldBytes = '{"lastupdated":"2026-08-12T18:00:00.000Z","show_ads":false}';
        file_put_contents($this->public . '/config.json', $oldBytes);
        file_put_contents($this->public . '/unrelated.txt', 'preserve me');
        $undo = new PublicationUndoService($this->public, $this->undoRoot);
        $service = new ConfigPublicationService(
            $this->public,
            static fn(): array => ['show_ads' => true, 'showads' => false],
            static fn(): DateTimeImmutable => new DateTimeImmutable('2026-08-12T19:30:00.876Z'),
            $undo
        );

        $result = $service->publish();
        $expectedBytes = "{\n"
            . "    \"lastupdated\": \"2026-08-12T19:30:00.000Z\",\n"
            . "    \"show_ads\": true\n"
            . "}\n";

        self::assertSame(['lastupdated' => '2026-08-12T19:30:00.000Z'], $result);
        self::assertSame($expectedBytes, file_get_contents($this->public . '/config.json'));
        self::assertSame([
            'lastupdated' => '2026-08-12T19:30:00.000Z',
            'show_ads' => true,
        ], json_decode($expectedBytes, true, 512, JSON_THROW_ON_ERROR));
        self::assertSame(0644, fileperms($this->public . '/config.json') & 0777);
        self::assertSame('preserve me', file_get_contents($this->public . '/unrelated.txt'));
        self::assertSame([], glob($this->public . '/.publish-*') ?: []);
        self::assertDirectoryExists($this->undoRoot . '/active');
        self::assertStringStartsNotWith($this->public . '/', $this->undoRoot);
        self::assertSame('config', $undo->status()['operation']);

        self::assertSame([
            'operation' => 'config',
            'target' => 'config.json',
            'restored' => ['config.json'],
        ], $undo->undo());
        self::assertSame($oldBytes, file_get_contents($this->public . '/config.json'));
        self::assertSame('preserve me', file_get_contents($this->public . '/unrelated.txt'));
        self::assertSame(
            ['available' => false, 'operation' => null, 'target' => null],
            $undo->status()
        );
        self::assertSame([], $this->temporaryArtifacts());
    }

    public function testInvalidPublicationRootPreservesHistoricalFailureContract(): void
    {
        $invalidRoot = $this->root . '/not-a-directory';
        file_put_contents($invalidRoot, 'fixture');
        $service = new ConfigPublicationService(
            $invalidRoot,
            static fn(): array => ['show_ads' => false],
            static fn(): DateTimeImmutable => new DateTimeImmutable('2026-08-12T20:00:00Z'),
            new PublicationUndoService($invalidRoot, $this->undoRoot)
        );

        $exception = $this->capturePublicationFailure(fn() => $service->publish());

        self::assertSame(409, $exception->getHttpStatus());
        self::assertSame(
            'Cannot publish without live artifact config.json',
            $exception->getMessage()
        );
        self::assertSame('fixture', file_get_contents($invalidRoot));
        self::assertSame([], $this->temporaryArtifacts());
    }

    public function testInvalidSettingsLeaveExistingConfigUnchanged(): void
    {
        $before = '{"show_ads":false}';
        file_put_contents($this->public . '/config.json', $before);
        $service = new ConfigPublicationService(
            $this->public,
            static fn(): array => ['show_ads' => 'true'],
            static fn(): DateTimeImmutable => new DateTimeImmutable('2026-08-12T20:00:00Z'),
            new PublicationUndoService($this->public, $this->undoRoot)
        );

        try {
            $service->publish();
            self::fail('Expected invalid settings failure');
        } catch (InvalidArgumentException $exception) {
            self::assertSame('Setting show_ads must be boolean', $exception->getMessage());
        }

        self::assertSame($before, file_get_contents($this->public . '/config.json'));
        self::assertSame([], $this->temporaryArtifacts());
    }

    public function testInvalidTimestampLeavesExistingConfigUnchanged(): void
    {
        $before = '{"show_ads":false}';
        file_put_contents($this->public . '/config.json', $before);
        $service = new ConfigPublicationService(
            $this->public,
            static fn(): array => ['show_ads' => true],
            static fn(): string => 'not-a-timestamp',
            new PublicationUndoService($this->public, $this->undoRoot)
        );

        try {
            $service->publish();
            self::fail('Expected invalid timestamp failure');
        } catch (InvalidArgumentException $exception) {
            self::assertSame('Invalid publication timestamp', $exception->getMessage());
        }

        self::assertSame($before, file_get_contents($this->public . '/config.json'));
        self::assertSame([], $this->temporaryArtifacts());
    }

    private function capturePublicationFailure(callable $operation): PublicationException
    {
        try {
            $operation();
            self::fail('Expected PublicationException');
        } catch (PublicationException $exception) {
            return $exception;
        }
    }

    private function temporaryArtifacts(): array
    {
        $artifacts = [];
        if (!is_dir($this->root)) {
            return $artifacts;
        }
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->root, FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $item) {
            if (str_starts_with($item->getFilename(), '.publish-')
                || str_starts_with($item->getFilename(), '.prepared-')
                || str_starts_with($item->getFilename(), '.rollback-')
                || str_starts_with($item->getFilename(), '.prior-')) {
                $artifacts[] = $item->getPathname();
            }
        }
        sort($artifacts);
        return $artifacts;
    }
}
