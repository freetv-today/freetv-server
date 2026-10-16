<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../public/api/admin/DatasetPackage.php';
require_once __DIR__ . '/../../../public/api/admin/FreshArtifactInstaller.php';

use FreeTV\Admin\DatasetPackage;
use FreeTV\Admin\FreshArtifactInstaller;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class FreshArtifactInstallerTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/freetv-permanent-fresh-installer-' . bin2hex(random_bytes(8));
        mkdir($this->root . '/playlists', 0700, true);
    }
    protected function tearDown(): void { DatasetPackage::removeTree($this->root); }

    public function testSuccessfulCommitReplacesOnlyManagedFilesAndCleansStage(): void
    {
        $this->seedManaged();
        file_put_contents($this->root . '/unrelated.bin', "keep\0bytes");
        file_put_contents($this->root . '/playlists/unrelated.json', 'keep playlist');
        $installation = (new FreshArtifactInstaller($this->root))->prepare($this->artifacts());
        self::assertNotSame([], glob($this->root . '/.freetv-fresh-*') ?: []);
        $installation->promote();
        $installation->commit();
        foreach ($this->artifacts() as $path => $value) {
            self::assertSame($value, json_decode((string) file_get_contents($this->root . '/' . $path), true, 512, JSON_THROW_ON_ERROR));
            self::assertSame(0644, fileperms($this->root . '/' . $path) & 0777);
        }
        self::assertSame("keep\0bytes", file_get_contents($this->root . '/unrelated.bin'));
        self::assertSame('keep playlist', file_get_contents($this->root . '/playlists/unrelated.json'));
        self::assertSame([], $this->stages());
    }

    #[DataProvider('invalidArtifactProvider')]
    public function testInvalidArtifactKeySetsAndOrderAreRejected(array $artifacts): void
    {
        $before = $this->seedManaged();
        $this->expectException(RuntimeException::class);
        try { (new FreshArtifactInstaller($this->root))->prepare($artifacts); }
        finally { self::assertSame($before, $this->snapshot()); self::assertSame([], $this->stages()); }
    }
    public static function invalidArtifactProvider(): array
    {
        $valid = self::staticArtifacts();
        $missing = $valid; unset($missing['config.json']);
        $extra = $valid + ['extra.json' => []];
        $reordered = ['playlists/index.json' => $valid['playlists/index.json'], 'config.json' => $valid['config.json'], 'playlists/playlist-one.json' => $valid['playlists/playlist-one.json']];
        return ['missing' => [$missing], 'extra' => [$extra], 'wrong order' => [$reordered]];
    }

    public function testInvalidPublicRootIsRejected(): void
    {
        $this->expectException(RuntimeException::class);
        (new FreshArtifactInstaller($this->root . '/missing'))->prepare($this->artifacts());
    }

    #[DataProvider('unsafeDestinationProvider')]
    public function testManagedNonFileDestinationsAreRejectedAndCleaned(string $path): void
    {
        $target = $this->root . '/' . $path;
        if (file_exists($target)) { unlink($target); }
        mkdir($target, 0700);
        $before = $this->snapshot();
        try { (new FreshArtifactInstaller($this->root))->prepare($this->artifacts()); self::fail('Unsafe destination accepted'); }
        catch (RuntimeException $exception) { self::assertStringContainsString('not a regular file', $exception->getMessage()); }
        self::assertSame($before, $this->snapshot());
        self::assertSame([], $this->stages());
    }
    public static function unsafeDestinationProvider(): array { return ['config' => ['config.json'], 'index' => ['playlists/index.json'], 'playlist' => ['playlists/playlist-one.json']]; }

    public function testJsonEncodingFailurePreservesExistingStateAndCleansStage(): void
    {
        $before = $this->seedManaged();
        $artifacts = $this->artifacts();
        $artifacts['config.json']['invalid'] = fopen('php://memory', 'r');
        try { (new FreshArtifactInstaller($this->root))->prepare($artifacts); self::fail('Unencodable value accepted'); }
        catch (RuntimeException $exception) { self::assertStringContainsString('encode', $exception->getMessage()); }
        self::assertSame($before, $this->snapshot());
        self::assertSame([], $this->stages());
    }

    #[DataProvider('promotionFailureProvider')]
    public function testPromotionFailuresRestoreExactOriginalTree(string $phase): void
    {
        $before = $this->seedManaged($phase === 'new-file' ? ['playlists/playlist-one.json'] : []);
        file_put_contents($this->root . '/unrelated', 'keep');
        $before = $this->snapshot();
        $failed = false;
        $rename = function (string $from, string $to) use (&$failed, $phase): bool {
            $normalized = str_replace('\\', '/', $to);
            $fail = match ($phase) {
                'backup' => str_ends_with($normalized, '/0.backup'),
                'promote' => str_ends_with($normalized, '/config.json'),
                'after-first' => str_ends_with($normalized, '/playlists/index.json'),
                'new-file' => str_ends_with($normalized, '/playlists/playlist-one.json'),
            };
            if (!$failed && $fail) { $failed = true; return false; }
            return rename($from, $to);
        };
        $installation = (new FreshArtifactInstaller($this->root, $rename))->prepare($this->artifacts());
        try { $installation->promote(); self::fail('Expected promotion failure'); } catch (RuntimeException) {}
        self::assertSame($before, $this->snapshot());
        self::assertSame([], $this->stages());
    }
    public static function promotionFailureProvider(): array { return ['backup failure' => ['backup'], 'first promotion' => ['promote'], 'after earlier replacement' => ['after-first'], 'new destination cleanup' => ['new-file']]; }

    #[DataProvider('verificationFailureProvider')]
    public function testVerificationFailuresRestoreExactOriginalTree(string $corruption): void
    {
        $this->seedManaged();
        file_put_contents($this->root . '/unrelated', 'keep');
        $before = $this->snapshot();
        $changed = false;
        $rename = function (string $from, string $to) use (&$changed, $corruption): bool {
            $result = rename($from, $to);
            if ($result && !$changed && $to === $this->root . '/config.json') {
                file_put_contents($to, $corruption === 'invalid' ? '{' : '{"lastupdated":"different","show_ads":false}');
                $changed = true;
            }
            return $result;
        };
        try { (new FreshArtifactInstaller($this->root, $rename))->prepare($this->artifacts())->promote(); self::fail('Expected verification failure'); }
        catch (RuntimeException $exception) { self::assertStringContainsString($corruption === 'invalid' ? 'invalid JSON' : 'verification failed', $exception->getMessage()); }
        self::assertSame($before, $this->snapshot());
        self::assertSame([], $this->stages());
    }
    public static function verificationFailureProvider(): array { return ['invalid JSON' => ['invalid'], 'different JSON' => ['different']]; }

    public function testExplicitRollbackAfterPrepareRemovesNewPlaylistDirectory(): void
    {
        DatasetPackage::removeTree($this->root . '/playlists');
        $installation = (new FreshArtifactInstaller($this->root))->prepare($this->artifacts());
        self::assertDirectoryExists($this->root . '/playlists');
        $installation->rollback();
        self::assertDirectoryDoesNotExist($this->root . '/playlists');
        self::assertSame([], $this->stages());
    }

    public function testRollbackAfterPromoteRestoresStateAndRollbackAfterCommitIsNoOp(): void
    {
        $before = $this->seedManaged();
        $installation = (new FreshArtifactInstaller($this->root))->prepare($this->artifacts());
        $installation->promote(); $installation->rollback();
        self::assertSame($before, $this->snapshot());
        $committed = (new FreshArtifactInstaller($this->root))->prepare($this->artifacts());
        $committed->promote(); $committed->commit();
        $after = $this->snapshot(); $committed->rollback();
        self::assertSame($after, $this->snapshot());
    }

    private function artifacts(): array { return self::staticArtifacts(); }
    private static function staticArtifacts(): array
    {
        return ['config.json' => ['lastupdated' => '2026-09-02T12:00:00.000Z', 'show_ads' => false], 'playlists/index.json' => ['default' => 'playlist-one.json', 'playlists' => [['filename' => 'playlist-one.json']]], 'playlists/playlist-one.json' => ['shows' => []]];
    }
    private function seedManaged(array $omit = []): array
    {
        foreach (array_keys($this->artifacts()) as $path) { if (!in_array($path, $omit, true)) { file_put_contents($this->root . '/' . $path, 'old:' . $path); } }
        return $this->snapshot();
    }
    private function stages(): array { return glob($this->root . '/.freetv-fresh-*') ?: []; }
    private function snapshot(): array
    {
        $result = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->root, FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $item) { $relative = substr($item->getPathname(), strlen($this->root) + 1); $result[$relative] = $item->isFile() ? hash_file('sha256', $item->getPathname()) : 'directory'; }
        ksort($result); return $result;
    }
}
