<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../public/api/admin/DatasetPackage.php';
require_once __DIR__ . '/../../../public/api/admin/DatasetPackageValidator.php';
require_once __DIR__ . '/../../../public/api/admin/DatasetPackageProvider.php';

use FreeTV\Admin\DatasetPackage;
use FreeTV\Admin\DatasetPackageAvailabilityException;
use FreeTV\Admin\DatasetPackageIntegrityException;
use FreeTV\Admin\DatasetPackageMetadataAvailabilityException;
use FreeTV\Admin\DatasetPackageMetadataIntegrityException;
use FreeTV\Admin\DatasetPackageProvider;
use FreeTV\Admin\DatasetPackageValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DatasetPackageProviderTest extends TestCase
{
    private string $root;
    private string|false $oldEnv;
    private bool $hadEnv;
    private mixed $oldEnvArray;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/freetv-permanent-provider-' . bin2hex(random_bytes(8));
        mkdir($this->root, 0700);
        $this->oldEnv = getenv('FREETV_DATASET_METADATA_URL');
        $this->hadEnv = array_key_exists('FREETV_DATASET_METADATA_URL', $_ENV);
        $this->oldEnvArray = $_ENV['FREETV_DATASET_METADATA_URL'] ?? null;
        $this->setUrl(null);
    }

    protected function tearDown(): void
    {
        $this->oldEnv === false ? putenv('FREETV_DATASET_METADATA_URL') : putenv('FREETV_DATASET_METADATA_URL=' . $this->oldEnv);
        if ($this->hadEnv) { $_ENV['FREETV_DATASET_METADATA_URL'] = $this->oldEnvArray; }
        else { unset($_ENV['FREETV_DATASET_METADATA_URL']); }
        DatasetPackage::removeTree($this->root);
    }

    public function testDefaultAndExplicitMetadataUrlsAreSelected(): void
    {
        self::assertSame(DatasetPackageProvider::DEFAULT_METADATA_URL, $this->provider('default')->metadataUrl());
        $this->setUrl('https://metadata.example.test/current.json');
        self::assertSame('https://metadata.example.test/current.json', $this->provider('override')->metadataUrl());
    }

    #[DataProvider('invalidUrlProvider')]
    public function testInvalidMetadataUrlOverridesAreRejected(string $url): void
    {
        $this->setUrl($url);
        $this->expectException(InvalidArgumentException::class);
        $this->provider('bad-url');
    }

    public static function invalidUrlProvider(): array
    {
        return ['http' => ['http://example.test/meta'], 'malformed' => ['not a URL'], 'user' => ['https://user@example.test/meta'], 'password' => ['https://user:secret@example.test/meta']];
    }

    #[DataProvider('datasetProvider')]
    public function testSupportedDatasetSelectsTrustedDefinitionAndRetainsWorkspaceUntilCleanup(string $dataset, string $bytes): void
    {
        $downloads = [];
        $validations = [];
        $provider = $this->provider('success-' . $dataset,
            static function (string $url, string $destination) use (&$downloads, $bytes): void { $downloads[] = $url; file_put_contents($destination, $bytes); },
            fn(): string => $this->metadataJson(),
            static function (string $zip, string $root, string $selected) use (&$validations): array {
                $validations[] = [$selected, is_file($zip), str_ends_with($root, '/extracted')];
                mkdir($root, 0700);
                file_put_contents($root . '/config.json', '{}');
                return ['config.json' => hash_file('sha256', $root . '/config.json')];
            });
        $package = $provider->acquire($dataset);
        $workspace = dirname($package->root());
        self::assertSame([$this->metadata()[$dataset]['url']], $downloads);
        self::assertSame([[$dataset, true, true]], $validations);
        self::assertDirectoryExists($workspace);
        self::assertFileDoesNotExist($workspace . '/download.zip');
        self::assertStringStartsWith($this->root . '/', realpath($workspace));
        $package->cleanup();
        self::assertDirectoryDoesNotExist($workspace);
    }

    public static function datasetProvider(): array
    {
        return ['sample' => ['sample', 'sample archive fixture'], 'official' => ['official', 'official archive fixture']];
    }

    public function testUnsupportedDatasetStopsBeforeMetadataOrDownload(): void
    {
        $calls = 0;
        $provider = $this->provider('unsupported', static function () use (&$calls): void { $calls++; }, static function () use (&$calls): string { $calls++; return '{}'; });
        try { $provider->acquire('baseline'); self::fail('Unsupported dataset accepted'); } catch (InvalidArgumentException) {}
        self::assertSame(0, $calls);
    }

    #[DataProvider('invalidMetadataProvider')]
    public function testInvalidMetadataStopsBeforeDownload(string $document): void
    {
        $downloads = 0;
        $provider = $this->provider('invalid-' . md5($document), static function () use (&$downloads): void { $downloads++; }, static fn(): string => $document);
        try { $provider->acquire('sample'); self::fail('Invalid metadata accepted'); } catch (DatasetPackageMetadataIntegrityException) {}
        self::assertSame(0, $downloads);
        self::assertSame([], $this->workspaces('invalid-' . md5($document)));
    }

    public static function invalidMetadataProvider(): array
    {
        $valid = self::staticMetadata();
        return [
            'malformed JSON' => ['{bad'],
            'root scalar' => ['[]'],
            'format' => [self::json(array_replace($valid, ['format_version' => 2]))],
            'extra root key' => [self::json($valid + ['extra' => true])],
            'missing sample' => [self::json(array_diff_key($valid, ['sample' => true]))],
            'missing official' => [self::json(array_diff_key($valid, ['official' => true]))],
            'extra definition key' => [self::json(array_replace($valid, ['sample' => $valid['sample'] + ['extra' => true]]))],
            'HTTP asset' => [self::json(array_replace($valid, ['sample' => ['url' => 'http://example.test/a.zip', 'sha256' => $valid['sample']['sha256']]]))],
            'credential asset' => [self::json(array_replace($valid, ['sample' => ['url' => 'https://user@example.test/a.zip', 'sha256' => $valid['sample']['sha256']]]))],
            'uppercase digest' => [self::json(array_replace($valid, ['sample' => ['url' => $valid['sample']['url'], 'sha256' => str_repeat('A', 64)]]))],
            'nonhex digest' => [self::json(array_replace($valid, ['official' => ['url' => $valid['official']['url'], 'sha256' => str_repeat('z', 64)]]))],
        ];
    }

    public function testMetadataFetcherFailureIsAvailabilityAndCreatesNoWorkspace(): void
    {
        $cause = new RuntimeException('DNS failure');
        $provider = $this->provider('metadata-failure', null, static fn() => throw $cause);
        try { $provider->acquire('sample'); self::fail('Expected failure'); } catch (DatasetPackageMetadataAvailabilityException $exception) { self::assertSame($cause, $exception->getPrevious()); }
        self::assertSame([], $this->workspaces('metadata-failure'));
    }

    public function testDownloaderFailureIsAvailabilityAndCleansWorkspace(): void
    {
        $cause = new RuntimeException('transport failure');
        $provider = $this->provider('download-failure', static fn() => throw $cause, fn(): string => $this->metadataJson());
        try { $provider->acquire('sample'); self::fail('Expected failure'); } catch (DatasetPackageAvailabilityException $exception) { self::assertSame($cause, $exception->getPrevious()); }
        self::assertSame([], $this->workspaces('download-failure'));
    }

    public function testMissingDownloadIsIntegrityFailureAndCleansWorkspace(): void
    {
        $provider = $this->provider('missing-download', static function (): void {}, fn(): string => $this->metadataJson());
        try { $provider->acquire('sample'); self::fail('Expected failure'); } catch (DatasetPackageIntegrityException $exception) { self::assertStringContainsString('verify', $exception->getMessage()); }
        self::assertSame([], $this->workspaces('missing-download'));
    }

    public function testOuterHashMismatchPreventsValidationAndCleansWorkspace(): void
    {
        $validations = 0;
        $provider = $this->provider('hash-mismatch', static fn(string $url, string $path) => file_put_contents($path, 'wrong'), fn(): string => $this->metadataJson(), static function () use (&$validations): array { $validations++; return []; });
        try { $provider->acquire('sample'); self::fail('Expected failure'); } catch (DatasetPackageIntegrityException $exception) { self::assertStringContainsString('does not match', $exception->getMessage()); }
        self::assertSame(0, $validations);
        self::assertSame([], $this->workspaces('hash-mismatch'));
    }

    public function testValidatorFailureRetainsCauseAndCleansOnlyWorkspace(): void
    {
        $outside = $this->root . '/unrelated.txt';
        file_put_contents($outside, 'keep');
        $cause = new RuntimeException('invalid ZIP');
        $provider = $this->provider('validation-failure', static fn(string $url, string $path) => file_put_contents($path, 'sample archive fixture'), fn(): string => $this->metadataJson(), static fn() => throw $cause);
        try { $provider->acquire('sample'); self::fail('Expected failure'); } catch (DatasetPackageIntegrityException $exception) { self::assertSame($cause, $exception->getPrevious()); }
        self::assertSame([], $this->workspaces('validation-failure'));
        self::assertSame('keep', file_get_contents($outside));
    }

    #[DataProvider('unsafeStorageProvider')]
    public function testUnsafeStorageTypesAreRejected(string $type): void
    {
        $temp = $this->root . '/' . $type;
        mkdir($temp, 0700);
        $base = $temp . '/bootstrap-packages';
        if ($type === 'file') { file_put_contents($base, 'unsafe'); }
        else { $target = $this->root . '/target'; mkdir($target); symlink($target, $base); }
        $provider = new DatasetPackageProvider($temp, new DatasetPackageValidator(), metadataFetcher: fn(): string => $this->metadataJson(), appRoot: $this->root . '/app-' . $type);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('unsafe type');
        $provider->acquire('sample');
    }

    public static function unsafeStorageProvider(): array { return ['file' => ['file'], 'symlink' => ['symlink']]; }

    private function provider(string $name, ?callable $downloader = null, ?callable $fetcher = null, ?callable $validator = null): DatasetPackageProvider
    {
        return new DatasetPackageProvider($this->root . '/' . $name, new DatasetPackageValidator(), $downloader, $fetcher, $validator, $this->root . '/app-' . $name);
    }
    private function setUrl(?string $value): void
    {
        if ($value === null) { putenv('FREETV_DATASET_METADATA_URL'); unset($_ENV['FREETV_DATASET_METADATA_URL']); }
        else { putenv('FREETV_DATASET_METADATA_URL=' . $value); $_ENV['FREETV_DATASET_METADATA_URL'] = $value; }
    }
    private function workspaces(string $name): array { return glob($this->root . '/' . $name . '/bootstrap-packages/package-*') ?: []; }
    private function metadata(): array { return self::staticMetadata(); }
    private function metadataJson(): string { return self::json($this->metadata()); }
    private static function staticMetadata(): array { return ['format_version' => 1, 'sample' => ['url' => 'https://packages.example.test/sample.zip', 'sha256' => hash('sha256', 'sample archive fixture')], 'official' => ['url' => 'https://packages.example.test/official.zip', 'sha256' => hash('sha256', 'official archive fixture')]]; }
    private static function json(array $value): string { return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES); }
}
