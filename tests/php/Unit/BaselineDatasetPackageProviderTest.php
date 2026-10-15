<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../public/api/admin/DatasetPackage.php';
require_once __DIR__ . '/../../../public/api/admin/DatasetPackageValidator.php';
require_once __DIR__ . '/../../../public/api/admin/BaselineDatasetPackageProvider.php';

use FreeTV\Admin\BaselineDatasetPackageProvider;
use FreeTV\Admin\DatasetPackage;
use FreeTV\Admin\DatasetPackageIntegrityException;
use FreeTV\Admin\DatasetPackageValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class BaselineDatasetPackageProviderTest extends TestCase
{
    private string $root;
    private string $app;
    private string $temp;
    private string $source;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/freetv-permanent-baseline-' . bin2hex(random_bytes(8));
        $this->app = $this->root . '/app';
        $this->temp = $this->root . '/private';
        mkdir($this->app . '/resources', 0700, true);
        mkdir($this->app . '/public', 0700, true);
        $this->source = $this->app . '/' . BaselineDatasetPackageProvider::PACKAGE_RELATIVE_PATH;
        file_put_contents($this->source, 'baseline package fixture');
    }

    protected function tearDown(): void { DatasetPackage::removeTree($this->root); }

    public function testSampleUsesBundledPrivateSourceAndWorkspaceUntilCleanup(): void
    {
        $calls = [];
        $provider = $this->provider(static function (string $zip, string $root, string $dataset) use (&$calls): array {
            $calls[] = [$zip, $dataset];
            mkdir($root, 0700, true);
            file_put_contents($root . '/config.json', '{}');
            return ['config.json' => hash_file('sha256', $root . '/config.json')];
        });
        $package = $provider->acquire('sample');
        $workspace = dirname($package->root());
        self::assertSame([[$this->source, 'sample']], $calls);
        self::assertStringNotContainsString('/public/', $calls[0][0]);
        self::assertSame('sample', $package->dataset());
        self::assertStringStartsWith($this->temp . '/bootstrap-packages/', realpath($workspace));
        $package->cleanup();
        self::assertDirectoryDoesNotExist($workspace);
        self::assertFileExists($this->source);
    }

    public function testUnsupportedDatasetStopsBeforeValidation(): void
    {
        $calls = 0;
        $provider = $this->provider(static function () use (&$calls): array { $calls++; return []; });
        try { $provider->acquire('official'); self::fail('Unsupported dataset accepted'); } catch (InvalidArgumentException) {}
        self::assertSame(0, $calls);
    }

    public function testSyntheticBundledZipPassesConcreteValidator(): void
    {
        $this->writeValidPackage();
        $package = $this->provider()->acquire('sample');
        $workspace = dirname($package->root());
        self::assertSame('sample', $package->dataset());
        self::assertArrayHasKey('database.sql', $package->files());
        self::assertSame('-- baseline SQL', file_get_contents($package->path('database.sql')));
        $package->cleanup();
        self::assertDirectoryDoesNotExist($workspace);
        self::assertFileExists($this->source);
    }

    #[DataProvider('unsafeSourceProvider')]
    public function testMissingAndUnsafeBundledSourcesAreRejected(string $type, string $class): void
    {
        unlink($this->source);
        if ($type === 'directory') { mkdir($this->source); }
        if ($type === 'symlink') { $target = $this->root . '/outside.zip'; file_put_contents($target, 'outside'); symlink($target, $this->source); }
        try { $this->provider()->acquire('sample'); self::fail('Unsafe source accepted'); } catch (Throwable $exception) { self::assertInstanceOf($class, $exception); }
        self::assertSame([], $this->workspaces());
    }

    public static function unsafeSourceProvider(): array
    {
        return ['missing' => ['missing', RuntimeException::class], 'directory' => ['directory', DatasetPackageIntegrityException::class], 'symlink' => ['symlink', DatasetPackageIntegrityException::class]];
    }

    #[DataProvider('unsafeStorageProvider')]
    public function testUnsafePrivateStorageIsRejectedWithoutTouchingSource(string $type): void
    {
        mkdir($this->temp, 0700);
        $base = $this->temp . '/bootstrap-packages';
        if ($type === 'file') { file_put_contents($base, 'unsafe'); }
        else { $target = $this->root . '/storage-target'; mkdir($target); symlink($target, $base); }
        $this->expectException(RuntimeException::class);
        try { $this->provider()->acquire('sample'); } finally { self::assertSame('baseline package fixture', file_get_contents($this->source)); }
    }

    public static function unsafeStorageProvider(): array { return ['file' => ['file'], 'symlink' => ['symlink']]; }

    public function testValidationFailureRetainsCauseCleansPartialWorkspaceAndPreservesOtherFiles(): void
    {
        $unrelated = $this->root . '/unrelated';
        file_put_contents($unrelated, 'keep');
        $cause = new RuntimeException('injected invalid package');
        $provider = $this->provider(static function (string $zip, string $root) use ($cause): array { mkdir($root, 0700, true); file_put_contents($root . '/partial', 'partial'); throw $cause; });
        try { $provider->acquire('sample'); self::fail('Invalid package accepted'); } catch (DatasetPackageIntegrityException $exception) { self::assertSame($cause, $exception->getPrevious()); }
        self::assertSame([], $this->workspaces());
        self::assertSame('baseline package fixture', file_get_contents($this->source));
        self::assertSame('keep', file_get_contents($unrelated));
    }

    private function provider(?callable $callback = null): BaselineDatasetPackageProvider
    {
        return new BaselineDatasetPackageProvider($this->app, $this->temp, new DatasetPackageValidator(), $callback);
    }
    private function workspaces(): array { return glob($this->temp . '/bootstrap-packages/baseline-*') ?: []; }
    private function writeValidPackage(): void
    {
        $payload = [
            'database.sql' => '-- baseline SQL',
            'config.json' => json_encode(['lastupdated' => '2026-09-02T12:00:00.000Z', 'show_ads' => false], JSON_THROW_ON_ERROR),
            'playlists/index.json' => json_encode(['default' => 'baseline.json', 'playlists' => [['filename' => 'baseline.json']]], JSON_THROW_ON_ERROR),
            'playlists/baseline.json' => json_encode(['shows' => []], JSON_THROW_ON_ERROR),
        ];
        $files = [];
        foreach ($payload as $name => $bytes) { $files[$name] = hash('sha256', $bytes); }
        $manifest = ['format_version' => 1, 'dataset' => 'sample', 'generated_at' => '2026-09-02T12:00:00.000Z', 'files' => $files];
        $zip = new ZipArchive();
        self::assertTrue($zip->open($this->source, ZipArchive::CREATE | ZipArchive::OVERWRITE));
        $zip->addFromString('manifest.json', json_encode($manifest, JSON_THROW_ON_ERROR));
        $zip->addEmptyDir('playlists');
        $zip->addEmptyDir('thumbs');
        foreach ($payload as $name => $bytes) { $zip->addFromString($name, $bytes); }
        $zip->close();
    }
}
