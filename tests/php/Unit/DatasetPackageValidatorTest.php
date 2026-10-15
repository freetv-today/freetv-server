<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../public/api/admin/DatasetPackage.php';
require_once __DIR__ . '/../../../public/api/admin/DatasetPackageValidator.php';

use FreeTV\Admin\DatasetPackage;
use FreeTV\Admin\DatasetPackageValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DatasetPackageValidatorTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/freetv-permanent-validator-' . bin2hex(random_bytes(8));
        mkdir($this->root, 0700);
    }

    protected function tearDown(): void
    {
        DatasetPackage::removeTree($this->root);
    }

    #[DataProvider('datasetProvider')]
    public function testValidArchiveExtractsVerifiedBytesAndInventory(string $dataset): void
    {
        $payload = $this->payload();
        $zip = $this->writeZip(dataset: $dataset);
        $destination = $this->root . '/private/extracted';
        mkdir(dirname($destination), 0700);

        $files = (new DatasetPackageValidator())->extractAndValidate($zip, $destination, $dataset);

        foreach ($payload as $path => $bytes) {
            self::assertSame(hash('sha256', $bytes), $files[$path]);
            self::assertSame($bytes, file_get_contents($destination . '/' . $path));
            self::assertStringStartsWith($this->root . '/', realpath($destination . '/' . $path));
        }
        self::assertDirectoryExists($destination . '/playlists');
        self::assertDirectoryExists($destination . '/thumbs');
    }

    public static function datasetProvider(): array
    {
        return ['sample' => ['sample'], 'official' => ['official']];
    }

    #[DataProvider('manifestFailureProvider')]
    public function testManifestAndRequiredContentFailures(string $case, string $message): void
    {
        $zip = match ($case) {
            'wrong dataset' => $this->writeZip(dataset: 'official'),
            'format' => $this->writeZip(format: 2),
            'timestamp' => $this->writeZip(manifestChange: static fn(array &$m) => $m['generated_at'] = '2026-02-30T25:00:00Z'),
            'manifest missing file' => $this->writeZip(manifestChange: static function (array &$m): void { unset($m['files']['playlists/fixture.json']); }),
            'invalid digest' => $this->writeZip(manifestChange: static fn(array &$m) => $m['files']['config.json'] = 'bad'),
            'hash mismatch' => $this->writeZip(manifestChange: static fn(array &$m) => $m['files']['config.json'] = str_repeat('0', 64)),
            'malformed manifest' => $this->writeZip(manifestBytes: '{bad'),
            'missing manifest' => $this->writeZip(omit: ['manifest.json']),
            'missing database' => $this->writeZip(omit: ['database.sql']),
            'missing config' => $this->writeZip(omit: ['config.json']),
            'missing index' => $this->writeZip(omit: ['playlists/index.json']),
            'missing playlists directory' => $this->writeZip(omit: ['playlists/']),
            'missing thumbs directory' => $this->writeZip(omit: ['thumbs/']),
            'extra payload' => $this->writeZip(extra: ['playlists/extra.json' => '{}']),
        };

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage($message);
        (new DatasetPackageValidator())->extractAndValidate($zip, $this->root . '/extract-' . md5($case), 'sample');
    }

    public static function manifestFailureProvider(): array
    {
        return [
            'wrong dataset' => ['wrong dataset', 'required format'],
            'unsupported format' => ['format', 'required format'],
            'invalid timestamp' => ['timestamp', 'required format'],
            'manifest inventory omission' => ['manifest missing file', 'inventory does not exactly match'],
            'invalid digest syntax' => ['invalid digest', 'invalid file entry'],
            'digest mismatch' => ['hash mismatch', 'SHA-256 mismatch'],
            'malformed manifest JSON' => ['malformed manifest', 'manifest is invalid JSON'],
            'missing manifest' => ['missing manifest', 'missing required manifest.json'],
            'missing database' => ['missing database', 'missing required database.sql'],
            'missing config' => ['missing config', 'missing required config.json'],
            'missing index' => ['missing index', 'missing required playlists/index.json'],
            'missing playlists directory' => ['missing playlists directory', 'missing required playlists/ directory'],
            'missing thumbs directory' => ['missing thumbs directory', 'missing required thumbs/ directory'],
            'unmanifested ZIP file' => ['extra payload', 'inventory does not exactly match'],
        ];
    }

    #[DataProvider('unsafeEntryProvider')]
    public function testUnsafeArchiveEntriesAreRejectedBeforeOutsideWrites(string $name, string $message): void
    {
        $zip = $this->writeZip(extra: [$name => 'hostile']);
        $outside = dirname($this->root) . '/freetv-validator-outside-' . basename($this->root);
        try {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage($message);
            (new DatasetPackageValidator())->extractAndValidate($zip, $this->root . '/extract', 'sample');
        } finally {
            self::assertFileDoesNotExist($outside);
        }
    }

    public static function unsafeEntryProvider(): array
    {
        return [
            'traversal' => ['../outside', 'unsafe archive path'],
            'absolute' => ['/outside', 'unsafe archive path'],
            'drive path' => ['C:/outside', 'unsafe archive path'],
            'backslash' => ['thumbs\\outside.jpg', 'unsafe archive path'],
            'unexpected directory' => ['private/', 'unexpected directory'],
            'unexpected payload' => ['secrets.txt', 'unexpected payload path'],
        ];
    }

    public function testCaseConflictingPathsAreRejected(): void
    {
        $zip = $this->writeZip(extra: ['thumbs/CONFLICT.jpg' => 'one', 'thumbs/conflict.jpg' => 'two']);
        $this->expectExceptionMessage('duplicate or conflicting paths');
        (new DatasetPackageValidator())->extractAndValidate($zip, $this->root . '/extract', 'sample');
    }

    public function testUnixSymlinkEntryIsRejected(): void
    {
        $zip = $this->writeZip();
        $archive = new ZipArchive();
        $archive->open($zip);
        $archive->addFromString('thumbs/link.jpg', 'target');
        $archive->setExternalAttributesName('thumbs/link.jpg', ZipArchive::OPSYS_UNIX, 0120777 << 16);
        $archive->close();
        $this->expectExceptionMessage('unsafe entry type');
        (new DatasetPackageValidator())->extractAndValidate($zip, $this->root . '/extract', 'sample');
    }

    #[DataProvider('viewerFailureProvider')]
    public function testViewerInventoryInconsistenciesAreRejected(string $case, string $message): void
    {
        $payload = $this->payload();
        match ($case) {
            'config malformed' => $payload['config.json'] = '{',
            'config shape' => $payload['config.json'] = json_encode(['lastupdated' => 'x'], JSON_THROW_ON_ERROR),
            'index missing playlist' => $payload['playlists/index.json'] = $this->index('missing.json', ['missing.json']),
            'packaged unindexed' => $payload['playlists/extra.json'] = $payload['playlists/fixture.json'],
            'default missing' => $payload['playlists/index.json'] = $this->index('missing.json', ['fixture.json']),
            'playlist malformed' => $payload['playlists/fixture.json'] = '{',
            'playlist shape' => $payload['playlists/fixture.json'] = json_encode(['shows' => 'bad'], JSON_THROW_ON_ERROR),
        };
        $zip = $this->writeZip(payload: $payload);
        $this->expectExceptionMessage($message);
        (new DatasetPackageValidator())->extractAndValidate($zip, $this->root . '/extract-' . md5($case), 'sample');
    }

    public static function viewerFailureProvider(): array
    {
        return [
            'malformed config' => ['config malformed', 'invalid Viewer JSON'],
            'config contract' => ['config shape', 'Viewer artifacts do not match'],
            'missing indexed playlist' => ['index missing playlist', 'playlist index does not match'],
            'unindexed packaged playlist' => ['packaged unindexed', 'playlist inventory does not match'],
            'missing default' => ['default missing', 'playlist inventory does not match'],
            'malformed playlist' => ['playlist malformed', 'invalid playlist JSON'],
            'playlist contract' => ['playlist shape', 'playlist does not match'],
        ];
    }

    private function payload(): array
    {
        return [
            'database.sql' => '-- fixture SQL',
            'config.json' => json_encode(['lastupdated' => '2026-09-02T12:00:00.000Z', 'show_ads' => false], JSON_THROW_ON_ERROR),
            'playlists/index.json' => $this->index('fixture.json', ['fixture.json']),
            'playlists/fixture.json' => json_encode(['lastupdated' => '2026-09-02T12:00:00.000Z', 'dbtitle' => 'Fixture', 'dbversion' => null, 'author' => null, 'email' => null, 'link' => null, 'shows' => []], JSON_THROW_ON_ERROR),
            'thumbs/tt0000001.jpg' => 'fixture thumbnail',
        ];
    }

    private function index(string $default, array $filenames): string
    {
        return json_encode(['default' => $default, 'playlists' => array_map(static fn(string $name) => ['filename' => $name, 'dbtitle' => 'Fixture', 'lastupdated' => '2026-09-02T12:00:00.000Z'], $filenames)], JSON_THROW_ON_ERROR);
    }

    private function writeZip(string $dataset = 'sample', int $format = 1, ?callable $manifestChange = null, array $extra = [], array $omit = [], ?string $manifestBytes = null, ?array $payload = null): string
    {
        $payload ??= $this->payload();
        $manifest = ['format_version' => $format, 'dataset' => $dataset, 'generated_at' => '2026-09-02T12:00:00.000Z', 'files' => []];
        foreach ($payload as $name => $bytes) { $manifest['files'][$name] = hash('sha256', $bytes); }
        if ($manifestChange !== null) { $manifestChange($manifest); }
        $path = $this->root . '/fixture-' . bin2hex(random_bytes(4)) . '.zip';
        $zip = new ZipArchive();
        self::assertTrue($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE));
        if (!in_array('manifest.json', $omit, true)) { $zip->addFromString('manifest.json', $manifestBytes ?? json_encode($manifest, JSON_THROW_ON_ERROR)); }
        foreach (['playlists/', 'thumbs/'] as $directory) { if (!in_array($directory, $omit, true)) { $zip->addEmptyDir(rtrim($directory, '/')); } }
        foreach ($payload as $name => $bytes) { if (!in_array($name, $omit, true)) { $zip->addFromString($name, $bytes); } }
        foreach ($extra as $name => $bytes) { str_ends_with($name, '/') ? $zip->addEmptyDir(rtrim($name, '/')) : $zip->addFromString($name, $bytes); }
        $zip->close();
        return $path;
    }
}
